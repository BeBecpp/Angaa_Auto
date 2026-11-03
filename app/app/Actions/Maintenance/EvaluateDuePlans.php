<?php

namespace App\Actions\Maintenance;

use App\Enums\ConsentPurpose;
use App\Enums\PlanStatus;
use App\Models\MaintenancePlan;
use App\Models\OdometerReading;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * Date-OR-km evaluation of active maintenance plans in the business timezone.
 *
 * - Date stages (default −7, 0, +7 days) are created only once they are reached, and only the stage
 *   whose window contains "now": stale earlier stages are never sent together.
 * - Km due requires a verified reading observed on/after the plan baseline and not older than the
 *   configured maximum age. Unknown mileage never invents a trigger.
 */
class EvaluateDuePlans
{
    public function __construct(private OutboxWriter $writer, private ConsentService $consents, private QuietHours $quietHours) {}

    /**
     * @return list<array{plan_id: int, stage: string, scheduled_for: string, reason: string}>
     */
    public function handle(?CarbonImmutable $now = null, bool $dryRun = false): array
    {
        $now ??= CarbonImmutable::now();
        $results = [];

        MaintenancePlan::query()
            ->where('status', PlanStatus::Active)
            ->with(['customer', 'vehicle', 'basedOnReading'])
            ->chunkById(200, function ($plans) use ($now, $dryRun, &$results): void {
                foreach ($plans as $plan) {
                    foreach ($this->dueStages($plan, $now) as $stage) {
                        if (! $this->eligible($plan)) {
                            $results[] = ['plan_id' => $plan->id, 'stage' => $stage['stage'], 'scheduled_for' => '', 'reason' => 'not_eligible'];

                            continue;
                        }

                        $sendAt = $this->quietHours->nextAllowed($stage['at']->max($now));
                        $results[] = ['plan_id' => $plan->id, 'stage' => $stage['stage'], 'scheduled_for' => $sendAt->toIso8601String(), 'reason' => $stage['reason']];

                        if (! $dryRun) {
                            $this->writer->schedule('maintenance_plan', $plan->id, $plan->version, $plan->customer, 'service_due', $stage['stage'], $sendAt, [
                                'vehicle' => $plan->vehicle->current_plate_display ?: $plan->vehicle->make,
                                'service' => mb_substr($plan->description, 0, 40),
                            ], $stage['until']);
                        }
                    }
                }
            });

        return $results;
    }

    /**
     * @return list<array{stage: string, at: CarbonImmutable, until: CarbonImmutable, reason: string}>
     */
    public function dueStages(MaintenancePlan $plan, CarbonImmutable $now): array
    {
        $stages = [];

        if ($plan->due_date) {
            $offsets = config('angaa.reminders.service_offsets_days');
            sort($offsets);
            $dueLocal = CarbonImmutable::parse($plan->due_date->toDateString().' 10:00', BusinessTime::zone());

            foreach ($offsets as $index => $offset) {
                $at = $dueLocal->addDays((int) $offset)->utc();
                $until = isset($offsets[$index + 1]) ? $dueLocal->addDays((int) $offsets[$index + 1])->utc() : $at->addDays(7);

                if ($now->greaterThanOrEqualTo($at) && $now->lessThan($until)) {
                    $stages[] = ['stage' => 'date_'.($offset < 0 ? 'm' : 'p').abs((int) $offset), 'at' => $at, 'until' => $until, 'reason' => 'date'];
                }
            }
        }

        if ($plan->due_km) {
            $reading = $this->credibleReading($plan, $now);

            if ($reading && $reading->km >= $plan->due_km) {
                $at = CarbonImmutable::instance($reading->observed_at)->max(CarbonImmutable::instance($plan->created_at));
                $stages[] = ['stage' => 'km_due', 'at' => $at, 'until' => $now->addDays(7), 'reason' => 'km:'.$reading->km];
            }
        }

        return $stages;
    }

    /**
     * Latest verified reading taken on/after the plan baseline and recent enough to trust; otherwise null (unknown).
     */
    public function credibleReading(MaintenancePlan $plan, CarbonImmutable $now): ?OdometerReading
    {
        $reading = $plan->vehicle->latestVerifiedReading();

        if ($reading === null) {
            return null;
        }

        $baseline = $plan->basedOnReading?->observed_at ?? $plan->created_at;

        if ($reading->observed_at->lessThan($baseline)) {
            return null;
        }

        if ($reading->observed_at->lessThan($now->subDays((int) config('angaa.reminders.mileage_max_age_days')))) {
            return null;
        }

        return $reading;
    }

    /**
     * Customer still has current access to the vehicle and service-reminder consent on the current phone.
     */
    public function eligible(MaintenancePlan $plan): bool
    {
        $hasAccess = $plan->vehicle->accesses()
            ->where('customer_id', $plan->customer_id)
            ->whereNull('ends_at')
            ->exists();

        return $hasAccess && $this->consents->has($plan->customer, ConsentPurpose::ServiceReminder);
    }
}
