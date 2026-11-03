<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Actions\Customers\CustomerService;
use App\Actions\Maintenance\ConsentService;
use App\Enums\ConsentPurpose;
use App\Enums\VehicleRelationship;
use App\Filament\Concerns\RefreshesRecordAfterActions;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Support\Notify;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCustomerAccess;
use App\Support\Audit;
use App\Support\EnumValue;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;

class ViewCustomer extends ViewRecord
{
    use RefreshesRecordAfterActions;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        $service = fn (): CustomerService => app(CustomerService::class);

        return [
            Action::make('addVehicle')->label('Машин холбох')->icon('heroicon-o-plus')
                ->schema([
                    Radio::make('mode')->label('')->options(['new' => 'Шинэ машин бүртгэх', 'existing' => 'Бүртгэлтэй машин (шалгасан)'])->default('new')->inline()->live(),
                    Select::make('vehicle_id')->label('Машин')->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Vehicle::query()->where('current_plate_normalized', 'like', '%'.mb_strtoupper(str_replace(' ', '', $search)).'%')->orWhere('vin', $search)->limit(20)->get()->mapWithKeys(fn (Vehicle $vehicle): array => [$vehicle->id => $vehicle->label()])->all())
                        ->visible(fn (Get $get): bool => $get('mode') === 'existing')->required(fn (Get $get): bool => $get('mode') === 'existing'),
                    TextInput::make('make')->label('Марк')->visible(fn (Get $get): bool => $get('mode') === 'new')->required(fn (Get $get): bool => $get('mode') === 'new'),
                    TextInput::make('model')->label('Загвар')->visible(fn (Get $get): bool => $get('mode') === 'new'),
                    TextInput::make('plate')->label('Улсын дугаар')->visible(fn (Get $get): bool => $get('mode') === 'new'),
                    TextInput::make('year')->label('Он')->integer()->visible(fn (Get $get): bool => $get('mode') === 'new'),
                    TextInput::make('vin')->label('VIN (заавал биш)')->visible(fn (Get $get): bool => $get('mode') === 'new'),
                    Select::make('relationship')->label('Холбоо')->options(VehicleRelationship::class)->default('owner')->required(),
                    Placeholder::make('note')->content('Өмнөх эзэмшигчийн засварын түүх шинэ эзэмшигчид харагдахгүй (ажил бүр үйлчлүүлэгчээр хязгаарлагдана).'),
                ])
                ->action(function (array $data) use ($service): void {
                    Notify::attempt(function () use ($data, $service) {
                        $vehicle = $data['mode'] === 'existing'
                            ? Vehicle::query()->findOrFail($data['vehicle_id'])
                            : $service()->createVehicle($data, auth()->user());

                        return $service()->grantVehicleAccess($this->record, $vehicle, VehicleRelationship::from(EnumValue::of($data['relationship'])), auth()->user());
                    }, 'Машин холбогдлоо');
                }),

            Action::make('consent')->label('Сануулгын зөвшөөрөл')->icon('heroicon-o-bell')->color('gray')
                ->fillForm(fn (): array => [
                    'service_reminder' => app(ConsentService::class)->has($this->record, ConsentPurpose::ServiceReminder) ? 'on' : 'off',
                    'marketing' => app(ConsentService::class)->has($this->record, ConsentPurpose::Marketing) ? 'on' : 'off',
                ])
                ->schema([
                    Radio::make('service_reminder')->label('Үйлчилгээний сануулга')->options(['on' => 'Зөвшөөрсөн', 'off' => 'Зөвшөөрөөгүй / цуцалсан'])->inline()->required(),
                    Radio::make('marketing')->label('Мэдээ, урамшуулал')->options(['on' => 'Зөвшөөрсөн', 'off' => 'Зөвшөөрөөгүй'])->inline()->required(),
                    Textarea::make('source')->label('Хэрэглэгч хэзээ, хэрхэн хэлсэн')->required(),
                ])
                ->action(function (array $data): void {
                    Notify::attempt(function () use ($data) {
                        $consents = app(ConsentService::class);

                        foreach (['service_reminder' => ConsentPurpose::ServiceReminder, 'marketing' => ConsentPurpose::Marketing] as $key => $purpose) {
                            $data[$key] === 'on'
                                ? $consents->grant($this->record, $purpose, 'staff: '.$data['source'], auth()->id())
                                : $consents->revoke($this->record, $purpose, 'staff: '.$data['source'], auth()->id());
                        }

                        return true;
                    }, 'Хадгалагдлаа');
                }),

            ActionGroup::make([
                Action::make('edit')->label('Нэр / тэмдэглэл')->icon('heroicon-o-pencil')
                    ->fillForm(fn (): array => ['display_name' => $this->record->display_name, 'notes_internal' => $this->record->notes_internal])
                    ->schema([TextInput::make('display_name')->label('Нэр')->required(), Textarea::make('notes_internal')->label('Дотоод тэмдэглэл')])
                    ->action(fn (array $data) => $this->record->forceFill($data)->save()),
                Action::make('phone')->label('Утас солих')->icon('heroicon-o-phone')
                    ->schema([TextInput::make('phone')->label('Шинэ утас')->tel()->required()])
                    ->modalDescription('Хуучин дугаар руу төлөвлөсөн мессежүүд цуцлагдана. Сануулгын зөвшөөрлийг шинэ дугаарт дахин авна.')
                    ->action(fn (array $data) => Notify::attempt(fn () => $service()->changeContactPhone($this->record, $data['phone'], auth()->user()), 'Утас солигдлоо')),
                Action::make('link')->label('Порталын бүртгэл холбох')->icon('heroicon-o-link')
                    ->visible(fn (): bool => $this->record->user_id === null)
                    ->schema([
                        Select::make('user_id')->label('Утсаа баталгаажуулсан бүртгэл (ижил утас)')->required()
                            ->options(fn (): array => User::query()->where('type', 'customer')->whereNotNull('phone_verified_at')->where('phone_normalized', $this->record->normalized_contact_phone)
                                ->get()->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' · '.Phone::display($user->phone_normalized).' · '.$user->phone_verified_at?->format('Y-m-d')])->all()),
                        Placeholder::make('how')->content('Хэрэглэгч порталд утсаараа нэвтэрсний дараа энд гарна. Хүнийг биечлэн/утсаар шалгасны дараа холбоно.'),
                    ])
                    ->action(fn (array $data) => Notify::attempt(fn () => $service()->linkPortalUser($this->record, User::query()->findOrFail($data['user_id']), auth()->user()), 'Портал холбогдлоо')),
                Action::make('endAccess')->label('Машины холбоо дуусгах (эзэмшил шилжсэн)')->icon('heroicon-o-arrow-right-start-on-rectangle')->color('warning')
                    ->schema([
                        Select::make('access_id')->label('Машин')->required()
                            ->options(fn (): array => VehicleCustomerAccess::query()->where('customer_id', $this->record->id)->whereNull('ends_at')->with('vehicle')->get()->mapWithKeys(fn ($access): array => [$access->id => $access->vehicle->label()])->all()),
                        Textarea::make('reason')->label('Шалтгаан')->required(),
                    ])
                    ->action(fn (array $data) => Notify::attempt(fn () => $service()->endVehicleAccess(VehicleCustomerAccess::query()->where('customer_id', $this->record->id)->findOrFail($data['access_id']), $data['reason'], auth()->user()), 'Холбоо дууслаа; сануулга зогслоо')),
                Action::make('archive')->label('Архивлах')->icon('heroicon-o-archive-box')->color('danger')
                    ->visible(fn (): bool => auth()->user()->isOwner() && $this->record->archived_at === null)
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $this->record->forceFill(['archived_at' => now()])->save();
                        Audit::record('customer.archived', $this->record);
                    }),
            ])->label('Бусад')->button()->color('gray'),
        ];
    }
}
