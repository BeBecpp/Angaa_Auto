<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Actions\Customers\CustomerService;
use App\Actions\Maintenance\ConsentService;
use App\Actions\Scheduling\ConfirmAppointment;
use App\Enums\ConsentPurpose;
use App\Enums\RequestStatus;
use App\Enums\VehicleRelationship;
use App\Filament\Concerns\RefreshesRecordAfterActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Filament\Support\Notify;
use App\Filament\Support\ScheduleFields;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Audit;
use App\Support\DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;

/**
 * Triage. Conversion requires staff to explicitly pick or create the customer and vehicle; nothing
 * is linked automatically from the unverified phone/plate the guest typed.
 */
class ViewServiceRequest extends ViewRecord
{
    use RefreshesRecordAfterActions;

    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('convert')
                ->label('Цаг баталгаажуулах')
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->visible(fn (): bool => $this->record->converted_at === null && ! in_array($this->record->status, [RequestStatus::Closed, RequestStatus::Spam], true))
                ->modalWidth('4xl')
                ->fillForm(fn (): array => [
                    'customer_mode' => 'new',
                    'display_name' => $this->record->name,
                    'contact_phone' => $this->record->phone_display,
                    'vehicle_mode' => 'new',
                    'make' => $this->record->vehicle_text,
                    'plate' => $this->record->plate_display,
                    'year' => $this->record->vehicle_year,
                    'type' => $this->record->request_type->value === 'callout' ? 'callout' : 'workshop',
                    'date' => $this->record->preferred_local_date?->toDateString(),
                    'grant_reminders' => $this->record->reminder_preference,
                ])
                ->schema([
                    Section::make('Үйлчлүүлэгч')->description('Утас/дугаар таарсан ч автоматаар холбохгүй. Хүнтэй ярьж шалгаад сонгоно уу.')->schema([
                        Radio::make('customer_mode')->label('')->options(['new' => 'Шинэ үйлчлүүлэгч', 'existing' => 'Бүртгэлтэй үйлчлүүлэгч (шалгасан)'])->inline()->live()->required(),
                        Select::make('customer_id')->label('Үйлчлүүлэгч')->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Customer::query()->whereNull('archived_at')
                                ->where(fn ($query) => $query->where('display_name', 'like', "%{$search}%")->orWhere('normalized_contact_phone', 'like', '%'.preg_replace('/\D/', '', $search).'%'))
                                ->limit(20)->get()->mapWithKeys(fn (Customer $customer): array => [$customer->id => $customer->display_name.' · '.$customer->contact_phone])->all())
                            ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->display_name)
                            ->visible(fn (Get $get): bool => $get('customer_mode') === 'existing')->required(fn (Get $get): bool => $get('customer_mode') === 'existing')->live(),
                        Placeholder::make('matches')->label('Ижил утастай бүртгэл (зөвхөн мэдээлэл)')
                            ->content(fn (): string => Customer::query()->where('normalized_contact_phone', $this->record->phone_normalized)->pluck('display_name')->join(', ') ?: 'Алга'),
                        TextInput::make('display_name')->label('Нэр')->visible(fn (Get $get): bool => $get('customer_mode') === 'new')->required(fn (Get $get): bool => $get('customer_mode') === 'new'),
                        TextInput::make('contact_phone')->label('Утас')->tel()->visible(fn (Get $get): bool => $get('customer_mode') === 'new')->required(fn (Get $get): bool => $get('customer_mode') === 'new'),
                        Toggle::make('grant_reminders')->label('Хэрэглэгч үйлчилгээний сануулга авахыг зөвшөөрсөн (хүсэлтэд тэмдэглэсэн)'),
                    ]),
                    Section::make('Машин')->schema([
                        Radio::make('vehicle_mode')->label('')->options(['new' => 'Шинэ машин', 'existing' => 'Энэ үйлчлүүлэгчийн бүртгэлтэй машин'])->inline()->live()->required(),
                        Select::make('vehicle_id')->label('Машин')
                            ->options(fn (Get $get): array => $get('customer_id') ? Customer::query()->find($get('customer_id'))?->currentVehicles()->get()->mapWithKeys(fn (Vehicle $vehicle): array => [$vehicle->id => $vehicle->label()])->all() ?? [] : [])
                            ->visible(fn (Get $get): bool => $get('vehicle_mode') === 'existing')->required(fn (Get $get): bool => $get('vehicle_mode') === 'existing'),
                        TextInput::make('make')->label('Марк')->visible(fn (Get $get): bool => $get('vehicle_mode') === 'new')->required(fn (Get $get): bool => $get('vehicle_mode') === 'new'),
                        TextInput::make('model')->label('Загвар')->visible(fn (Get $get): bool => $get('vehicle_mode') === 'new'),
                        TextInput::make('plate')->label('Улсын дугаар')->visible(fn (Get $get): bool => $get('vehicle_mode') === 'new'),
                        TextInput::make('year')->label('Он')->integer()->visible(fn (Get $get): bool => $get('vehicle_mode') === 'new'),
                    ])->columns(2),
                    Section::make('Цаг')->schema([
                        ...ScheduleFields::make(),
                        Textarea::make('staff_notes')->label('Дотоод тэмдэглэл'),
                    ]),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();

                    $appointment = Notify::attempt(function () use ($data, $actor) {
                        return DB::transaction(function () use ($data, $actor) {
                            $customers = app(CustomerService::class);
                            $customer = $data['customer_mode'] === 'existing'
                                ? Customer::query()->findOrFail($data['customer_id'])
                                : $customers->createCustomer(['display_name' => $data['display_name'], 'contact_phone' => $data['contact_phone']], $actor);

                            if ($data['vehicle_mode'] === 'existing') {
                                $vehicle = $customer->currentVehicles()->whereKey($data['vehicle_id'])->first() ?? throw new DomainException('Машин энэ үйлчлүүлэгчид холбогдоогүй байна.');
                            } else {
                                $vehicle = $customers->createVehicle(['make' => $data['make'], 'model' => $data['model'] ?? null, 'plate' => $data['plate'] ?? null, 'year' => $data['year'] ?? null], $actor);
                                $customers->grantVehicleAccess($customer, $vehicle, VehicleRelationship::Owner, $actor, 'request_conversion');
                            }

                            if (! empty($data['grant_reminders'])) {
                                app(ConsentService::class)->grant($customer, ConsentPurpose::ServiceReminder, 'request:'.$this->record->reference, $actor->id);
                            }

                            [$start, $end] = ScheduleFields::interval($data);

                            return app(ConfirmAppointment::class)->handle([
                                'customer_id' => $customer->id,
                                'vehicle_id' => $vehicle->id,
                                'service_id' => $this->record->service_id,
                                'type' => $data['type'] ?? 'workshop',
                                'start' => $start,
                                'end' => $end,
                                'bay_id' => $data['bay_id'] ?? null,
                                'mechanic_user_id' => $data['mechanic_user_id'],
                                'buffer_min' => (int) ($data['buffer_min'] ?? 0),
                                'travel_min' => (int) ($data['travel_min'] ?? 0),
                                'staff_notes' => $data['staff_notes'] ?? null,
                                'service_request_id' => $this->record->id,
                            ], $actor);
                        });
                    }, 'Цаг баталгаажлаа');

                    $this->redirect(AppointmentResource::getUrl('edit', ['record' => $appointment]));
                }),

            Action::make('contacted')
                ->label('Холбогдсон гэж тэмдэглэх')
                ->icon('heroicon-o-phone')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === RequestStatus::New)
                ->action(function (): void {
                    $this->record->forceFill(['status' => RequestStatus::Contacted, 'first_responded_at' => $this->record->first_responded_at ?? now(), 'assigned_staff_id' => $this->record->assigned_staff_id ?? auth()->id()])->save();
                    Audit::record('request.contacted', $this->record);
                }),

            ActionGroup::make([
                Action::make('note')->label('Тэмдэглэл / хариуцагч')->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (): array => ['staff_notes' => $this->record->staff_notes, 'assigned_staff_id' => $this->record->assigned_staff_id])
                    ->schema([
                        Textarea::make('staff_notes')->label('Тэмдэглэл')->rows(4),
                        Select::make('assigned_staff_id')->label('Хариуцагч')->options(fn (): array => User::query()->where('type', 'staff')->where('active', true)->whereIn('staff_role', ['owner', 'advisor'])->pluck('name', 'id')->all()),
                    ])
                    ->action(function (array $data): void {
                        $this->record->forceFill($data)->save();
                    }),
                Action::make('close')->label('Хаах')->icon('heroicon-o-archive-box')->color('gray')
                    ->visible(fn (): bool => $this->record->converted_at === null && $this->record->status !== RequestStatus::Closed)
                    ->schema([Textarea::make('reason')->label('Шалтгаан')->required()])
                    ->action(function (array $data): void {
                        $this->record->forceFill(['status' => RequestStatus::Closed, 'staff_notes' => trim(($this->record->staff_notes ? $this->record->staff_notes."\n" : '').'Хаасан: '.$data['reason'])])->save();
                        Audit::record('request.closed', $this->record, reason: $data['reason']);
                    }),
                Action::make('spam')->label('Спам')->icon('heroicon-o-no-symbol')->color('danger')->requiresConfirmation()
                    ->visible(fn (): bool => $this->record->converted_at === null && $this->record->status !== RequestStatus::Spam)
                    ->action(function (): void {
                        $this->record->forceFill(['status' => RequestStatus::Spam])->save();
                        Audit::record('request.spam', $this->record);
                    }),
            ])->label('Бусад')->button()->color('gray'),
        ];
    }
}
