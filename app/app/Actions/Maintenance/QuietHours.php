<?php

namespace App\Actions\Maintenance;

use App\Support\BusinessTime;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * Quiet hours in the business timezone (default 20:00–09:00 Asia/Ulaanbaatar).
 */
class QuietHours
{
    public function isQuiet(CarbonImmutable $instant): bool
    {
        $local = BusinessTime::local($instant);
        [$start, $end] = $this->bounds($local);

        return $start->greaterThan($end)
            ? ($local->greaterThanOrEqualTo($start) || $local->lessThan($end))
            : ($local->greaterThanOrEqualTo($start) && $local->lessThan($end));
    }

    /**
     * Shift an instant to the next permissible send time (returned in UTC).
     */
    public function nextAllowed(CarbonImmutable $instant): CarbonImmutable
    {
        if (! $this->isQuiet($instant)) {
            return $instant->utc();
        }

        $local = BusinessTime::local($instant);
        [, $end] = $this->bounds($local);

        $candidate = $local->lessThan($end) ? $end : $end->addDay();

        return $candidate->utc();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function bounds(CarbonImmutable $local): array
    {
        $start = $local->setTimeFromTimeString((string) (Settings::get('quiet_start') ?? '20:00'));
        $end = $local->setTimeFromTimeString((string) (Settings::get('quiet_end') ?? '09:00'));

        return [$start, $end];
    }
}
