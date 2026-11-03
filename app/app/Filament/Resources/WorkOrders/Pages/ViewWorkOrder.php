<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Actions\Finance\InvoiceService;
use App\Actions\Maintenance\CancelPendingNotifications;
use App\Actions\Maintenance\MaintenancePlanService;
use App\Actions\Workshop\EstimateService;
use App\Actions\Workshop\RecordEstimateDecision;
use App\Actions\Workshop\TransitionWorkOrder;
use App\Actions\Workshop\WorkshopService;
use App\Enums\DecisionChannel;
use App\Enums\EstimateDecisionType;
use App\Enums\EstimateLineKind;
use App\Enums\EstimateState;
use App\Enums\PlanStatus;
use App\Enums\QcResult;
use App\Enums\WorkOrderStatus;
use App\Filament\Concerns\RefreshesRecordAfterActions;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Filament\Support\Notify;
use App\Filament\Support\ScheduleFields;
use App\Models\EstimateRevision;
use App\Models\Service;
use App\Models\WorkOrder;
use App\Support\Audit;
use App\Support\EnumValue;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;

class ViewWorkOrder extends ViewRecord
{
    use RefreshesRecordAfterActions;

    protected static string $resource = WorkOrderResource::class;

    public function getTitle(): string
    {
        return $this->record->order_number;
    }

    protected function getHeaderActions(): array
    {
        $order = fn (): WorkOrder => $this->record->refresh();
        $is = fn (WorkOrderStatus ...$statuses): bool => in_array($this->record->status, $statuses, true);

        return [
            Action::make('diagnose')->label('Онош эхлүүлэх')->icon('heroicon-o-magnifying-glass')
                ->visible(fn (): bool => $is(WorkOrderStatus::Received))
                ->action(fn () => Notify::attempt(fn () => app(TransitionWorkOrder::class)->handle($order(), WorkOrderStatus::Diagnosing, auth()->user()), 'Онош эхэллээ')),

            $this->estimateEditor(),

            Action::make('publishEstimate')->label('Үнийн санал илгээх')->icon('heroicon-o-paper-airplane')->color('primary')
                ->visible(fn (): bool => $this->draft() !== null)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Хувилбар #'.$this->draft()?->revision_number.', нийт '.Money::format($this->draft()?->grand_total_mnt).'. Илгээсний дараа өөрчлөх боломжгүй; хэрэглэгч энэ хувилбарыг зөвшөөрнө.')
                ->action(fn () => Notify::attempt(fn () => app(EstimateService::class)->publish($this->draft(), auth()->user()), 'Үнийн санал илгээгдлээ — хэрэглэгчийн зөвшөөрөл хүлээж байна')),

            Action::make('phoneDecision')->label('Утсаар/биечлэн хариу бүртгэх')->icon('heroicon-o-phone')->color('gray')
                ->visible(fn (): bool => $this->pending() !== null)
                ->schema([
                    Placeholder::make('which')->label('Хувилбар')->content(fn (): string => '#'.$this->pending()?->revision_number.' — '.Money::format($this->pending()?->grand_total_mnt)),
                    Radio::make('decision')->label('Хэрэглэгчийн хариу')->options(EstimateDecisionType::class)->required(),
                    Radio::make('channel')->label('Суваг')->options([DecisionChannel::Phone->value => DecisionChannel::Phone->getLabel(), DecisionChannel::InPerson->value => DecisionChannel::InPerson->getLabel()])->default('phone')->required(),
                    Textarea::make('note')->label('Хэзээ, хэнтэй, юу ярьсан (ажилтны баталгаа)')->required(),
                    TextInput::make('reason')->label('Татгалзсан шалтгаан'),
                ])
                ->modalDescription('Энэ нь хэрэглэгчийн цахим гарын үсэг биш — ажилтны бүртгэсэн зөвшөөрөл болж хадгалагдана.')
                ->action(function (array $data): void {
                    $pending = $this->pending();
                    Notify::attempt(fn () => app(RecordEstimateDecision::class)->handle(
                        $pending,
                        EstimateDecisionType::from(EnumValue::of($data['decision'])),
                        DecisionChannel::from(EnumValue::of($data['channel'])),
                        auth()->user(),
                        (string) $pending->content_hash,
                        $data['note'],
                        $data['reason'] ?? null,
                    ), 'Хариу бүртгэгдлээ');
                }),

            Action::make('startWork')->label('Засвар эхлүүлэх')->icon('heroicon-o-play')
                ->visible(fn (): bool => $is(WorkOrderStatus::Approved))
                ->action(fn () => Notify::attempt(fn () => app(TransitionWorkOrder::class)->handle($order(), WorkOrderStatus::InProgress, auth()->user()), 'Засвар эхэллээ')),

            Action::make('toQc')->label('Чанарын шалгалтад')->icon('heroicon-o-clipboard-document-check')
                ->visible(fn (): bool => $is(WorkOrderStatus::InProgress))
                ->requiresConfirmation()
                ->action(fn () => Notify::attempt(fn () => app(TransitionWorkOrder::class)->handle($order(), WorkOrderStatus::QualityCheck, auth()->user()), 'Чанарын шалгалтад шилжлээ')),

            Action::make('qc')->label('Чанарын шалгалт бөглөх')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (): bool => $is(WorkOrderStatus::QualityCheck))
                ->modalWidth(Width::ThreeExtraLarge)
                ->fillForm(fn (): array => ['checks' => collect(['Засварласан хэсэг ажиллаж байна', 'Шингэн алдалтгүй', 'Туршилтын жолоодлого', 'Багаж, хог хаягдал авсан', 'Анхааруулах гэрэл унтарсан'])->map(fn (string $item): array => ['item' => $item, 'result' => 'pass'])->all()])
                ->schema([
                    Repeater::make('checks')->label('Шалгах зүйлс')->schema([
                        TextInput::make('item')->label('Зүйл')->required(),
                        Select::make('result')->label('Үр дүн')->options(QcResult::class)->required(),
                        TextInput::make('note')->label('Тэмдэглэл'),
                    ])->columns(3)->minItems(1),
                    Textarea::make('customer_summary')->label('Хэрэглэгчид зориулсан товч тайлбар')->default(fn (): ?string => $this->record->customer_summary),
                ])
                ->action(fn (array $data) => Notify::attempt(fn () => app(WorkshopService::class)->completeQualityCheck($order(), array_values($data['checks']), auth()->user(), $data['customer_summary'] ?? null), 'Чанарын шалгалт бүртгэгдлээ')),

            Action::make('invoice')->label('Нэхэмжлэл')->icon('heroicon-o-document-text')->color('gray')
                ->visible(fn (): bool => $this->record->current_approved_estimate_revision_id !== null)
                ->action(function (): void {
                    $invoice = Notify::attempt(fn () => app(InvoiceService::class)->createDraft($this->record, auth()->user()), 'Нэхэмжлэл нээгдлээ');
                    $this->redirect(InvoiceResource::getUrl('view', ['record' => $invoice]));
                }),

            Action::make('deliver')->label('Машин олгох')->icon('heroicon-o-truck')->color('success')
                ->visible(fn (): bool => $is(WorkOrderStatus::ReadyForPickup))
                ->schema([
                    Placeholder::make('balance')->label('Төлбөрийн төлөв')->content(function (): string {
                        $invoice = $this->record->invoices()->where('status', 'issued')->latest('id')->first();

                        return $invoice ? 'Үлдэгдэл '.Money::format($invoice->balanceMnt()).' ('.$invoice->paymentState()->getLabel().')' : 'Нэхэмжлэл гаргаагүй';
                    }),
                    Textarea::make('unpaid_reason')->label('Үлдэгдэлтэй олгох бол эзний шалтгаан (зээл)'),
                ])
                ->action(fn (array $data) => Notify::attempt(fn () => app(WorkshopService::class)->deliver($order(), auth()->user(), $data['unpaid_reason'] ?? null), 'Машин олгогдлоо')),

            $this->planAction(),

            ActionGroup::make([
                Action::make('publishFindings')->label('Дүгнэлт/зураг хэрэглэгчид нийтлэх')->icon('heroicon-o-eye')
                    ->schema([
                        CheckboxList::make('items')->label('Үзлэгийн мөр (хэрэглэгчид зориулсан тайлбартай)')
                            ->options(fn (): array => $this->record->inspectionItems()->whereNull('published_at')->whereNotNull('finding_customer')->pluck('label', 'id')->all()),
                        CheckboxList::make('media')->label('Зураг')
                            ->options(fn (): array => $this->record->media()->where('customer_visible', false)->get()->mapWithKeys(fn ($media): array => [$media->id => $media->category->getLabel().($media->caption ? ' — '.$media->caption : '').' #'.$media->id])->all()),
                    ])
                    ->action(fn (array $data) => Notify::attempt(fn () => app(WorkshopService::class)->publishInspection($this->record, array_map('intval', $data['items'] ?? []), array_map('intval', $data['media'] ?? []), auth()->user()), 'Нийтэллээ')),
                Action::make('mechanicScreen')->label('Механикийн дэлгэц (үзлэг, зураг)')->icon('heroicon-o-device-phone-mobile')->url(fn (): string => route('mechanic.job', $this->record)),
                Action::make('assign')->label('Механик/хугацаа')->icon('heroicon-o-user')
                    ->fillForm(fn (): array => ['assigned_mechanic_id' => $this->record->assigned_mechanic_id, 'promised_ready_at' => $this->record->promised_ready_at, 'warranty_terms' => $this->record->warranty_terms])
                    ->schema([
                        Select::make('assigned_mechanic_id')->label('Механик')->options(fn (): array => ScheduleFields::mechanics()),
                        DateTimePicker::make('promised_ready_at')->label('Амласан хугацаа')->timezone('Asia/Ulaanbaatar')->seconds(false),
                        TextInput::make('warranty_terms')->label('Баталгааны нөхцөл (бичгээр тохирсон)'),
                    ])
                    ->action(function (array $data): void {
                        $this->record->forceFill($data)->save();
                        Audit::record('work_order.assignment', $this->record, ['mechanic' => $data['assigned_mechanic_id']]);
                    }),
                Action::make('waitParts')->label('Сэлбэг хүлээх')->icon('heroicon-o-pause')
                    ->visible(fn (): bool => $is(WorkOrderStatus::InProgress))
                    ->schema([TextInput::make('reason')->label('Ямар сэлбэг, хэзээ')->required()])
                    ->action(fn (array $data) => Notify::attempt(fn () => app(TransitionWorkOrder::class)->handle($order(), WorkOrderStatus::WaitingParts, auth()->user(), $data['reason']), 'Сэлбэг хүлээх төлөвт')),
                Action::make('resume')->label('Сэлбэг ирсэн — үргэлжлүүлэх')->icon('heroicon-o-play')
                    ->visible(fn (): bool => $is(WorkOrderStatus::WaitingParts))
                    ->action(fn () => Notify::attempt(fn () => app(TransitionWorkOrder::class)->handle($order(), $this->record->status_before_wait ?? WorkOrderStatus::InProgress, auth()->user()), 'Үргэлжиллээ')),
                Action::make('override')->label('Зөвшөөрөлгүй ажил эхлүүлэх (эзэн)')->icon('heroicon-o-shield-exclamation')->color('warning')
                    ->visible(fn (): bool => auth()->user()->isOwner() && $this->record->owner_override_at === null && $this->record->status->isOpen())
                    ->modalDescription('Хэрэглэгчийн зөвшөөрөлгүйгээр ажил эхлүүлэх онцгой шийдвэр. Шалтгаан аудитад бичигдэнэ; хэрэглэгчийн зөвшөөрөл гэж тооцогдохгүй.')
                    ->schema([Textarea::make('reason')->label('Шалтгаан')->required()])
                    ->action(fn (array $data) => Notify::attempt(fn () => app(WorkshopService::class)->ownerOverride($order(), $data['reason'], auth()->user()), 'Онцгой зөвшөөрөл бүртгэгдлээ')),
                Action::make('rework')->label('Дахин засварт буцаах')->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (): bool => $is(WorkOrderStatus::ReadyForPickup, WorkOrderStatus::QualityCheck))
                    ->schema([Textarea::make('reason')->label('Шалтгаан')->required()])
                    ->action(fn (array $data) => Notify::attempt(fn () => app(TransitionWorkOrder::class)->handle($order(), WorkOrderStatus::InProgress, auth()->user(), $data['reason']), 'Засварт буцлаа')),
                Action::make('print')->label('Тайлан хэвлэх')->icon('heroicon-o-printer')->url(fn (): string => route('print.work-order', $this->record))->openUrlInNewTab(),
                Action::make('cancel')->label('Ажил цуцлах')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (): bool => $this->record->status->canTransitionTo(WorkOrderStatus::Cancelled) || $is(WorkOrderStatus::WaitingParts))
                    ->schema([Textarea::make('reason')->label('Шалтгаан')->required()])
                    ->action(fn (array $data) => Notify::attempt(fn () => app(TransitionWorkOrder::class)->handle($order(), WorkOrderStatus::Cancelled, auth()->user(), $data['reason']), 'Ажил цуцлагдлаа')),
            ])->label('Бусад')->button()->color('gray'),
        ];
    }

    private function estimateEditor(): Action
    {
        return Action::make('estimate')
            ->label(fn (): string => $this->draft() ? 'Үнийн санал засах (#'.$this->draft()->revision_number.')' : 'Шинэ үнийн санал')
            ->icon('heroicon-o-calculator')
            ->color('gray')
            ->visible(fn (): bool => in_array($this->record->status, [WorkOrderStatus::Diagnosing, WorkOrderStatus::AwaitingApproval, WorkOrderStatus::Approved, WorkOrderStatus::InProgress, WorkOrderStatus::WaitingParts], true))
            ->modalWidth(Width::FiveExtraLarge)
            ->modalDescription('Нийт дүнг сервер тооцно (нэг мөрөнд нэг удаа бүхэл төгрөгт дугуйлна). Илгээсэн хувилбар өөрчлөгдөхгүй; нэмэлт ажил бол шинэ хувилбар.')
            ->fillForm(function (): array {
                $draft = $this->draft() ?? app(EstimateService::class)->createDraft($this->record, auth()->user());

                return [
                    'diagnosis_summary' => $draft->diagnosis_summary,
                    'terms' => $draft->terms,
                    'exclusions' => $draft->exclusions,
                    'valid_until' => $draft->valid_until?->toDateString(),
                    'lines' => $draft->lines()->orderBy('sort_order')->get()->map(fn ($line): array => [
                        'kind' => $line->kind->value,
                        'description' => $line->description,
                        'quantity' => Money::milliToDisplay($line->quantity_milli),
                        'unit_price_mnt' => $line->unit_price_mnt,
                        'discount_mnt' => $line->discount_mnt,
                    ])->all(),
                ];
            })
            ->schema([
                Textarea::make('diagnosis_summary')->label('Оношийн тайлбар (хэрэглэгчид)')->rows(3),
                Repeater::make('lines')->label('Мөрүүд')->schema([
                    Select::make('kind')->label('Төрөл')->options(EstimateLineKind::class)->required()->default('labor'),
                    TextInput::make('description')->label('Тайлбар')->required()->columnSpan(2),
                    TextInput::make('quantity')->label('Тоо')->required()->default('1')->regex('/^\d+([.,]\d{1,3})?$/'),
                    TextInput::make('unit_price_mnt')->label('Нэгж үнэ ₮')->integer()->minValue(0)->required(),
                    TextInput::make('discount_mnt')->label('Хөнгөлөлт ₮')->integer()->minValue(0)->default(0),
                ])->columns(6)->minItems(1)->reorderable()->addActionLabel('Мөр нэмэх'),
                Grid::make(3)->schema([
                    Textarea::make('exclusions')->label('Багтаагүй зүйлс')->rows(2),
                    Textarea::make('terms')->label('Нөхцөл')->rows(2),
                    DatePicker::make('valid_until')->label('Хүчинтэй хугацаа')->native(false)->firstDayOfWeek(1),
                ]),
            ])
            ->action(function (array $data): void {
                $draft = $this->draft() ?? app(EstimateService::class)->createDraft($this->record, auth()->user());
                Notify::attempt(fn () => app(EstimateService::class)->saveDraft($draft, $data, array_values($data['lines'] ?? []), auth()->user()), 'Ноорог хадгалагдлаа — «Үнийн санал илгээх»-ээр хэрэглэгчид гаргана');
            });
    }

    private function planAction(): Action
    {
        return Action::make('plan')->label('Дараагийн үйлчилгээ')->icon('heroicon-o-bell-alert')->color('gray')
            ->visible(fn (): bool => in_array($this->record->status, [WorkOrderStatus::InProgress, WorkOrderStatus::QualityCheck, WorkOrderStatus::ReadyForPickup, WorkOrderStatus::Delivered], true))
            ->modalDescription('Тухайн машин, үйлчилгээнд тохирох огноо ЭСВЭЛ км (аль түрүүлж). Ерөнхий 5000 км/6 сар гэж бүү тавь.')
            ->schema([
                Select::make('service_id')->label('Үйлчилгээ')->options(fn (): array => Service::query()->pluck('name_mn', 'id')->all()),
                TextInput::make('description')->label('Юу хийх')->required(),
                Grid::make(2)->schema([
                    DatePicker::make('due_date')->label('Огноо')->native(false)->firstDayOfWeek(1),
                    TextInput::make('due_km')->label('Км')->integer()->minValue(1),
                ]),
                Textarea::make('recommendation_source_note')->label('Зөвлөмжийн үндэслэл (үйлдвэрийн заавар, ашиглалт г.м.)'),
                Select::make('supersedes_id')->label('Хуучин төлөвлөгөөг солих')
                    ->options(fn (): array => $this->record->vehicle->maintenancePlans()->where('customer_id', $this->record->customer_id)->where('status', PlanStatus::Active)->pluck('description', 'id')->all()),
                Select::make('satisfies_id')->label('Энэ ажлаар хийгдсэн идэвхтэй төлөвлөгөө')
                    ->options(fn (): array => $this->record->vehicle->maintenancePlans()->where('customer_id', $this->record->customer_id)->where('status', PlanStatus::Active)->pluck('description', 'id')->all()),
            ])
            ->action(function (array $data): void {
                Notify::attempt(function () use ($data) {
                    if (! empty($data['satisfies_id'])) {
                        app(WorkshopService::class)->satisfiesPlan($this->record, (int) $data['satisfies_id'], auth()->user());
                        app(CancelPendingNotifications::class)->forPlan($this->record->vehicle->maintenancePlans()->findOrFail((int) $data['satisfies_id']), 'plan_satisfied');
                    }

                    return app(MaintenancePlanService::class)->createFromWorkOrder($this->record, $data, auth()->user());
                }, 'Дараагийн үйлчилгээ төлөвлөгдлөө');
            });
    }

    private function draft(): ?EstimateRevision
    {
        return $this->record->estimateRevisions()->where('state', EstimateState::Draft)->first();
    }

    private function pending(): ?EstimateRevision
    {
        return $this->record->estimateRevisions()->where('state', EstimateState::Sent)->orderByDesc('revision_number')->first();
    }
}
