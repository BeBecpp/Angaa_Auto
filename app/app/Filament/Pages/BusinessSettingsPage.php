<?php

namespace App\Filament\Pages;

use App\Enums\StaffRole;
use App\Support\Audit;
use App\Support\Phone;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Central, owner-editable business details. Unknown real details stay empty rather than invented.
 */
class BusinessSettingsPage extends Page
{
    protected string $view = 'filament.pages.business-settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'Тохиргоо';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Бизнесийн мэдээлэл';

    protected static ?string $title = 'Бизнесийн мэдээлэл';

    protected static ?string $slug = 'business-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(StaffRole::Owner) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(Settings::allWithDefaults());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Холбоо барих')->columns(2)->schema([
                TextInput::make('contact_phone')->label('Утас')->tel()->helperText('Бодит дугаар батлагдсаны дараа оруулна.')
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail): void {
                        if (filled($value) && Phone::normalize($value) === null) {
                            $fail('Утасны дугаар буруу.');
                        }
                    }),
                TextInput::make('public_domain')->label('Домэйн (https-гүй)')->placeholder('angaa-auto.mn'),
                Textarea::make('address')->label('Хаяг')->columnSpanFull(),
                Textarea::make('landmark_directions')->label('Ойролцоох тэмдэг, зогсоол')->columnSpanFull(),
                TextInput::make('map_lat')->label('Өргөрөг')->numeric(),
                TextInput::make('map_lng')->label('Уртраг')->numeric(),
                Textarea::make('closure_notice')->label('Түр хаалтын мэдэгдэл (сайтад харагдана)')->columnSpanFull(),
                Textarea::make('owner_intro')->label('Бидний тухай (зөвхөн баталгаатай мэдээлэл)')->columnSpanFull(),
            ]),
            Section::make('Очиж үйлчлэх')->columns(2)->schema([
                Toggle::make('callout_enabled')->label('Очиж үйлчлэх хүсэлт авах'),
                TextInput::make('callout_area')->label('Хүрээ'),
                Textarea::make('callout_fee_policy')->label('Төлбөрийн бодлого')->columnSpanFull(),
            ]),
            Section::make('Мессеж ба баримт')->columns(2)->schema([
                TimePicker::make('quiet_start')->label('Мессеж илгээхгүй эхлэх')->seconds(false)->format('H:i'),
                TimePicker::make('quiet_end')->label('Мессеж илгээхгүй дуусах')->seconds(false)->format('H:i'),
                Textarea::make('invoice_footer')->label('Нэхэмжлэлийн доорх тэмдэглэл')->columnSpanFull(),
            ]),
            Section::make('Нээлтэд бэлэн байдал')->columns(2)->schema([
                Toggle::make('public_booking_enabled')->label('Олон нийтээс хүсэлт хүлээн авах'),
                Toggle::make('details_approved')->label('Эзэн дээрх бизнесийн мэдээллийг шалгаж баталсан'),
                TextInput::make('last_restore_test_at')->label('Нөөцөөс сэргээж шалгасан огноо')->placeholder('YYYY-MM-DD'),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (array_keys(Settings::DEFINITIONS) as $key) {
            if (array_key_exists($key, $data)) {
                Settings::set($key, $data[$key]);
            }
        }

        Audit::record('settings.updated', null, ['keys' => array_keys($data)]);
        Notification::make()->success()->title('Хадгалагдлаа')->send();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('save')->label('Хадгалах')->action('save')];
    }
}
