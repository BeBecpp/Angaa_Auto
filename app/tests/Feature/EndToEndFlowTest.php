<?php

namespace Tests\Feature;

use App\Actions\Customers\CustomerService;
use App\Enums\AppointmentStatus;
use App\Enums\EstimateState;
use App\Enums\InvoiceStatus;
use App\Enums\OutboxStatus;
use App\Enums\PaymentState;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrder;
use App\Livewire\Mechanic\JobScreen;
use App\Livewire\Portal\EstimatePanel;
use App\Livewire\RequestWizard;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\NotificationOutbox;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsWorkshop;
use Tests\TestCase;

/**
 * 05_ACCEPTANCE "ONE COMPLETE DEMO SCRIPT", driven through the real UI components.
 */
class EndToEndFlowTest extends TestCase
{
    use BuildsWorkshop, RefreshDatabase;

    public function test_request_to_history_to_reminder_to_rebooking(): void
    {
        Storage::fake('media');
        // Time travel makes real temp-upload files look days old; Livewire would clean them up mid-test.
        config(['livewire.temporary_file_upload.cleanup' => false]);
        $this->travelTo(BusinessTime::fromLocal('2026-11-04 08:00'));
        $this->setUpWorkshop();

        // 1. Guest request with photo.
        Livewire::test(RequestWizard::class)
            ->set('symptoms', 'Тоормослоход шажигнана')
            ->set('newPhotos', [UploadedFile::fake()->image('brake.jpg', 1200, 900)])
            ->call('next')
            ->set('name', 'Бат Эрдэнэ')->set('phone', '9911 2233')->set('vehicleText', 'Toyota Prius')->set('plate', '1234 УБА')
            ->call('next')
            ->set('contactConsent', true)->set('reminderPreference', true)
            ->call('submit')
            ->assertHasNoErrors();
        $request = ServiceRequest::query()->sole();

        // 2. Advisor triages and confirms a bay/mechanic slot (creating customer + vehicle explicitly).
        $this->actingAs($this->advisor);
        Livewire::test(ViewServiceRequest::class, ['record' => $request->getRouteKey()])
            ->callAction('convert', data: [
                'customer_mode' => 'new', 'display_name' => 'Бат Эрдэнэ', 'contact_phone' => '9911 2233', 'grant_reminders' => true,
                'vehicle_mode' => 'new', 'make' => 'Toyota', 'model' => 'Prius', 'plate' => '1234 УБА', 'year' => 2012,
                'type' => 'workshop', 'date' => '2026-11-05', 'time' => '10:00', 'duration_min' => 60,
                'bay_id' => $this->bay->id, 'mechanic_user_id' => $this->mechanic->id, 'buffer_min' => 0,
            ])
            ->assertHasNoActionErrors();

        $appointment = Appointment::query()->sole();
        $customer = Customer::query()->sole();
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame('2026-11-05 10:00', BusinessTime::format($appointment->planned_start));

        // 3. Customer signs in (verified phone) and is linked by staff; another account sees nothing.
        $customerUser = User::factory()->customer('+97699112233')->create();
        app(CustomerService::class)->linkPortalUser($customer, $customerUser, $this->advisor);
        $stranger = User::factory()->customer('+97699000077')->create();
        Customer::factory()->create(['user_id' => $stranger->id, 'normalized_contact_phone' => $stranger->phone_normalized]);

        $this->actingAs($customerUser)->get('/portal')->assertOk()->assertSee('11/05 10:00');
        $this->actingAs($stranger)->get('/portal')->assertOk()->assertDontSee('11/05 10:00');

        // 4. Check-in, then mechanic diagnosis with photo on the phone screen.
        $this->travelTo(BusinessTime::fromLocal('2026-11-05 09:55'));
        $this->actingAs($this->advisor);
        Livewire::test(EditAppointment::class, ['record' => $appointment->getRouteKey()])
            ->callAction('checkin', data: ['complaint' => 'Тоормослоход шажигнана', 'intake_km' => 182400])
            ->assertHasNoActionErrors();
        $order = WorkOrder::query()->sole();

        $this->actingAs($this->mechanic);
        Livewire::test(JobScreen::class, ['workOrder' => $order])
            ->call('moveStatus', 'diagnosing')
            ->set('itemLabel', 'Урд тоормосны дэвсгэр')->set('itemCondition', 'urgent')
            ->set('itemInternal', 'Дотоод: диск 22мм')->set('itemCustomer', 'Дэвсгэр 2 мм хүртэл элэгдсэн')
            ->call('saveItem')
            ->set('photo', UploadedFile::fake()->image('pad.jpg', 1000, 800))->set('photoCategory', 'before')
            ->call('uploadPhoto')
            ->assertHasNoErrors();
        $this->assertSame(WorkOrderStatus::Diagnosing, $order->refresh()->status);

        // 5. Advisor publishes findings and estimate revision 1; customer approves it in the portal.
        $this->actingAs($this->advisor);
        $page = Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()]);
        $page->callAction('publishFindings', data: ['items' => [$order->inspectionItems()->sole()->id], 'media' => [$order->media()->sole()->id]])->assertHasNoActionErrors();
        $page->callAction('estimate', data: [
            'diagnosis_summary' => 'Урд дэвсгэр солих шаардлагатай',
            'lines' => [
                ['kind' => 'labor', 'description' => 'Дэвсгэр солих', 'quantity' => '1', 'unit_price_mnt' => 35000, 'discount_mnt' => 0],
                ['kind' => 'part', 'description' => 'Урд дэвсгэр', 'quantity' => '1', 'unit_price_mnt' => 68000, 'discount_mnt' => 0],
            ],
        ])->assertHasNoActionErrors();
        $page->callAction('publishEstimate')->assertHasNoActionErrors();

        $revision1 = $order->estimateRevisions()->sole();
        $this->assertSame(103000, $revision1->grand_total_mnt);
        $this->assertSame(WorkOrderStatus::AwaitingApproval, $order->refresh()->status);

        $this->actingAs($customerUser);
        Livewire::test(EstimatePanel::class, ['revision' => $revision1])->call('ask', 'approved')->call('decide')->assertHasNoErrors();
        $this->assertSame(WorkOrderStatus::Approved, $order->refresh()->status);

        // Mechanic starts permitted work.
        $this->actingAs($this->mechanic);
        Livewire::test(JobScreen::class, ['workOrder' => $order])->call('moveStatus', 'in_progress')->assertHasNoErrors();

        // 6. Additional work → revision 2 (revision 1 approval does not cover it); customer declines.
        $this->actingAs($this->advisor);
        Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('estimate', data: [
                'lines' => [
                    ['kind' => 'labor', 'description' => 'Дэвсгэр солих', 'quantity' => '1', 'unit_price_mnt' => 35000, 'discount_mnt' => 0],
                    ['kind' => 'part', 'description' => 'Урд дэвсгэр', 'quantity' => '1', 'unit_price_mnt' => 68000, 'discount_mnt' => 0],
                    ['kind' => 'part', 'description' => 'Тоормосны диск', 'quantity' => '2', 'unit_price_mnt' => 90000, 'discount_mnt' => 0],
                ],
            ])
            ->callAction('publishEstimate')
            ->assertHasNoActionErrors();
        $revision2 = $order->estimateRevisions()->where('revision_number', 2)->sole();
        $this->assertSame(WorkOrderStatus::AwaitingApproval, $order->refresh()->status);

        $this->actingAs($customerUser);
        Livewire::test(EstimatePanel::class, ['revision' => $revision2])->call('ask', 'declined')->set('declineReason', 'Дараа')->call('decide');
        $this->assertSame(EstimateState::Declined, $revision2->refresh()->state);
        $this->assertSame(WorkOrderStatus::Approved, $order->refresh()->status);
        $this->assertSame($revision1->id, $order->current_approved_estimate_revision_id);

        // 7. Mechanic records work + after photo; QC by advisor; ready.
        $this->actingAs($this->mechanic);
        $screen = Livewire::test(JobScreen::class, ['workOrder' => $order]);
        foreach ($revision1->lines as $line) {
            $screen->set('repairLineId', (string) $line->id)->set('repairWork', 'Хийсэн')->call('recordRepair')->assertHasNoErrors();
        }
        $screen->set('photo', UploadedFile::fake()->image('after.jpg', 800, 600))->set('photoCategory', 'after')->call('uploadPhoto')
            ->call('moveStatus', 'quality_check')->assertHasNoErrors();

        $this->actingAs($this->advisor);
        Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('qc', data: ['checks' => [['item' => 'Тоормос туршсан', 'result' => 'pass', 'note' => null]], 'customer_summary' => 'Урд дэвсгэр солив'])
            ->assertHasNoActionErrors();
        $this->assertSame(WorkOrderStatus::ReadyForPickup, $order->refresh()->status);

        // 8. Invoice, partial then final payment, delivery and printable summary.
        Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()])->callAction('invoice');
        $invoice = $order->invoices()->sole();
        $invoicePage = Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()]);
        $invoicePage->callAction('issue')->assertHasNoActionErrors();
        $invoicePage->callAction('pay', data: ['amount_mnt' => 50000, 'method' => 'cash', 'received_at' => now()->toDateTimeString()])->assertHasNoActionErrors();
        $this->assertSame(PaymentState::PartPaid, $invoice->refresh()->paymentState());
        $invoicePage->callAction('pay', data: ['amount_mnt' => 53000, 'method' => 'bank', 'reference' => 'TX-77', 'received_at' => now()->toDateTimeString()])->assertHasNoActionErrors();
        $this->assertSame(InvoiceStatus::Issued, $invoice->refresh()->status);
        $this->assertSame(0, $invoice->balanceMnt());

        Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()])->callAction('deliver', data: [])->assertHasNoActionErrors();
        $this->assertSame(WorkOrderStatus::Delivered, $order->refresh()->status);
        $this->get('/staff-print/work-orders/'.$order->public_id)->assertOk()->assertSee('Урд дэвсгэр солив');

        // 9. Customer sees own photo history; staff sets a service-specific next date/km plan.
        $this->actingAs($this->advisor);
        Livewire::test(ViewWorkOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('plan', data: ['description' => 'Тоормосны давтан үзлэг', 'due_date' => '2027-05-05', 'due_km' => 195000])
            ->assertHasNoActionErrors();

        $this->actingAs($customerUser);
        $this->get('/portal/ajil/'.$order->public_id)->assertOk()
            ->assertSee('Дэвсгэр 2 мм хүртэл элэгдсэн')->assertDontSee('Дотоод: диск 22мм')
            ->assertSee('Тоормосны давтан үзлэг');
        $photo = $order->media()->where('customer_visible', true)->first();
        $this->get('/media/'.$photo->public_id.'/full')->assertOk();
        $this->actingAs($stranger)->get('/portal/ajil/'.$order->public_id)->assertNotFound();
        $this->actingAs($stranger)->get('/media/'.$photo->public_id.'/full')->assertForbidden();

        // 10. Advance the clock, dry-run then simulate the reminder (never really sent), then rebook.
        $this->artisan('reminders:dispatch', ['--at' => '2027-04-28 10:00'])->assertSuccessful();
        $reminder = NotificationOutbox::query()->where('aggregate_type', 'maintenance_plan')->sole();
        $this->assertSame(OutboxStatus::Simulated, $reminder->status);

        $this->travelTo(BusinessTime::fromLocal('2027-04-28 11:00'));
        $this->actingAs($this->advisor);
        $this->appointmentFor($customer->refresh(), $order->vehicle, '2027-05-03 10:00');
        $this->assertSame(2, Appointment::query()->where('customer_id', $customer->id)->count());
    }
}
