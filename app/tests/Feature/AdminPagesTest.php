<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MediaFile;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every admin screen renders for the owner against realistic demo data; roles are enforced server-side.
 */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_owner_can_open_every_admin_screen(): void
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);

        $urls = [
            '/admin', '/admin/calendar', '/admin/reports', '/admin/business-settings',
            '/admin/service-requests', '/admin/appointments', '/admin/appointments/create',
            '/admin/work-orders', '/admin/work-orders/create', '/admin/customers', '/admin/customers/create', '/admin/vehicles',
            '/admin/invoices', '/admin/payments', '/admin/maintenance-plans', '/admin/outbox',
            '/admin/services', '/admin/staff', '/admin/bays', '/admin/opening-rules', '/admin/closures', '/admin/audit',
            '/admin/service-requests/'.ServiceRequest::query()->first()->getRouteKey(),
            '/admin/customers/'.Customer::query()->first()->getRouteKey(),
            '/admin/vehicles/'.Vehicle::query()->first()->getRouteKey(),
            '/admin/invoices/'.Invoice::query()->first()->getRouteKey(),
            '/mechanic',
            '/staff-print/invoices/'.Invoice::query()->first()->public_id,
        ];

        foreach (WorkOrder::query()->get() as $order) {
            $urls[] = '/admin/work-orders/'.$order->getRouteKey();
            $urls[] = '/mechanic/ajil/'.$order->public_id;
            $urls[] = '/staff-print/work-orders/'.$order->public_id;
        }

        foreach (Appointment::query()->get() as $appointment) {
            $urls[] = '/admin/appointments/'.$appointment->getRouteKey().'/edit';
        }

        foreach ($urls as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->status(), "GET {$url} returned {$response->status()}: ".($response->exception?->getMessage() ?? '').' @ '.($response->exception ? $response->exception->getFile().':'.$response->exception->getLine() : ''));
        }
    }

    public function test_guests_are_redirected_to_the_right_sign_in_page(): void
    {
        $order = WorkOrder::query()->firstOrFail();

        $this->get('/mechanic')->assertRedirect(route('filament.admin.auth.login'));
        $this->get('/staff-print/work-orders/'.$order->public_id)->assertRedirect(route('filament.admin.auth.login'));
        $this->get('/media/'.MediaFile::query()->firstOrFail()->public_id.'/thumb')->assertRedirect(route('filament.admin.auth.login'));
        $this->get('/portal')->assertRedirect(route('portal.login'));
        $this->get('/admin')->assertRedirect();
    }

    public function test_customers_cannot_enter_the_staff_panel_or_mechanic_screens(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($customer)->get('/mechanic')->assertForbidden();
    }

    public function test_mechanic_cannot_open_finance_settings_or_other_mechanics_jobs(): void
    {
        $mechanic = User::query()->where('email', 'mechanic1@demo.local')->firstOrFail();
        $this->actingAs($mechanic);

        foreach (['/admin/invoices', '/admin/payments', '/admin/staff', '/admin/business-settings', '/admin/reports', '/admin/customers', '/admin/audit'] as $url) {
            $this->assertContains($this->get($url)->status(), [403, 404], $url);
        }

        $foreign = WorkOrder::query()->where('assigned_mechanic_id', '!=', $mechanic->id)->firstOrFail();
        $this->get('/mechanic/ajil/'.$foreign->public_id)->assertNotFound();
        $this->get('/staff-print/invoices/'.Invoice::query()->first()->public_id)->assertForbidden();

        $own = WorkOrder::query()->where('assigned_mechanic_id', $mechanic->id)->firstOrFail();
        $this->get('/mechanic/ajil/'.$own->public_id)->assertOk()->assertDontSee('₮');
    }

    public function test_advisor_cannot_manage_staff_or_business_settings(): void
    {
        $advisor = User::query()->where('email', 'advisor@demo.local')->firstOrFail();
        $this->actingAs($advisor);

        $this->get('/admin/staff')->assertForbidden();
        $this->get('/admin/business-settings')->assertForbidden();
        $this->get('/admin/work-orders')->assertOk();
    }

    public function test_inactive_staff_lose_access_immediately(): void
    {
        $advisor = User::query()->where('email', 'advisor@demo.local')->firstOrFail();
        $advisor->forceFill(['active' => false])->save();

        $this->actingAs($advisor)->get('/admin')->assertForbidden();
        $this->actingAs($advisor)->get('/mechanic')->assertForbidden();
    }
}
