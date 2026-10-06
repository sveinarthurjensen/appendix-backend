<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/** Enkel e-post med HTML-kropp – erstatter integrations.Core.SendEmail. Husk: Exchange legger på signatur selv. */
class PlainMail extends Mailable
{
    public function __construct(public string $subjectLine, public string $html, public ?string $replyToAddress = null) {}

    public function build(): static
    {
        $m = $this->subject($this->subjectLine)->html($this->html);
        if ($this->replyToAddress) {
            $m->replyTo($this->replyToAddress);
        }
        return $m;
    }
}
