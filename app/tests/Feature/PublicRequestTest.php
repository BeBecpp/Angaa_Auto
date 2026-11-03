<?php

namespace Tests\Feature;

use App\Actions\Requests\SubmitServiceRequest;
use App\Enums\RequestStatus;
use App\Livewire\RequestWizard;
use App\Models\Customer;
use App\Models\MediaFile;
use App\Models\ServiceRequest;
use App\Models\Vehicle;
use App\Support\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PublicRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'request_type' => 'workshop',
            'name' => 'Бат',
            'phone' => '+976 9911-2233',
            'vehicle_text' => 'Toyota Prius',
            'plate' => '1234 уба',
            'symptoms' => 'Тоормослоход шажигнана',
        ], $overrides);
    }

    private function photo(int $width = 800, int $height = 600): UploadedFile
    {
        return UploadedFile::fake()->image('car.jpg', $width, $height);
    }

    public function test_public_pages_render_with_brand_and_without_inventing_details(): void
    {
        foreach (['/', '/uilchilgee', '/huselt', '/holboo-barih', '/tugeemel-asuult', '/nuuts-bodlogo', '/uilchilgeenii-nohtsol', '/portal/nevtreh'] as $url) {
            $this->get($url)->assertOk()->assertSee('АНГАА АВТО', false)->assertSee('brand/web/angaa-auto-', false);
        }

        $this->get('/holboo-barih')->assertSee('Утасны дугаарыг удахгүй нийтэлнэ')->assertDontSee('24/7');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get('/sitemap.xml')->assertOk()->assertSee('<urlset', false);
    }

    public function test_guest_submits_a_pending_request_with_a_photo_through_the_wizard(): void
    {
        Livewire::test(RequestWizard::class)
            ->set('requestType', 'workshop')
            ->set('symptoms', 'Хөдөлгүүрийн гэрэл асав')
            ->set('newPhotos', [$this->photo()])
            ->call('next')
            ->assertHasNoErrors()
            ->set('name', 'Бат')
            ->set('phone', '9911 2233')
            ->set('vehicleText', 'Toyota Prius')
            ->set('plate', '1234 УБА')
            ->call('next')
            ->assertHasNoErrors()
            ->set('contactConsent', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();

        $request = ServiceRequest::query()->sole();
        $this->assertSame(RequestStatus::New, $request->status);
        $this->assertSame('+97699112233', $request->phone_normalized);
        $this->assertSame('1234УБА', $request->plate_normalized);
        $this->assertNull($request->customer_id);
        $this->assertSame(1, $request->media()->count());
        $this->assertFalse((bool) $request->media()->first()->customer_visible);
    }

    public function test_validation_errors_keep_the_entered_text(): void
    {
        Livewire::test(RequestWizard::class)
            ->set('symptoms', 'Урд талаас тогшино')
            ->call('next')
            ->set('name', 'Бат')
            ->set('phone', '12')
            ->set('vehicleText', 'Fit')
            ->call('next')
            ->assertHasErrors(['phone'])
            ->assertSet('step', 2)
            ->assertSet('name', 'Бат')
            ->assertSet('symptoms', 'Урд талаас тогшино');
    }

    public function test_double_submit_returns_the_same_request(): void
    {
        $action = app(SubmitServiceRequest::class);
        $first = $action->handle($this->payload(), 'session-a', 'key-1', [$this->photo()]);
        $second = $action->handle($this->payload(), 'session-a', 'key-1', [$this->photo()]);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ServiceRequest::query()->count());
        $this->assertSame(1, MediaFile::query()->count());
    }

    public function test_invalid_files_fail_clearly_and_create_nothing(): void
    {
        $action = app(SubmitServiceRequest::class);

        foreach ([
            [UploadedFile::fake()->create('photo.heic', 100, 'image/heic'), 'HEIC'],
            [UploadedFile::fake()->createWithContent('fake.jpg', 'not an image'), 'JPEG, PNG, WebP'],
            [$this->photo(5000, 4000), '16 мегапиксел'],
        ] as [$file, $expected]) {
            try {
                $action->handle($this->payload(), 'session-b', 'key-'.$expected, [$file]);
                $this->fail('Invalid file accepted: '.$expected);
            } catch (DomainException $exception) {
                $this->assertStringContainsString($expected, $exception->getMessage());
            }
        }

        $this->assertSame(0, ServiceRequest::query()->count());
        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_stored_photo_is_reencoded_without_exif_under_a_random_private_path(): void
    {
        $request = app(SubmitServiceRequest::class)->handle($this->payload(), 's', 'k', [$this->photo(3000, 2000)]);
        $media = $request->media()->sole();

        $this->assertStringNotContainsString('car', $media->private_path);
        $this->assertLessThanOrEqual(2400, max($media->width, $media->height));
        Storage::disk('media')->assertExists([$media->private_path, $media->preview_path]);
        $this->assertFalse(is_file(public_path('storage/'.$media->private_path)));
    }

    public function test_receipt_is_visible_only_to_the_submitting_session_and_reveals_no_history(): void
    {
        $sessionId = Str::random(40);
        $request = app(SubmitServiceRequest::class)->handle($this->payload(), $sessionId, 'k', []);

        $this->withCookie(config('session.cookie'), $sessionId)->get('/huselt/'.$request->reference)->assertOk()->assertSee($request->reference)->assertSee('цаг баталгаажаагүй');

        $this->withCookie(config('session.cookie'), Str::random(40))->get('/huselt/'.$request->reference)->assertNotFound();
    }

    public function test_unverified_phone_and_plate_never_link_to_an_existing_customer_or_vehicle(): void
    {
        $customer = Customer::factory()->create(['normalized_contact_phone' => '+97699112233']);
        Vehicle::factory()->create(['current_plate_normalized' => '1234УБА']);

        $request = app(SubmitServiceRequest::class)->handle($this->payload(), 's', 'k', []);

        $this->assertNull($request->customer_id);
        $this->assertNull($request->vehicle_id);
        $this->assertSame(0, $customer->workOrders()->count());
    }

    public function test_callout_is_rejected_unless_enabled(): void
    {
        $this->expectException(DomainException::class);
        app(SubmitServiceRequest::class)->handle($this->payload(['request_type' => 'callout', 'callout_address' => 'Дархан']), 's', 'k', []);
    }

    public function test_honeypot_submission_creates_nothing(): void
    {
        Livewire::test(RequestWizard::class)->set('website', 'spam')->call('submit')->assertRedirect(route('home'));
        $this->assertSame(0, ServiceRequest::query()->count());
    }
}
