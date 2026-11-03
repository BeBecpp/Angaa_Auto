<?php

namespace Tests\Feature;

use App\Actions\Auth\OtpService;
use App\Actions\Customers\CustomerService;
use App\Actions\Workshop\TransitionWorkOrder;
use App\Actions\Workshop\WorkshopService;
use App\Enums\MediaCategory;
use App\Enums\VehicleRelationship;
use App\Enums\WorkOrderStatus;
use App\Livewire\Portal\PortalLogin;
use App\Models\Customer;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\BuildsWorkshop;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use BuildsWorkshop, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        $this->travelTo(BusinessTime::fromLocal('2026-11-04 08:00'));
        $this->setUpWorkshop();
    }

    public function test_otp_login_is_session_bound_hashed_and_attempt_capped(): void
    {
        $otp = app(OtpService::class);
        $otp->request('9911 2233', 'session-1', '127.0.0.1');
        $challenge = OtpChallenge::query()->sole();

        $this->assertNotSame($challenge->local_preview_code, $challenge->hashed_code);
        $this->assertNull($otp->localPreview('other-session'));
        $code = $otp->localPreview('session-1');

        try {
            $otp->verify('9911 2233', $code, 'other-session');
            $this->fail('Code must be bound to its session');
        } catch (DomainException) {
        }

        foreach (range(1, 5) as $attempt) {
            try {
                $otp->verify('9911 2233', $code === '000000' ? '111111' : '000000', 'session-1');
            } catch (DomainException) {
            }
        }

        $this->expectException(DomainException::class);
        $otp->verify('9911 2233', $code, 'session-1');
    }

    public function test_customer_can_sign_in_through_the_portal_form(): void
    {
        $component = Livewire::test(PortalLogin::class)->set('phone', '9911 2233')->call('sendCode')->assertSet('codeSent', true);
        $code = OtpChallenge::query()->sole()->local_preview_code;

        $component->set('code', $code)->call('verify')->assertRedirect(route('portal.home'));

        $user = User::query()->where('phone_normalized', '+97699112233')->sole();
        $this->assertTrue($user->isCustomer());
        $this->assertNotNull($user->phone_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_first_sign_in_does_not_claim_existing_customers_with_the_same_phone(): void
    {
        Customer::factory()->create(['normalized_contact_phone' => '+97699112233']);
        $otp = app(OtpService::class);
        $otp->request('99112233', 's', 'ip');
        $user = $otp->verify('99112233', OtpChallenge::query()->sole()->local_preview_code, 's');

        $this->actingAs($user)->get('/portal')->assertOk()->assertSee('ажилтан таны бүртгэлийг');
        $this->assertNull(Customer::query()->where('user_id', $user->id)->first());
    }

    public function test_staff_phone_identity_cannot_sign_in_via_customer_otp(): void
    {
        $this->owner->forceFill(['phone_normalized' => '+97688112233'])->save();
        $otp = app(OtpService::class);
        $otp->request('88112233', 's', 'ip');

        $this->expectException(DomainException::class);
        $otp->verify('88112233', OtpChallenge::query()->sole()->local_preview_code, 's');
    }

    public function test_customer_a_cannot_see_customer_b_jobs_photos_or_prints(): void
    {
        $userA = User::factory()->customer('+97699000001')->create();
        $userB = User::factory()->customer('+97699000002')->create();
        [$customerA, $vehicleA] = $this->customerWithVehicle($userA);
        [$customerB, $vehicleB] = $this->customerWithVehicle($userB);

        $orderB = $this->checkedInOrder($customerB, $vehicleB);
        $photoB = app(WorkshopService::class)->attachPhoto($orderB, UploadedFile::fake()->image('b.jpg'), MediaCategory::Before, $this->advisor);
        $photoB->forceFill(['customer_visible' => true])->save();

        $this->actingAs($userB)->get('/portal/ajil/'.$orderB->public_id)->assertOk();
        $this->actingAs($userB)->get('/media/'.$photoB->public_id.'/thumb')->assertOk();

        $this->actingAs($userA)->get('/portal/ajil/'.$orderB->public_id)->assertNotFound();
        $this->actingAs($userA)->get('/portal/ajil/'.$orderB->public_id.'/hevleh')->assertNotFound();
        $this->actingAs($userA)->get('/portal/mashin/'.$vehicleB->public_id)->assertNotFound();
        $this->actingAs($userA)->get('/media/'.$photoB->public_id.'/full')->assertForbidden();
        $this->actingAs($userA)->get('/staff-print/work-orders/'.$orderB->public_id)->assertForbidden();
    }

    public function test_internal_photos_and_findings_stay_hidden_until_published(): void
    {
        $user = User::factory()->customer()->create();
        [$customer, $vehicle] = $this->customerWithVehicle($user);
        $order = $this->checkedInOrder($customer, $vehicle);
        $workshop = app(WorkshopService::class);
        $photo = $workshop->attachPhoto($order, UploadedFile::fake()->image('a.jpg'), MediaCategory::Before, $this->advisor);
        $item = $workshop->saveInspectionItem($order, ['label' => 'Тоормос', 'condition' => 'urgent', 'finding_internal' => 'НУУЦ ТЭМДЭГЛЭЛ', 'finding_customer' => 'Дэвсгэр элэгдсэн'], $this->advisor);

        $this->actingAs($user)->get('/media/'.$photo->public_id.'/thumb')->assertForbidden();
        $this->actingAs($user)->get('/portal/ajil/'.$order->public_id)->assertDontSee('Дэвсгэр элэгдсэн')->assertDontSee('НУУЦ ТЭМДЭГЛЭЛ');

        $workshop->publishInspection($order, [$item->id], [$photo->id], $this->advisor);

        $this->actingAs($user)->get('/media/'.$photo->public_id.'/thumb')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->actingAs($user)->get('/portal/ajil/'.$order->public_id)->assertSee('Дэвсгэр элэгдсэн')->assertDontSee('НУУЦ ТЭМДЭГЛЭЛ');
    }

    public function test_new_owner_does_not_see_previous_owner_history_and_former_owner_does_not_see_new_jobs(): void
    {
        $oldUser = User::factory()->customer()->create();
        $newUser = User::factory()->customer()->create();
        [$oldCustomer, $vehicle] = $this->customerWithVehicle($oldUser);
        $oldOrder = $this->checkedInOrder($oldCustomer, $vehicle);
        app(TransitionWorkOrder::class)->handle($oldOrder, WorkOrderStatus::Cancelled, $this->advisor, 'Тест: машин гарсан');

        $service = app(CustomerService::class);
        $service->endVehicleAccess($oldCustomer->vehicleAccesses()->sole(), 'Зарсан', $this->advisor);
        $newCustomer = Customer::factory()->create(['user_id' => $newUser->id, 'normalized_contact_phone' => $newUser->phone_normalized]);
        $service->grantVehicleAccess($newCustomer, $vehicle, VehicleRelationship::Owner, $this->advisor);

        $this->travelTo(BusinessTime::fromLocal('2026-11-05 08:00'));
        $newOrder = $this->checkedInOrder($newCustomer, $vehicle);

        $this->actingAs($newUser)->get('/portal/mashin/'.$vehicle->public_id)->assertOk()->assertSee($newOrder->order_number)->assertDontSee($oldOrder->order_number);
        $this->actingAs($newUser)->get('/portal/ajil/'.$oldOrder->public_id)->assertNotFound();

        $this->actingAs($oldUser)->get('/portal/mashin/'.$vehicle->public_id)->assertOk()->assertSee($oldOrder->order_number)->assertDontSee($newOrder->order_number);
        $this->actingAs($oldUser)->get('/portal/ajil/'.$newOrder->public_id)->assertNotFound();
    }

    public function test_staff_session_is_not_a_portal_session(): void
    {
        $this->actingAs($this->advisor)->get('/portal')->assertForbidden();

        auth()->logout();
        $this->get('/portal/tsag')->assertRedirect(route('portal.login'));
    }

    public function test_linking_a_portal_account_requires_matching_verified_phone(): void
    {
        $user = User::factory()->customer('+97699000009')->create();
        $customer = Customer::factory()->create(['normalized_contact_phone' => '+97699000008']);

        $this->expectException(DomainException::class);
        app(CustomerService::class)->linkPortalUser($customer, $user, $this->advisor);
    }
}
