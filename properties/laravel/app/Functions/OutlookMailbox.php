<?php

namespace App\Functions;

use App\Models\InboxMessage;
use App\Models\User;
use App\Services\MicrosoftGraph;

/**
 * Portert fra base44/functions/outlookMailbox/entry.ts
 *
 * Admin-klient mot delte postbokser (post@/booking@/faktura@aprop.no) via Microsoft Graph.
 * Actions (payload.action, standard 'list'):
 *   list     {mailbox?, folder?}            → {messages: [...]}  (folder 'samtalelogg' = InboxMessage-loggen)
 *   get      {mailbox?, folder?, messageId} → {message: {...}}
 *   folders  {mailbox?}                     → {folders: [...]}
 *   send     {mailbox?, to[], cc?, replyTo?, subject, body} → {success: true}  (+ logg som InboxMessage)
 *   reply    {mailbox?, messageId, body, to?, subject?}     → {success: true}  (+ logg som InboxMessage)
 *   markRead {mailbox?, messageId, isRead?} → {success: true}
 *   delete   {mailbox?, messageId}          → {success: true}
 *
 * Avvik fra originalen:
 *  - Token kom fra Base44-connectoren «outlook» (delegert OAuth). Her brukes
 *    App\Services\MicrosoftGraph (client credentials; krever Application permissions
 *    Mail.ReadWrite + Mail.Send i Entra, se kommentar der).
 *  - Graph-feil ga {error: 'Graph 4xx: …'} med status 500 – beholdt (FunctionException 500).
 */
class OutlookMailbox extends Base44Function
{
    public const DEFAULT_MAILBOX = 'post@aprop.no';

    private const FOLDER_MAP = [
        'inbox' => 'inbox',
        'sent' => 'sentitems',
        'sentitems' => 'sentitems',
        'drafts' => 'drafts',
        'junk' => 'junkemail',
        'junkemail' => 'junkemail',
        'deleted' => 'deleteditems',
        'deleteditems' => 'deleteditems',
        'archive' => 'archive',
        'conversationhistory' => 'conversationhistory',
        'outbox' => 'outbox',
    ];

    private const FOLDER_DISPLAY = [
        'samtalelogg' => 'Samtalelogg',
    ];

    private MicrosoftGraph $graph;

    public function __invoke(?User $user, array $payload): array
    {
        $user = $this->requireAdmin($user);
        $this->graph = app(MicrosoftGraph::class);

        $action = $payload['action'] ?? 'list';
        $mailbox = (string) ($payload['mailbox'] ?? self::DEFAULT_MAILBOX);
        $mb = rawurlencode($mailbox);

        switch ($action) {
            case 'list':
                return $this->list($payload, $mailbox, $mb);

            case 'get':
                $messageId = $payload['messageId'] ?? null;
                if (!$messageId) {
                    throw new FunctionException('messageId required', 400);
                }
                if (($payload['folder'] ?? null) === 'samtalelogg') {
                    $im = InboxMessage::find($messageId);
                    if (!$im) {
                        throw new FunctionException('InboxMessage not found', 404);
                    }
                    return ['message' => $this->logToMessage($im, $mailbox, true)];
                }
                $data = $this->graph->get("/users/{$mb}/messages/" . rawurlencode($messageId)
                    . '?$select=id,subject,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,isRead,hasAttachments,body,importance');
                return ['message' => $data];

            case 'folders':
                $data = $this->graph->get("/users/{$mb}/mailFolders?\$select=displayName,id,parentFolderId,unreadItemCount,totalItemCount&\$top=100");
                return ['folders' => $data['value'] ?? []];

            case 'send':
                return $this->send($payload, $user, $mailbox, $mb);

            case 'reply':
                $messageId = $payload['messageId'] ?? null;
                $bodyContent = $payload['body'] ?? null;
                if (!$messageId || !$bodyContent) {
                    throw new FunctionException('messageId and body required', 400);
                }
                $this->graph->post("/users/{$mb}/messages/" . rawurlencode($messageId) . '/reply', ['comment' => $bodyContent]);
                InboxMessage::create([
                    'message_type' => 'email',
                    'direction' => 'outgoing',
                    'from_address' => $mailbox,
                    'to_address' => $payload['to'] ?? '',
                    'subject' => $payload['subject'] ?? 'Re:',
                    'body_text' => $bodyContent,
                    'mailbox' => $mailbox,
                    'sent_date' => now(),
                    'sent_by' => $user->full_name ?: $user->email,
                    'status' => 'ny',
                ]);
                return ['success' => true];

            case 'markRead':
                $messageId = $payload['messageId'] ?? null;
                if (!$messageId) {
                    throw new FunctionException('messageId required', 400);
                }
                $isRead = ($payload['isRead'] ?? null) !== false;
                $this->graph->patch("/users/{$mb}/messages/" . rawurlencode($messageId), ['isRead' => $isRead]);
                return ['success' => true];

            case 'delete':
                $messageId = $payload['messageId'] ?? null;
                if (!$messageId) {
                    throw new FunctionException('messageId required', 400);
                }
                $this->graph->delete("/users/{$mb}/messages/" . rawurlencode($messageId));
                return ['success' => true];

            default:
                throw new FunctionException('Unknown action', 400);
        }
    }

    private function list(array $payload, string $mailbox, string $mb): array
    {
        $folderKey = (string) ($payload['folder'] ?? 'inbox');

        if ($folderKey === 'samtalelogg') {
            $msgs = InboxMessage::where('mailbox', $mailbox)->orderByDesc('sent_date')->limit(100)->get();
            return ['messages' => $msgs->map(fn (InboxMessage $im) => $this->logToMessage($im, $mailbox, false))->values()->all()];
        }

        $folder = self::FOLDER_MAP[$folderKey] ?? null;
        if (!$folder) {
            $dispName = self::FOLDER_DISPLAY[$folderKey] ?? $folderKey;
            try {
                $folders = $this->graph->get("/users/{$mb}/mailFolders?\$select=displayName,id&\$top=200");
                foreach ($folders['value'] ?? [] as $f) {
                    if (mb_strtolower($f['displayName'] ?? '') === mb_strtolower((string) $dispName)) {
                        $folder = $f['id'];
                        break;
                    }
                }
            } catch (\Throwable) {
                $folder = null;
            }
        }
        if (!$folder) {
            return ['messages' => []];
        }

        $isSent = $folder === 'sentitems' || $folder === 'outbox';
        $isDraft = $folder === 'drafts';
        $orderBy = $isSent ? 'sentDateTime desc' : ($isDraft ? 'lastModifiedDateTime desc' : 'receivedDateTime desc');
        $dateField = $isSent ? 'sentDateTime' : ($isDraft ? 'lastModifiedDateTime' : 'receivedDateTime');

        $path = "/users/{$mb}/mailFolders/" . rawurlencode($folder) . '/messages'
            . '?$top=50&$orderby=' . rawurlencode($orderBy)
            . '&$select=id,subject,from,toRecipients,receivedDateTime,sentDateTime,lastModifiedDateTime,isRead,hasAttachments,bodyPreview,importance';
        $data = $this->graph->get($path);

        $messages = [];
        foreach ($data['value'] ?? [] as $m) {
            $m['receivedDateTime'] = $m[$dateField] ?? ($m['receivedDateTime'] ?? null);
            $messages[] = $m;
        }
        return ['messages' => $messages];
    }

    private function send(array $payload, User $user, string $mailbox, string $mb): array
    {
        $to = $payload['to'] ?? null;
        $cc = $payload['cc'] ?? null;
        $replyTo = $payload['replyTo'] ?? null;
        $subject = $payload['subject'] ?? null;
        $bodyContent = $payload['body'] ?? null;

        if (!is_array($to) || count($to) === 0) {
            throw new FunctionException('to required (array)', 400);
        }
        if (!$subject || !$bodyContent) {
            throw new FunctionException('subject and body required', 400);
        }

        $message = [
            'subject' => $subject,
            'body' => ['contentType' => 'HTML', 'content' => $bodyContent],
            'toRecipients' => array_values(array_map([$this, 'asRecipient'], $to)),
        ];
        if (is_array($cc) && $cc) {
            $message['ccRecipients'] = array_values(array_map([$this, 'asRecipient'], $cc));
        }
        if (is_array($replyTo) && $replyTo) {
            $message['replyTo'] = array_values(array_map([$this, 'asRecipient'], $replyTo));
        }

        $this->graph->post("/users/{$mb}/sendMail", ['message' => $message, 'saveToSentItems' => true]);

        InboxMessage::create([
            'message_type' => 'email',
            'direction' => 'outgoing',
            'from_address' => $mailbox,
            'to_address' => implode(', ', array_map(fn ($a) => is_string($a) ? $a : ($a['address'] ?? ''), $to)),
            'subject' => $subject,
            'body_text' => $bodyContent,
            'mailbox' => $mailbox,
            'sent_date' => now(),
            'sent_by' => $user->full_name ?: $user->email,
            'status' => 'ny',
        ]);

        return ['success' => true];
    }

    private function asRecipient(mixed $a): array
    {
        return ['emailAddress' => ['address' => is_string($a) ? $a : ($a['address'] ?? null)]];
    }

    /** Samme form som Graph-meldinger, bygd fra InboxMessage (folder 'samtalelogg'). */
    private function logToMessage(InboxMessage $im, string $mailbox, bool $full): array
    {
        $from = $im->direction === 'outgoing' ? ($im->from_address ?: $mailbox) : ($im->from_address ?: '');
        $to = $im->to_address
            ? array_map(fn ($a) => ['emailAddress' => ['address' => trim($a)]], explode(',', (string) $im->to_address))
            : [];
        $sent = $im->sent_date?->toISOString();

        $out = [
            'id' => $im->id,
            'subject' => $im->subject ?: ($im->message_type === 'sms' ? 'SMS' : '(uten emne)'),
            'from' => ['emailAddress' => ['address' => $from]],
            'toRecipients' => $to,
        ];
        if ($full) {
            $out['ccRecipients'] = [];
        }
        $out += [
            'receivedDateTime' => $sent,
            'sentDateTime' => $sent,
            'isRead' => $im->status === 'lest',
            'hasAttachments' => false,
        ];
        if ($full) {
            $out['body'] = ['contentType' => 'HTML', 'content' => $im->body_text ?: '<p class="text-slate-400">(ingen innhold)</p>'];
        } else {
            $out['bodyPreview'] = mb_substr((string) ($im->body_text ?? ''), 0, 200);
        }
        $out += [
            'importance' => 'normal',
            '_isLog' => true,
            '_messageType' => $im->message_type,
        ];
        return $out;
    }
}
