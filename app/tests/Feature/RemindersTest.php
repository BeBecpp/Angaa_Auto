<?php

namespace Tests\Feature;

use App\Actions\Customers\CustomerService;
use App\Actions\Maintenance\ConsentService;
use App\Actions\Maintenance\DispatchOutbox;
use App\Actions\Maintenance\EvaluateDuePlans;
use App\Actions\Maintenance\MaintenancePlanService;
use App\Actions\Scheduling\RescheduleAppointment;
use App\Actions\Vehicles\RecordOdometerReading;
use App\Contracts\SmsProvider;
use App\Enums\ConsentPurpose;
use App\Enums\OutboxStatus;
use App\Enums\PlanStatus;
use App\Models\Customer;
use App\Models\MaintenancePlan;
use App\Models\NotificationOutbox;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Messaging\SmsResult;
use App\Support\BusinessTime;
use App\Support\DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\BuildsWorkshop;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use BuildsWorkshop, RefreshDatabase;

    private Customer $customer;

    private Vehicle $vehicle;

    private WorkOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(BusinessTime::fromLocal('2026-11-04 08:00'));
        $this->setUpWorkshop();
        [$this->customer, $this->vehicle] = $this->customerWithVehicle();
        app(ConsentService::class)->grant($this->customer, ConsentPurpose::ServiceReminder, 'test');
        $this->order = $this->checkedInOrder($this->customer, $this->vehicle);
    }

    private function plan(?string $dueDate, ?int $dueKm): MaintenancePlan
    {
        return app(MaintenancePlanService::class)->createFromWorkOrder($this->order, ['description' => 'Тос солих', 'due_date' => $dueDate, 'due_km' => $dueKm], $this->advisor);
    }

    /**
     * @return Builder<NotificationOutbox>
     */
    private function planMessages()
    {
        return NotificationOutbox::query()->where('aggregate_type', 'maintenance_plan');
    }

    private function evaluateAt(string $local): array
    {
        return app(EvaluateDuePlans::class)->handle(BusinessTime::fromLocal($local));
    }

    private function dispatchAt(string $local): array
    {
        return app(DispatchOutbox::class)->dispatchDue(BusinessTime::fromLocal($local));
    }

    public function test_plan_requires_date_or_km_and_has_no_default_interval(): void
    {
        $this->expectException(DomainException::class);
        $this->plan(null, null);
    }

    public function test_date_only_plan_triggers_by_date_and_rerun_creates_one_message_per_stage(): void
    {
        $this->plan('2026-12-10', null);

        $this->assertSame([], $this->evaluateAt('2026-12-01 10:00'));
        $this->assertCount(1, $this->evaluateAt('2026-12-03 10:00'));
        $this->evaluateAt('2026-12-03 11:00');
        $this->evaluateAt('2026-12-04 10:00');

        $this->assertSame(1, $this->planMessages()->count());
        $this->assertSame('date_m7', $this->planMessages()->sole()->lifecycle_stage);
    }

    public function test_stale_stages_are_never_sent_together(): void
    {
        $this->plan('2026-12-10', null);

        $this->evaluateAt('2026-12-18 12:00');

        $this->assertSame(['date_p7'], $this->planMessages()->pluck('lifecycle_stage')->all());
    }

    public function test_km_only_plan_needs_a_credible_reading_and_unknown_mileage_never_triggers(): void
    {
        $plan = $this->plan(null, 105000);

        $this->assertSame([], $this->evaluateAt('2026-11-20 10:00'));

        $this->travelTo(BusinessTime::fromLocal('2026-11-20 09:00'));
        app(RecordOdometerReading::class)->handle($this->vehicle, 106000, $this->advisor);

        $this->assertSame(['km_due'], array_column($this->evaluateAt('2026-11-20 10:00'), 'stage'));
        $this->assertSame(105000, $plan->due_km);
    }

    public function test_reading_older_than_the_plan_baseline_cannot_mark_a_new_plan_due(): void
    {
        $this->travelTo(BusinessTime::fromLocal('2026-11-04 08:30'));
        $plan = $this->plan(null, 110000);

        app(RecordOdometerReading::class)->handle($this->vehicle, 120000, $this->advisor, BusinessTime::fromLocal('2026-10-01 10:00'));

        $this->assertSame([], $this->evaluateAt('2026-11-10 10:00'));
        $this->assertSame(100000, app(EvaluateDuePlans::class)->credibleReading($plan->refresh(), CarbonImmutable::now())->km);
    }

    public function test_decreasing_km_requires_an_authorized_correction(): void
    {
        $odometer = app(RecordOdometerReading::class);

        try {
            $odometer->handle($this->vehicle, 90000, $this->advisor);
            $this->fail('Lower km must be rejected without a correction note');
        } catch (DomainException) {
        }

        try {
            $odometer->handle($this->vehicle, 90000, $this->mechanic, null, 'Буруу уншсан');
            $this->fail('Mechanic cannot correct km down');
        } catch (DomainException) {
        }

        $reading = $odometer->handle($this->vehicle, 90000, $this->advisor, null, 'Өмнө нь буруу бичсэн');
        $this->assertNotNull($reading->correction_of_id);
        $this->assertSame(90000, $this->vehicle->latestVerifiedReading()->km);
        $this->assertDatabaseHas('audit_events', ['action' => 'odometer.corrected']);
    }

    public function test_plan_with_both_date_and_km_triggers_on_whichever_comes_first(): void
    {
        $this->plan('2027-05-01', 105000);
        $this->travelTo(BusinessTime::fromLocal('2026-12-01 09:00'));
        app(RecordOdometerReading::class)->handle($this->vehicle, 105500, $this->advisor);

        $this->assertSame(['km_due'], array_column($this->evaluateAt('2026-12-01 10:00'), 'stage'));
    }

    public function test_two_workers_cannot_claim_the_same_outbox_entry(): void
    {
        $this->plan('2026-12-10', null);
        $this->evaluateAt('2026-12-03 10:00');
        $id = $this->planMessages()->sole()->id;

        $dispatch = app(DispatchOutbox::class);
        $now = BusinessTime::fromLocal('2026-12-03 10:05');

        $this->assertNotNull($dispatch->claim($id, $now));
        $this->assertNull($dispatch->claim($id, $now));
    }

    public function test_simulated_driver_stays_simulated_and_logs_no_body(): void
    {
        $this->plan('2026-12-10', null);
        $this->evaluateAt('2026-12-03 10:00');

        $counts = $this->dispatchAt('2026-12-03 10:05');

        $message = $this->planMessages()->sole();
        $this->assertSame(['simulated' => 1], $counts);
        $this->assertSame(OutboxStatus::Simulated, $message->status);
        $this->assertNull($message->delivered_at);
        $this->assertSame('simulated', $message->attempts()->sole()->status);
    }

    public function test_consent_revoked_before_dispatch_skips_the_queued_message(): void
    {
        $this->plan('2026-12-10', null);
        $this->evaluateAt('2026-12-03 10:00');
        $this->planMessages()->update(['status' => OutboxStatus::Queued]);

        app(ConsentService::class)->revoke($this->customer, ConsentPurpose::ServiceReminder, 'portal');
        $this->planMessages()->update(['status' => OutboxStatus::Queued]);
        $this->dispatchAt('2026-12-03 10:05');

        $this->assertSame(OutboxStatus::Skipped, $this->planMessages()->sole()->status);
        $this->assertSame('no_consent', $this->planMessages()->sole()->skip_reason);
    }

    public function test_changed_phone_and_ownership_invalidate_old_messages(): void
    {
        $this->plan('2026-12-10', null);
        $this->evaluateAt('2026-12-03 10:00');

        app(CustomerService::class)->changeContactPhone($this->customer, '9900 1122', $this->advisor);
        $this->assertSame(OutboxStatus::Cancelled, $this->planMessages()->sole()->status);

        $this->assertSame([], array_filter($this->evaluateAt('2026-12-04 10:00'), fn ($row) => $row['reason'] !== 'not_eligible'));

        app(ConsentService::class)->grant($this->customer->refresh(), ConsentPurpose::ServiceReminder, 'new phone');
        app(CustomerService::class)->endVehicleAccess($this->customer->vehicleAccesses()->sole(), 'Зарсан', $this->advisor);

        $this->assertSame([], array_filter($this->evaluateAt('2026-12-05 10:00'), fn ($row) => $row['reason'] !== 'not_eligible'));
    }

    public function test_quiet_hours_shift_in_business_timezone_and_skip_when_no_longer_useful(): void
    {
        $this->plan('2026-12-10', null);
        $this->evaluateAt('2026-12-03 10:00');
        $this->planMessages()->update(['scheduled_for' => BusinessTime::fromLocal('2026-12-03 21:00')]);

        $this->dispatchAt('2026-12-03 21:30');
        $message = $this->planMessages()->sole();
        $this->assertSame(OutboxStatus::Scheduled, $message->status);
        $this->assertSame('2026-12-04 09:00', BusinessTime::format($message->scheduled_for));

        $message->forceFill(['scheduled_for' => BusinessTime::fromLocal('2026-12-04 07:00'), 'expires_at' => BusinessTime::fromLocal('2026-12-04 08:00')])->save();
        $this->dispatchAt('2026-12-04 07:30');
        $this->assertSame(OutboxStatus::Skipped, $message->refresh()->status);
    }

    public function test_booking_inside_24h_skips_the_past_stage_and_reschedule_invalidates_old_version(): void
    {
        $this->travelTo(BusinessTime::fromLocal('2026-11-04 12:00'));
        $appointment = $this->appointmentFor($this->customer, $this->vehicle, '2026-11-05 10:00');

        $stages = NotificationOutbox::query()->where('aggregate_type', 'appointment')->where('aggregate_id', $appointment->id)->pluck('lifecycle_stage')->all();
        $this->assertSame(['before_2h'], $stages);
        $this->assertSame('2026-11-05 09:00', BusinessTime::format(NotificationOutbox::query()->where('aggregate_id', $appointment->id)->sole()->scheduled_for));

        app(RescheduleAppointment::class)->handle($appointment, BusinessTime::fromLocal('2026-11-06 15:00'), BusinessTime::fromLocal('2026-11-06 16:00'), $this->bay->id, $this->mechanic->id, 1, $this->advisor);

        $rows = NotificationOutbox::query()->where('aggregate_id', $appointment->id)->get();
        $this->assertSame(OutboxStatus::Cancelled, $rows->firstWhere('aggregate_version', 1)->status);
        $this->assertEqualsCanonicalizing(['before_24h', 'before_2h'], $rows->where('aggregate_version', 2)->pluck('lifecycle_stage')->all());
    }

    public function test_provider_acceptance_is_not_delivery_and_timeout_is_unknown_without_retry(): void
    {
        $this->plan('2026-12-10', null);
        $this->evaluateAt('2026-12-03 10:00');

        $this->app->instance(SmsProvider::class, new class implements SmsProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function send(string $destination, string $body, string $idempotencyKey): SmsResult
            {
                throw new RuntimeException('timeout');
            }
        });

        app(DispatchOutbox::class)->dispatchDue(BusinessTime::fromLocal('2026-12-03 10:05'));
        $message = $this->planMessages()->sole();
        $this->assertSame(OutboxStatus::Unknown, $message->status);

        app(DispatchOutbox::class)->dispatchDue(BusinessTime::fromLocal('2026-12-03 11:00'));
        $this->assertSame(1, $message->attempts()->count());

        $message->forceFill(['status' => OutboxStatus::Scheduled])->save();
        $this->app->instance(SmsProvider::class, new class implements SmsProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function send(string $destination, string $body, string $idempotencyKey): SmsResult
            {
                return new SmsResult(SmsResult::ACCEPTED, 'prov-1');
            }
        });

        app(DispatchOutbox::class)->dispatchDue(BusinessTime::fromLocal('2026-12-03 11:05'));
        $this->assertSame(OutboxStatus::ProviderAccepted, $message->refresh()->status);
        $this->assertNull($message->delivered_at);
    }

    public function test_known_failure_backs_off_and_satisfied_plan_cancels_future_reminders(): void
    {
        $plan = $this->plan('2026-12-10', null);
        $this->evaluateAt('2026-12-03 10:00');

        $this->app->instance(SmsProvider::class, new class implements SmsProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function send(string $destination, string $body, string $idempotencyKey): SmsResult
            {
                return SmsResult::failure('rate_limited', retryable: true);
            }
        });

        app(DispatchOutbox::class)->dispatchDue(BusinessTime::fromLocal('2026-12-03 10:05'));
        $message = $this->planMessages()->sole();
        $this->assertSame(OutboxStatus::Scheduled, $message->status);
        $this->assertTrue($message->next_attempt_at->greaterThan(BusinessTime::fromLocal('2026-12-03 10:05')));

        app(MaintenancePlanService::class)->changeStatus($plan, PlanStatus::Satisfied, $this->advisor);
        $this->assertSame(OutboxStatus::Cancelled, $message->refresh()->status);
    }

    public function test_no_messages_without_consent_and_rebooking_cancels_service_nudges(): void
    {
        $this->plan('2026-12-10', null);
        $this->evaluateAt('2026-12-03 10:00');
        $this->assertSame(OutboxStatus::Scheduled, $this->planMessages()->sole()->status);

        $this->travelTo(BusinessTime::fromLocal('2026-12-03 11:00'));
        $this->appointmentFor($this->customer, $this->vehicle, '2026-12-08 10:00');

        $this->assertSame(OutboxStatus::Cancelled, NotificationOutbox::query()->where('aggregate_type', 'maintenance_plan')->sole()->status);
        $this->assertSame('rebooked', NotificationOutbox::query()->where('aggregate_type', 'maintenance_plan')->sole()->skip_reason);
    }

    public function test_dry_run_command_writes_and_sends_nothing(): void
    {
        $this->plan('2026-12-10', null);

        $this->artisan('reminders:dispatch', ['--dry-run' => true, '--at' => '2026-12-03 10:00'])->assertSuccessful();
        $this->assertSame(0, NotificationOutbox::query()->where('aggregate_type', 'maintenance_plan')->count());

        $this->artisan('reminders:dispatch', ['--at' => '2026-12-03 10:00'])->assertSuccessful()->expectsOutputToContain('бодитоор илгээгээгүй');
        $this->assertSame(OutboxStatus::Simulated, NotificationOutbox::query()->where('aggregate_type', 'maintenance_plan')->sole()->status);
    }
}
