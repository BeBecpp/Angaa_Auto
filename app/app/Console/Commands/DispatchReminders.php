<?php

namespace App\Console\Commands;

use App\Actions\Maintenance\DispatchOutbox;
use App\Actions\Maintenance\EvaluateDuePlans;
use App\Contracts\SmsProvider;
use App\Enums\OutboxStatus;
use App\Models\NotificationOutbox;
use App\Models\SystemHeartbeat;
use App\Support\BusinessTime;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Evaluates maintenance plans into the outbox and dispatches due messages through the configured
 * SmsProvider. --dry-run writes nothing and sends nothing. --at lets you simulate a clock locally.
 */
#[Signature('reminders:dispatch {--dry-run : Show what would be scheduled/sent without writing} {--at= : Pretend the current time is this business-local datetime (local/testing only)}')]
#[Description('Evaluate due maintenance plans and dispatch due reminder messages')]
class DispatchReminders extends Command
{
    public function handle(EvaluateDuePlans $evaluate, DispatchOutbox $dispatch, SmsProvider $sms): int
    {
        $now = CarbonImmutable::now();

        if ($this->option('at')) {
            if (! app()->environment(['local', 'testing'])) {
                $this->error('--at зөвхөн local/testing орчинд.');

                return self::FAILURE;
            }

            $now = BusinessTime::fromLocal((string) $this->option('at'));
        }

        $this->line('Цаг (УБ): '.BusinessTime::format($now).' · SMS: '.$sms->name().($sms->isAvailable() ? '' : ' (идэвхгүй)'));

        $planned = $evaluate->handle($now, (bool) $this->option('dry-run'));
        $this->table(['Төлөвлөгөө', 'Үе', 'Илгээх цаг', 'Шалтгаан'], array_map(fn (array $row): array => [$row['plan_id'], $row['stage'], $row['scheduled_for'] ? BusinessTime::format(CarbonImmutable::parse($row['scheduled_for'])) : '—', $row['reason']], $planned));

        if ($this->option('dry-run')) {
            $due = NotificationOutbox::query()->whereIn('status', [OutboxStatus::Scheduled, OutboxStatus::Queued])->where('scheduled_for', '<=', $now)->get();
            $this->info('DRY RUN — юу ч бичээгүй, илгээгээгүй. Хугацаа болсон мессеж: '.$due->count());

            foreach ($due as $message) {
                $skip = $dispatch->skipReason($message, $now);
                $this->line(sprintf('  #%d %s → %s : %s', $message->id, $message->template_key, Phone::mask($message->recipient_phone), $skip ? 'алгасна ('.$skip.')' : 'илгээнэ: '.$dispatch->render($message)));
            }

            return self::SUCCESS;
        }

        $counts = $dispatch->dispatchDue($now);
        SystemHeartbeat::beat('reminders');

        $this->info('Үр дүн: '.(collect($counts)->map(fn (int $count, string $status): string => $status.'='.$count)->join(', ') ?: 'илгээх зүйл алга'));

        if (($counts[OutboxStatus::Simulated->value] ?? 0) > 0) {
            $this->warn('Туршилтын сануулга — бодитоор илгээгээгүй.');
        }

        return self::SUCCESS;
    }
}
