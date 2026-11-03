<?php

namespace Tests\Feature;

use App\Actions\Customers\CustomerService;
use App\Enums\VehicleRelationship;
use App\Models\Customer;
use App\Models\OpeningRule;
use App\Models\ShopBay;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Two independent OS processes (separate DB connections) confirm the same empty bay/mechanic slot at the
 * same moment against a file-based SQLite database. Exactly one may succeed.
 *
 * This verifies the local SQLite strategy only. The MySQL release target must be verified with the
 * same test against MySQL 8.4 (see docs/RUNBOOK.md); it is not executed here when MySQL is unavailable.
 */
class ConcurrentBookingTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/concurrency-'.uniqid().'.sqlite');
        @mkdir(dirname($this->databasePath), 0755, true);
        touch($this->databasePath);

        config(['database.connections.concurrency' => array_merge(config('database.connections.sqlite'), ['database' => $this->databasePath])]);
        DB::setDefaultConnection('concurrency');
        Artisan::call('migrate', ['--database' => 'concurrency', '--force' => true]);

        $advisor = User::factory()->advisor()->create();
        User::factory()->mechanic()->create();
        ShopBay::factory()->create();
        OpeningRule::query()->create(['scope' => 'shop', 'weekday' => 1, 'local_start' => '09:00', 'local_end' => '18:00']);
        $customer = Customer::factory()->create();
        app(CustomerService::class)->grantVehicleAccess($customer, Vehicle::factory()->create(), VehicleRelationship::Owner, $advisor);

        DB::disconnect('concurrency');
    }

    protected function tearDown(): void
    {
        DB::disconnect('concurrency');
        foreach ([$this->databasePath, $this->databasePath.'-wal', $this->databasePath.'-shm'] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_two_processes_cannot_both_reserve_the_same_empty_slot(): void
    {
        $php = (new PhpExecutableFinder)->find(false);
        $goAt = sprintf('%.6F', microtime(true) + 2.0);
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $this->databasePath,
            'DB_URL' => '',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];

        $processes = [];
        foreach ([1, 2] as $worker) {
            $process = new Process([$php, base_path('tests/Support/confirm_slot_worker.php'), $goAt], base_path(), $env, null, 60);
            $process->start();
            $processes[] = $process;
        }

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $results[] = trim($process->getOutput()).trim($process->getErrorOutput());
        }

        sort($results);
        $this->assertSame(['CONFLICT', 'OK'], $results, 'Results: '.implode(' | ', $results));

        DB::reconnect('concurrency');
        $this->assertSame(2, DB::connection('concurrency')->table('resource_reservations')->where('active', true)->count());
        $this->assertSame(1, DB::connection('concurrency')->table('appointments')->count());
    }
}
