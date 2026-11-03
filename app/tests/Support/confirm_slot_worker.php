<?php

/*
 * Test worker: an independent PHP process with its own DB connection that tries to confirm the same
 * empty slot as a sibling process. Prints OK, CONFLICT or ERROR:<message>.
 *
 * Usage: php confirm_slot_worker.php <go-at-unix-microtime>
 */

use App\Actions\Scheduling\ConfirmAppointment;
use App\Actions\Scheduling\SchedulingConflict;
use App\Models\Customer;
use App\Models\ShopBay;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$goAt = (float) ($argv[1] ?? microtime(true));
$advisor = User::query()->where('staff_role', 'advisor')->firstOrFail();
$mechanic = User::query()->where('staff_role', 'mechanic')->firstOrFail();
$customer = Customer::query()->firstOrFail();
$vehicle = $customer->currentVehicles()->firstOrFail();
$bay = ShopBay::query()->firstOrFail();

while (microtime(true) < $goAt) {
    usleep(500);
}

try {
    app(ConfirmAppointment::class)->handle([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'start' => BusinessTime::fromLocal('2030-01-07 10:00'),
        'end' => BusinessTime::fromLocal('2030-01-07 11:00'),
        'bay_id' => $bay->id,
        'mechanic_user_id' => $mechanic->id,
    ], $advisor);
    echo 'OK';
} catch (SchedulingConflict) {
    echo 'CONFLICT';
} catch (Throwable $exception) {
    echo 'ERROR:'.$exception->getMessage();
}
