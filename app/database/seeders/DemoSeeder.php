<?php

namespace Database\Seeders;

use App\Actions\Customers\CustomerService;
use App\Actions\Finance\InvoiceService;
use App\Actions\Finance\PaymentService;
use App\Actions\Maintenance\ConsentService;
use App\Actions\Maintenance\EvaluateDuePlans;
use App\Actions\Maintenance\MaintenancePlanService;
use App\Actions\Requests\SubmitServiceRequest;
use App\Actions\Scheduling\CancelAppointment;
use App\Actions\Scheduling\CheckInVehicle;
use App\Actions\Scheduling\ConfirmAppointment;
use App\Actions\Workshop\EstimateService;
use App\Actions\Workshop\RecordEstimateDecision;
use App\Actions\Workshop\TransitionWorkOrder;
use App\Actions\Workshop\WorkshopService;
use App\Enums\ConsentPurpose;
use App\Enums\DecisionChannel;
use App\Enums\EstimateDecisionType;
use App\Enums\MediaCategory;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\StaffRole;
use App\Enums\UserType;
use App\Enums\VehicleRelationship;
use App\Enums\WorkOrderStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\EstimateRevision;
use App\Models\MediaFile;
use App\Models\OpeningRule;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ShopBay;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Support\BusinessTime;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use GdImage;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Deterministic, unmistakably fictional local demo. Every scenario is produced through the real domain
 * actions. Phones are non-deliverable (0000xxxx), plates use «ДЕМ», images are generated placeholders.
 */
class DemoSeeder extends Seeder
{
    public string $staffPassword = '';

    private User $advisor;

    private User $mechanicA;

    private User $mechanicB;

    private ShopBay $bay1;

    private ShopBay $bay2;

    /** @var array<string, Service> */
    private array $services = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo seed is allowed only in local/testing environments.');
        }

        $this->staffPassword = $this->staffPassword ?: Str::password(14, symbols: false);

        $this->catalog();
        $this->staff();

        $today = BusinessTime::today();

        // Chronological order matters: bays stay physically occupied until a job is delivered or released.
        $this->scenarioDelivered($today);
        $this->scenarioDeclined($today);
        $this->scenarioWaitingParts($today);
        $this->scenarioAwaitingApproval($today);
        $this->scenarioActiveRepair($today);
        $this->scenarioTomorrowAppointment($today);
        $this->scenarioCancelled($today);
        $this->scenarioPendingRequest();
        $this->scenarioOptOut();

        $this->travel(null);
        app(EvaluateDuePlans::class)->handle();
    }

    private function catalog(): void
    {
        foreach ([1, 2, 3, 4, 5] as $day) {
            OpeningRule::query()->firstOrCreate(['scope' => 'shop', 'weekday' => $day, 'local_start' => '09:00'], ['local_end' => '18:00', 'is_demo' => true]);
        }
        OpeningRule::query()->firstOrCreate(['scope' => 'shop', 'weekday' => 6, 'local_start' => '10:00'], ['local_end' => '15:00', 'is_demo' => true]);

        $this->bay1 = ShopBay::query()->firstOrCreate(['name' => 'Пост 1'], ['active' => true, 'sort_order' => 1]);
        $this->bay2 = ShopBay::query()->firstOrCreate(['name' => 'Пост 2'], ['active' => true, 'sort_order' => 2]);

        $catalog = [
            'oil' => ['tos-solih', 'Хөдөлгүүрийн тос солих', 'Үйлчилгээ', PriceType::From, 45000, 45, 'Тос, тосны шүүр солих. Тосны төрөл машинаас хамаарна.', 'Тос, шүүр, ажлын хөлс', 'Бусад шүүр, шингэн'],
            'brake' => ['toormos', 'Тоормосны үзлэг, засвар', 'Засвар', PriceType::InspectionRequired, null, 60, 'Дэвсгэр, диск, шингэнийг шалгаж үнийн санал гаргана.', 'Үзлэг', 'Сэлбэг тусдаа'],
            'diag' => ['hodolguur-onoshlogoo', 'Хөдөлгүүрийн оношлогоо', 'Оношлогоо', PriceType::Fixed, 30000, 45, 'Алдааны код уншиж, шинж тэмдгийг шалгана.', 'Код уншилт, тайлбар', 'Засвар тусдаа'],
            'susp' => ['yavah-ed-angi', 'Явах эд ангийн үзлэг', 'Засвар', PriceType::InspectionRequired, null, 60, 'Холбоос, амортизатор, жолооны хэсгийг шалгана.', 'Үзлэг', 'Сэлбэг тусдаа'],
            'battery' => ['akkumlyator', 'Аккумлятор шалгах', 'Үйлчилгээ', PriceType::Fixed, 10000, 20, 'Хүчдэл, цэнэглэлтийн системийг шалгана.', 'Шалгалт', 'Шинэ аккумлятор'],
        ];

        foreach ($catalog as $key => [$slug, $name, $category, $priceType, $price, $duration, $summary, $inclusions, $exclusions]) {
            $this->services[$key] = Service::query()->firstOrCreate(['slug' => $slug], [
                'name_mn' => $name, 'category' => $category, 'price_type' => $priceType, 'price_mnt' => $price,
                'estimated_duration_min' => $duration, 'summary' => $summary, 'inclusions' => $inclusions, 'exclusions' => $exclusions,
                'active' => true, 'published' => true, 'is_demo' => true, 'sort_order' => count($this->services),
            ]);
        }
    }

    private function staff(): void
    {
        $make = function (string $email, string $name, StaffRole $role, bool $bookable, array $capabilities = []): User {
            $user = User::query()->where('email', $email)->first() ?? new User;
            $user->fill(['name' => $name, 'email' => $email, 'password' => $this->staffPassword]);
            $user->forceFill(['type' => UserType::Staff, 'staff_role' => $role, 'active' => true])->save();
            $user->staffProfile()->updateOrCreate([], ['display_name' => $name, 'bookable' => $bookable, 'capabilities' => $capabilities]);

            return $user;
        };

        $this->advisor = $make('advisor@demo.local', 'Зөвлөх Номин (DEMO)', StaffRole::Advisor, false, ['qc']);
        $this->mechanicA = $make('mechanic1@demo.local', 'Механик Бат (DEMO)', StaffRole::Mechanic, true);
        $this->mechanicB = $make('mechanic2@demo.local', 'Механик Дорж (DEMO)', StaffRole::Mechanic, true);
    }

    /**
     * @return array{0: Customer, 1: Vehicle}
     */
    private function customer(string $name, string $phoneSuffix, string $plate, string $make, string $model, int $year, bool $portal = true, bool $reminders = true): array
    {
        $service = app(CustomerService::class);
        $customer = $service->createCustomer(['display_name' => $name.' (DEMO)', 'contact_phone' => '0000'.$phoneSuffix], $this->advisor);
        $customer->forceFill(['is_demo' => true])->save();

        $vehicle = $service->createVehicle(['make' => $make, 'model' => $model, 'year' => $year, 'plate' => $plate], $this->advisor);
        $vehicle->forceFill(['is_demo' => true])->save();
        $service->grantVehicleAccess($customer, $vehicle, VehicleRelationship::Owner, $this->advisor, 'demo');

        if ($portal) {
            $user = new User(['name' => $name.' (DEMO)']);
            $user->forceFill(['type' => UserType::Customer, 'phone_normalized' => $customer->normalized_contact_phone, 'phone_verified_at' => now(), 'active' => true])->save();
            $service->linkPortalUser($customer, $user, $this->advisor);
        }

        if ($reminders) {
            app(ConsentService::class)->grant($customer, ConsentPurpose::ServiceReminder, 'demo', $this->advisor->id);
        }

        return [$customer, $vehicle];
    }

    private function appointment(Customer $customer, Vehicle $vehicle, Service $service, CarbonImmutable $localStart, ShopBay $bay, User $mechanic, ?int $requestId = null)
    {
        return app(ConfirmAppointment::class)->handle([
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'service_id' => $service->id, 'type' => 'workshop',
            'start' => $localStart->utc(), 'end' => $localStart->addMinutes(60)->utc(),
            'bay_id' => $bay->id, 'mechanic_user_id' => $mechanic->id, 'service_request_id' => $requestId,
        ], $this->advisor);
    }

    private function intake(Appointment $appointment, int $km, string $complaint): WorkOrder
    {
        $order = app(CheckInVehicle::class)->fromAppointment($appointment, ['complaint' => $complaint, 'intake_km' => $km, 'fuel_level' => '1/2'], $this->advisor);
        $order->forceFill(['is_demo' => true])->save();

        return $order;
    }

    private function diagnoseAndQuote(WorkOrder $order, User $mechanic, array $lines, string $finding): EstimateRevision
    {
        app(TransitionWorkOrder::class)->handle($order, WorkOrderStatus::Diagnosing, $mechanic);
        $workshop = app(WorkshopService::class);
        $item = $workshop->saveInspectionItem($order->refresh(), ['label' => $lines[0]['description'], 'condition' => 'attention', 'finding_internal' => 'DEMO дотоод тэмдэглэл', 'finding_customer' => $finding], $mechanic);
        $photo = $this->photo($order, MediaCategory::Before, 'ӨМНӨ', $mechanic, $item->id);
        $workshop->publishInspection($order, [$item->id], [$photo->id], $this->advisor);

        $estimates = app(EstimateService::class);
        $draft = $estimates->createDraft($order->refresh(), $this->advisor);
        $estimates->saveDraft($draft, ['diagnosis_summary' => $finding, 'terms' => 'DEMO нөхцөл', 'valid_until' => BusinessTime::today()->addDays(14)->toDateString()], $lines, $this->advisor);

        return $estimates->publish($draft, $this->advisor);
    }

    private function approveByPhone(EstimateRevision $revision): void
    {
        app(RecordEstimateDecision::class)->handle($revision->refresh(), EstimateDecisionType::Approved, DecisionChannel::Phone, $this->advisor, (string) $revision->content_hash, 'DEMO: хэрэглэгч утсаар зөвшөөрсөн');
    }

    private function scenarioDelivered(CarbonImmutable $today): void
    {
        [$customer, $vehicle] = $this->customer('Болд', '0001', '0001 ДЕМ', 'Toyota', 'Prius 30', 2012);
        $day = $this->workday($today->subDays(40));
        $this->travel($day->setTime(9, 0));

        $appointment = $this->appointment($customer, $vehicle, $this->services['brake'], $day->setTime(10, 0), $this->bay1, $this->mechanicA);
        $this->travel($day->setTime(10, 5));
        $order = $this->intake($appointment, 182400, 'Тоормослоход шажигнана (DEMO)');

        $this->travel($day->setTime(10, 40));
        $revision = $this->diagnoseAndQuote($order, $this->mechanicA, [
            ['kind' => 'labor', 'description' => 'Урд тоормосны дэвсгэр солих', 'quantity' => '1', 'unit_price_mnt' => 35000],
            ['kind' => 'part', 'description' => 'Урд дэвсгэр (иж)', 'quantity' => '1', 'unit_price_mnt' => 68000],
            ['kind' => 'part', 'description' => 'Тоормосны шингэн', 'quantity' => '0.5', 'unit_price_mnt' => 25000],
        ], 'Урд дэвсгэр 2 мм хүртэл элэгдсэн (DEMO).');

        $this->travel($day->setTime(11, 0));
        $this->approveByPhone($revision);
        $this->repairAndQc($order->refresh());

        $this->travel($day->setTime(15, 30));
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->issue($invoices->createDraft($order->refresh(), $this->advisor), $this->advisor);
        $invoice->forceFill(['is_demo' => true])->save();
        app(PaymentService::class)->record($invoice, 50000, PaymentMethod::Cash, $this->advisor, 'demo-pay-1', null, now(), 'DEMO урьдчилгаа');
        $this->travel($day->setTime(17, 0));
        app(PaymentService::class)->record($invoice, $invoice->balanceMnt(), PaymentMethod::Bank, $this->advisor, 'demo-pay-2', 'DEMO-TX-0001');
        app(WorkshopService::class)->deliver($order->refresh(), $this->advisor);

        app(MaintenancePlanService::class)->createFromWorkOrder($order->refresh(), [
            'description' => 'Тоормосны шингэн, дэвсгэрийн давтан үзлэг (DEMO)',
            'due_date' => $today->subDays(10)->toDateString(),
            'due_km' => 192000,
            'service_id' => $this->services['brake']->id,
            'recommendation_source_note' => 'DEMO: механикийн зөвлөмж — бодит хуваарь биш',
        ], $this->advisor);
    }

    private function repairAndQc(WorkOrder $order): void
    {
        $workshop = app(WorkshopService::class);
        $mechanic = $order->mechanic;

        foreach ($order->currentApprovedRevision->lines as $line) {
            $workshop->recordRepair($order->refresh(), ['description' => $line->description, 'work_done' => 'DEMO: хийсэн', 'estimate_line_id' => $line->id, 'quantity_milli' => $line->quantity_milli, 'is_part' => $line->kind->value === 'part'], $mechanic);
        }

        $this->photo($order, MediaCategory::After, 'ДАРАА', $mechanic, null, visible: true);
        app(TransitionWorkOrder::class)->handle($order->refresh(), WorkOrderStatus::QualityCheck, $mechanic);
        $workshop->completeQualityCheck($order->refresh(), [
            ['item' => 'Тоормос туршсан', 'result' => 'pass'],
            ['item' => 'Шингэн алдалтгүй', 'result' => 'pass'],
        ], $this->advisor, 'DEMO: урд тоормосны дэвсгэр сольж, шингэн нэмэв.');
    }

    private function scenarioPendingRequest(): void
    {
        $this->travel(BusinessTime::now()->subHours(2));
        $file = $this->uploadedPlaceholder('ХҮСЭЛТ');

        app(SubmitServiceRequest::class)->handle([
            'request_type' => 'workshop',
            'service_id' => $this->services['susp']->id,
            'name' => 'Ганаа (DEMO)',
            'phone' => '0000 0002',
            'vehicle_text' => 'Honda Fit (DEMO)',
            'plate' => '0002 ДЕМ',
            'symptoms' => 'Овойлт дээр урд талаас тогшино. (DEMO хүсэлт)',
            'preferred_local_date' => $this->workday(BusinessTime::today()->addDays(2))->toDateString(),
            'preferred_window' => 'morning',
            'reminder_preference' => true,
        ], 'demo-session-1', 'demo-request-1', [$file]);

        ServiceRequest::query()->where('idempotency_key', 'demo-request-1')->update(['is_demo' => true]);
        MediaFile::query()->whereNotNull('service_request_id')->update(['is_demo' => true]);
    }

    private function scenarioTomorrowAppointment(CarbonImmutable $today): void
    {
        $this->travel(BusinessTime::now()->subDay());
        $request = app(SubmitServiceRequest::class)->handle([
            'request_type' => 'workshop', 'service_id' => $this->services['oil']->id, 'name' => 'Сараа (DEMO)', 'phone' => '0000 0003',
            'vehicle_text' => 'Hyundai Accent (DEMO)', 'plate' => '0003 ДЕМ', 'symptoms' => 'Тос солиулах (DEMO).', 'reminder_preference' => true,
        ], 'demo-session-2', 'demo-request-2');
        $request->forceFill(['is_demo' => true])->save();

        [$customer, $vehicle] = $this->customer('Сараа', '0003', '0003 ДЕМ', 'Hyundai', 'Accent', 2016);
        $this->appointment($customer, $vehicle, $this->services['oil'], $this->workday($today->addDay())->setTime(10, 0), $this->bay1, $this->mechanicA, $request->id);
        $this->travel(null);
    }

    private function scenarioAwaitingApproval(CarbonImmutable $today): void
    {
        [$customer, $vehicle] = $this->customer('Энхээ', '0004', '0004 ДЕМ', 'Toyota', 'Land Cruiser 100', 2005);
        $day = $this->workday($today->subDay());
        $this->travel($day->setTime(9, 0));
        $appointment = $this->appointment($customer, $vehicle, $this->services['diag'], $day->setTime(11, 0), $this->bay2, $this->mechanicB);
        $this->travel($day->setTime(11, 5));
        $order = $this->intake($appointment, 311250, 'Хөдөлгүүрийн гэрэл асаж, хүч сул (DEMO)');
        $this->travel($day->setTime(13, 0));
        $this->diagnoseAndQuote($order, $this->mechanicB, [
            ['kind' => 'labor', 'description' => 'Оношлогоо', 'quantity' => '1', 'unit_price_mnt' => 30000],
            ['kind' => 'labor', 'description' => 'Асаах лаа солих', 'quantity' => '1', 'unit_price_mnt' => 40000],
            ['kind' => 'part', 'description' => 'Асаах лаа', 'quantity' => '8', 'unit_price_mnt' => 18500],
        ], 'Асаах лаа элэгдсэн, 3-р цилиндр тасалдаж байна (DEMO).');
        $order->refresh()->forceFill(['promised_ready_at' => $today->setTime(17, 0)->utc()])->save();
    }

    private function scenarioActiveRepair(CarbonImmutable $today): void
    {
        [$customer, $vehicle] = $this->customer('Тулга', '0005', '0005 ДЕМ', 'Nissan', 'X-Trail', 2014);
        $day = $this->workday($today);
        $this->travel($day->setTime(8, 30));
        $appointment = $this->appointment($customer, $vehicle, $this->services['oil'], $day->setTime(9, 0), $this->bay1, $this->mechanicA);
        $this->travel($day->setTime(9, 5));
        $order = $this->intake($appointment, 98400, 'Тос солих, ерөнхий үзлэг (DEMO)');
        $this->travel($day->setTime(9, 30));
        $revision = $this->diagnoseAndQuote($order, $this->mechanicA, [
            ['kind' => 'labor', 'description' => 'Тос солих', 'quantity' => '1', 'unit_price_mnt' => 15000],
            ['kind' => 'part', 'description' => 'Хөдөлгүүрийн тос 5W-30', 'quantity' => '4.5', 'unit_price_mnt' => 14000],
            ['kind' => 'part', 'description' => 'Тосны шүүр', 'quantity' => '1', 'unit_price_mnt' => 12000],
        ], 'Тос солих хугацаа болсон (DEMO).');
        $this->approveByPhone($revision);
        app(TransitionWorkOrder::class)->handle($order->refresh(), WorkOrderStatus::InProgress, $this->mechanicA);
        $this->travel(null);
    }

    private function scenarioWaitingParts(CarbonImmutable $today): void
    {
        [$customer, $vehicle] = $this->customer('Мөнхөө', '0006', '0006 ДЕМ', 'Mitsubishi', 'Delica', 2008);
        $day = $this->workday($today->subDays(3));
        $this->travel($day->setTime(14, 0));
        $appointment = $this->appointment($customer, $vehicle, $this->services['susp'], $day->setTime(15, 0), $this->bay2, $this->mechanicB);
        $this->travel($day->setTime(15, 5));
        $order = $this->intake($appointment, 240100, 'Урд амортизатор гоожсон (DEMO)');
        $this->travel($day->setTime(16, 0));
        $revision = $this->diagnoseAndQuote($order, $this->mechanicB, [
            ['kind' => 'labor', 'description' => 'Урд амортизатор солих', 'quantity' => '2', 'unit_price_mnt' => 30000],
            ['kind' => 'part', 'description' => 'Урд амортизатор', 'quantity' => '2', 'unit_price_mnt' => 145000],
        ], 'Урд хоёр амортизатор гоожиж байна (DEMO).');
        $this->approveByPhone($revision);
        app(TransitionWorkOrder::class)->handle($order->refresh(), WorkOrderStatus::InProgress, $this->mechanicB);
        app(TransitionWorkOrder::class)->handle($order->refresh(), WorkOrderStatus::WaitingParts, $this->mechanicB, 'Амортизатор захиалсан, 2 хоногт ирнэ (DEMO)');
        $order->reservations()->whereNotNull('actual_occupied_from')->whereNull('actual_occupied_to')->update(['actual_occupied_to' => now(), 'release_reason' => 'waiting_parts']);
        $this->travel(null);
    }

    private function scenarioDeclined(CarbonImmutable $today): void
    {
        [$customer, $vehicle] = $this->customer('Цэцгээ', '0007', '0007 ДЕМ', 'Toyota', 'Corolla Axio', 2013);
        $day = $this->workday($today->subDays(7));
        $this->travel($day->setTime(11, 30));
        $appointment = $this->appointment($customer, $vehicle, $this->services['battery'], $day->setTime(12, 0), $this->bay1, $this->mechanicB);
        $this->travel($day->setTime(12, 5));
        $order = $this->intake($appointment, 156000, 'Өглөө асахгүй байна (DEMO)');
        $revision = $this->diagnoseAndQuote($order, $this->mechanicB, [
            ['kind' => 'part', 'description' => 'Аккумлятор 55Ah', 'quantity' => '1', 'unit_price_mnt' => 230000],
            ['kind' => 'labor', 'description' => 'Суурилуулах', 'quantity' => '1', 'unit_price_mnt' => 10000],
        ], 'Аккумлятор цэнэг барихгүй (DEMO).');
        app(RecordEstimateDecision::class)->handle($revision->refresh(), EstimateDecisionType::Declined, DecisionChannel::Phone, $this->advisor, (string) $revision->content_hash, 'DEMO: хэрэглэгч өөрөө авна гэсэн', 'Өөрөө худалдаж авна');
        app(TransitionWorkOrder::class)->handle($order->refresh(), WorkOrderStatus::Cancelled, $this->advisor, 'Хэрэглэгч татгалзсан (DEMO)');
        $this->travel(null);
    }

    private function scenarioCancelled(CarbonImmutable $today): void
    {
        [$customer, $vehicle] = $this->customer('Ариунаа', '0008', '0008 ДЕМ', 'Lexus', 'RX 350', 2010);
        $appointment = $this->appointment($customer, $vehicle, $this->services['diag'], $this->workday($today->addDays(2))->setTime(14, 0), $this->bay2, $this->mechanicA);
        app(CancelAppointment::class)->handle($appointment, 'Хэрэглэгч цуцалсан (DEMO)', $this->advisor);
    }

    private function scenarioOptOut(): void
    {
        [$customer] = $this->customer('Батаа', '0009', '0009 ДЕМ', 'Subaru', 'Forester', 2011, portal: false);
        app(ConsentService::class)->revoke($customer, ConsentPurpose::ServiceReminder, 'demo opt-out', $this->advisor->id);
    }

    private function photo(WorkOrder $order, MediaCategory $category, string $label, User $actor, ?int $itemId = null, bool $visible = false): MediaFile
    {
        $media = app(WorkshopService::class)->attachPhoto($order, $this->uploadedPlaceholder($label), $category, $actor, 'DEMO зураг — '.$label, $itemId);
        $media->forceFill(['is_demo' => true, 'customer_visible' => $visible])->save();

        return $media;
    }

    private function uploadedPlaceholder(string $label): UploadedFile
    {
        $image = $this->placeholder($label);
        $path = tempnam(sys_get_temp_dir(), 'demo').'.jpg';
        imagejpeg($image, $path, 85);

        return new UploadedFile($path, 'demo.jpg', 'image/jpeg', null, true);
    }

    /**
     * Generated placeholder (no real photo): brand-colored panel with a large "DEMO" label.
     */
    private function placeholder(string $label): GdImage
    {
        $image = imagecreatetruecolor(960, 720);
        imagefill($image, 0, 0, imagecolorallocate($image, 11, 90, 96));
        imagefilledrectangle($image, 40, 40, 920, 680, imagecolorallocate($image, 245, 243, 238));
        $ink = imagecolorallocate($image, 20, 38, 50);
        $amber = imagecolorallocate($image, 230, 170, 66);
        imagefilledrectangle($image, 40, 600, 920, 680, $amber);

        $text = 'DEMO '.Str::ascii($label);
        $scale = 8;
        $small = imagecreatetruecolor(strlen($text) * imagefontwidth(5) + 4, imagefontheight(5) + 4);
        imagefill($small, 0, 0, imagecolorallocate($small, 245, 243, 238));
        imagestring($small, 5, 2, 2, $text, imagecolorallocate($small, 20, 38, 50));
        $width = imagesx($small) * $scale;
        imagecopyresized($image, $small, (int) ((960 - $width) / 2), 260, 0, 0, $width, imagesy($small) * $scale, imagesx($small), imagesy($small));
        imagestring($image, 5, 60, 630, 'ANGAA AUTO - placeholder, not a real vehicle photo', $ink);

        return $image;
    }

    private function workday(CarbonImmutable $localDay): CarbonImmutable
    {
        $day = $localDay->startOfDay();

        while ($day->isoWeekday() >= 6) {
            $day = $day->addDay();
        }

        return $day;
    }

    private function travel(?CarbonImmutable $local): void
    {
        $instant = $local?->utc();
        Carbon::setTestNow($instant);
        CarbonImmutable::setTestNow($instant);
    }
}
