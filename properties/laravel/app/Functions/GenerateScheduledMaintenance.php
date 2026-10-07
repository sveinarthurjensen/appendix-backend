<?php

namespace App\Functions;

use App\Models\MaintenanceTask;
use App\Models\ServiceSchedule;
use App\Models\User;
use Carbon\Carbon;

/**
 * Portert fra base44/functions/generateScheduledMaintenance/entry.ts
 *
 * Planlagt jobb (workflow «Generer serviceplan-oppgaver», cron 0 4 * * * UTC): oppretter
 * MaintenanceTask for ServiceSchedule med auto_generate=true som har forfalt (minus lead_days),
 * og beregner neste forfall ut fra frequency. Kan også kjøres manuelt av admin.
 * Svar: {success, is_automation, evaluated, due_found, tasks_created, schedules_updated,
 *        created_task_ids, updated_schedule_ids}
 */
class GenerateScheduledMaintenance extends Base44Function
{
    public function __invoke(?User $user, array $payload): array
    {
        $isAutomation = $user === null;
        if ($user) {
            $this->requireAdmin($user);
        }

        $todayStr = now('UTC')->toDateString();

        $schedules = ServiceSchedule::orderByDesc('created_date')->limit(500)->get();

        $due = $schedules->filter(function (ServiceSchedule $s) use ($todayStr) {
            if (!$s->auto_generate) {
                return false;
            }
            if (in_array($s->status, ['kansellert', 'utført'], true)) {
                return false;
            }
            if (!$s->frequency || $s->frequency === 'engangs') {
                return false;
            }
            $nextDue = $this->toDateOnly($s->next_due_date) ?? $this->toDateOnly($s->scheduled_date);
            if (!$nextDue) {
                return false;
            }
            $lead = (int) ($s->lead_days ?: 0);
            $triggerStr = Carbon::parse($nextDue)->subDays($lead)->toDateString();
            if ($triggerStr > $todayStr) {
                return false;
            }
            $lastGen = $this->toDateOnly($s->last_generated_date);
            if ($lastGen && $lastGen >= $nextDue) {
                return false; // allerede generert for dette forfallet
            }
            return true;
        });

        $created = [];
        $updated = [];

        foreach ($due as $s) {
            $nextDue = $this->toDateOnly($s->next_due_date) ?? $this->toDateOnly($s->scheduled_date);

            $task = MaintenanceTask::create([
                'property_id' => $s->property_id,
                'service_schedule_id' => $s->id,
                'service_partner_id' => $s->service_partner_id ?: null,
                'staff_id' => $s->default_staff_id ?: null,
                'source' => 'service_schedule',
                'title' => $s->title,
                'description' => $s->description ?: "Automatisk generert fra serviceplan ({$s->type}).",
                'category' => $s->type === 'inspeksjon' ? 'inspeksjon' : 'vedlikehold',
                'priority' => $s->default_priority ?: 'medium',
                'status' => 'planlagt',
                'due_date' => $nextDue,
                'scheduled_date' => $nextDue,
                'estimated_cost' => $s->cost ?: null,
                'notes' => $s->notes ?: null,
            ]);
            $created[] = $task->id;

            $s->update([
                'last_generated_date' => now(),
                'next_due_date' => $this->addInterval($nextDue, $s->frequency),
            ]);
            $updated[] = $s->id;
        }

        return [
            'success' => true,
            'is_automation' => $isAutomation,
            'evaluated' => $schedules->count(),
            'due_found' => $due->count(),
            'tasks_created' => count($created),
            'schedules_updated' => count($updated),
            'created_task_ids' => $created,
            'updated_schedule_ids' => $updated,
        ];
    }

    /** Frequency → neste forfall (Y-m-d). null for engangs/ukjent. Måneds-overflyt som i JS setMonth. */
    private function addInterval(string $date, ?string $frequency): ?string
    {
        $d = Carbon::parse($date);
        return match ($frequency) {
            'ukentlig' => $d->addDays(7)->toDateString(),
            'månedlig' => $d->addMonths(1)->toDateString(),
            'kvartalsvis' => $d->addMonths(3)->toDateString(),
            'halvårlig' => $d->addMonths(6)->toDateString(),
            'årlig' => $d->addYears(1)->toDateString(),
            'hvert_2_år' => $d->addYears(2)->toDateString(),
            'hvert_5_år' => $d->addYears(5)->toDateString(),
            default => null,
        };
    }

    private function toDateOnly(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
