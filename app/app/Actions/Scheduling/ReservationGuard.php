<?php

namespace App\Actions\Scheduling;

use App\Enums\AppointmentType;
use App\Models\Closure;
use App\Models\OpeningRule;
use App\Models\ResourceReservation;
use App\Models\ShopBay;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared validation for every path that reserves bays/mechanics: confirm, reschedule, drag/drop,
 * walk-in and check-in. Intervals are half-open [start, end).
 *
 * Callers MUST run inside Serialized::run('scheduling', ...) and call lockResources() before
 * checking conflicts, so that an empty slot cannot be claimed twice concurrently.
 */
class ReservationGuard
{
    /**
     * Lock stable resource rows in deterministic order (bays, then mechanics; ascending id).
     */
    public function lockResources(?int $bayId, ?int $mechanicId): void
    {
        if ($bayId !== null) {
            ShopBay::query()->whereKey($bayId)->lockForUpdate()->firstOrFail();
        }

        if ($mechanicId !== null) {
            User::query()->whereKey($mechanicId)->lockForUpdate()->firstOrFail();
        }
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} reservation interval including travel and buffer
     */
    public function reservationWindow(CarbonImmutable $start, CarbonImmutable $end, int $bufferMin, int $travelMin): array
    {
        return [$start->subMinutes($travelMin), $end->addMinutes($bufferMin + $travelMin)];
    }

    public function validateResources(AppointmentType $type, ?int $bayId, ?int $mechanicId): void
    {
        if ($mechanicId === null) {
            throw new DomainException('Механик сонгоно уу.');
        }

        if ($type === AppointmentType::Workshop && $bayId === null) {
            throw new DomainException('Сервисийн цагт пост (bay) заавал сонгоно.');
        }

        $mechanic = User::query()->whereKey($mechanicId)->first();

        if (! $mechanic || ! $mechanic->isStaff() || ! $mechanic->active || ! $mechanic->staffProfile?->bookable) {
            throw new DomainException('Сонгосон механик цаг захиалгад идэвхгүй байна.');
        }

        if ($bayId !== null && ! ShopBay::query()->whereKey($bayId)->where('active', true)->exists()) {
            throw new DomainException('Сонгосон пост идэвхгүй байна.');
        }
    }

    public function validateTimes(CarbonImmutable $start, CarbonImmutable $end, ?int $bayId, ?int $mechanicId): void
    {
        if ($end->lessThanOrEqualTo($start)) {
            throw new DomainException('Дуусах цаг эхлэх цагаас хойш байх ёстой.');
        }

        $this->assertWithinOpeningHours($start, $end);
        $this->assertNoClosure($start, $end, $bayId, $mechanicId);
    }

    /**
     * @param  list<int>  $ignoreReservationIds
     */
    public function assertNoConflict(CarbonImmutable $from, CarbonImmutable $to, ?int $bayId, ?int $mechanicId, array $ignoreReservationIds = []): void
    {
        if ($bayId !== null && $this->overlapQuery('bay_id', $bayId, $from, $to, $ignoreReservationIds)->exists()) {
            throw new SchedulingConflict('Энэ пост сонгосон цагт аль хэдийн захиалагдсан байна.');
        }

        if ($mechanicId !== null && $this->overlapQuery('mechanic_user_id', $mechanicId, $from, $to, $ignoreReservationIds)->exists()) {
            throw new SchedulingConflict('Энэ механик сонгосон цагт өөр ажилтай байна.');
        }

        if ($bayId !== null && $this->openOccupancyOverlaps($bayId, $from, $to, $ignoreReservationIds)) {
            throw new SchedulingConflict('Пост дээр машин засварт байгаа (төлөвлөсөн хугацаа хэтэрсэн). Өөр пост эсвэл цаг сонгоно уу.');
        }
    }

    /**
     * @param  list<int>  $ignore
     * @return Builder<ResourceReservation>
     */
    private function overlapQuery(string $column, int $id, CarbonImmutable $from, CarbonImmutable $to, array $ignore)
    {
        return ResourceReservation::query()
            ->where($column, $id)
            ->where('active', true)
            ->whereNotIn('id', $ignore)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from);
    }

    /**
     * An open actual occupancy (vehicle still on the bay) blocks the bay from its start until now,
     * even when the planned end has passed.
     *
     * @param  list<int>  $ignore
     */
    private function openOccupancyOverlaps(int $bayId, CarbonImmutable $from, CarbonImmutable $to, array $ignore): bool
    {
        $now = CarbonImmutable::now();

        return ResourceReservation::query()
            ->where('bay_id', $bayId)
            ->whereNotIn('id', $ignore)
            ->whereNotNull('actual_occupied_from')
            ->whereNull('actual_occupied_to')
            ->get()
            ->contains(function (ResourceReservation $reservation) use ($from, $to, $now): bool {
                $occupiedUntil = CarbonImmutable::instance($reservation->ends_at)->max($now->addMinutes(15));

                return CarbonImmutable::instance($reservation->actual_occupied_from)->lessThan($to) && $occupiedUntil->greaterThan($from);
            });
    }

    private function assertWithinOpeningHours(CarbonImmutable $start, CarbonImmutable $end): void
    {
        $localStart = BusinessTime::local($start);
        $localEnd = BusinessTime::local($end);

        if ($localStart->toDateString() !== $localEnd->subSecond()->toDateString()) {
            throw new DomainException('Цаг нэг өдрийн ажлын цагийн дотор багтах ёстой.');
        }

        $rules = OpeningRule::query()
            ->where('scope', 'shop')
            ->where('weekday', $localStart->isoWeekday())
            ->where(fn ($query) => $query->whereNull('active_from')->orWhereDate('active_from', '<=', $localStart->toDateString()))
            ->where(fn ($query) => $query->whereNull('active_until')->orWhereDate('active_until', '>=', $localStart->toDateString()))
            ->get();

        if ($rules->isEmpty()) {
            throw new DomainException('Сонгосон өдөр ажлын цаг тохируулагдаагүй (амралтын өдөр) байна.');
        }

        $fits = $rules->contains(function (OpeningRule $rule) use ($localStart, $localEnd): bool {
            $open = $localStart->setTimeFromTimeString($rule->local_start);
            $close = $localStart->setTimeFromTimeString($rule->local_end);

            return $localStart->greaterThanOrEqualTo($open) && $localEnd->lessThanOrEqualTo($close);
        });

        if (! $fits) {
            throw new DomainException('Сонгосон цаг ажлын цагаас гадуур байна.');
        }
    }

    private function assertNoClosure(CarbonImmutable $start, CarbonImmutable $end, ?int $bayId, ?int $mechanicId): void
    {
        $closure = Closure::query()
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->where(function ($query) use ($bayId, $mechanicId): void {
                $query->where('scope', 'shop');

                if ($bayId !== null) {
                    $query->orWhere(fn ($inner) => $inner->where('scope', 'bay')->where('scope_id', $bayId));
                }

                if ($mechanicId !== null) {
                    $query->orWhere(fn ($inner) => $inner->where('scope', 'mechanic')->where('scope_id', $mechanicId));
                }
            })
            ->first();

        if ($closure) {
            throw new DomainException('Энэ хугацаанд хаалттай / чөлөөтэй: '.$closure->reason);
        }
    }
}
