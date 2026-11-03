<?php

namespace App\Actions\Vehicles;

use App\Models\OdometerReading;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Audit;
use App\Support\DomainException;
use Carbon\CarbonInterface;

/**
 * Appends a dated odometer reading. Earlier readings are never replaced. A reading lower than the
 * latest verified one is rejected unless an owner/advisor records it as an explicit correction.
 */
class RecordOdometerReading
{
    public function handle(Vehicle $vehicle, int $km, User $actor, ?CarbonInterface $observedAt = null, ?string $correctionNote = null, ?int $workOrderId = null): OdometerReading
    {
        if ($km < 0 || $km > 3_000_000) {
            throw new DomainException('Гүйлтийн км буруу байна.');
        }

        $latest = $vehicle->latestVerifiedReading();
        $correctionOf = null;

        if ($latest && $km < $latest->km) {
            if ($correctionNote === null || trim($correctionNote) === '') {
                throw new DomainException("Км өмнөх бүртгэлээс ({$latest->km} км) бага байна. Залруулга бол шалтгааныг бичнэ үү.");
            }

            if (! $actor->isOfficeStaff()) {
                throw new DomainException('Км буурсан залруулгыг зөвхөн эзэн эсвэл зөвлөх хийнэ.');
            }

            $correctionOf = $latest->id;
        }

        $reading = OdometerReading::query()->create([
            'vehicle_id' => $vehicle->id,
            'km' => $km,
            'observed_at' => $observedAt ?? now(),
            'source' => 'staff',
            'verification_state' => 'verified',
            'recorded_by' => $actor->id,
            'correction_of_id' => $correctionOf,
            'work_order_id' => $workOrderId,
            'note' => $correctionNote,
        ]);

        if ($correctionOf) {
            Audit::record('odometer.corrected', $reading, ['previous_km' => $latest->km, 'new_km' => $km], $correctionNote, $actor->id);
        }

        return $reading;
    }
}
