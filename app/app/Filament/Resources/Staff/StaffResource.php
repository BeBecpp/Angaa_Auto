<?php

namespace App\Filament\Resources\Staff;

use App\Enums\StaffRole;
use App\Enums\UserType;
use App\Filament\Concerns\GatesByRole;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Models\User;
use App\Support\Audit;
use App\Support\EnumValue;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * Owner-only staff management. Deactivation removes access on the next request.
 */
class StaffResource extends Resource
{
    use GatesByRole;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Тохиргоо';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'ажилтан';

    protected static ?string $pluralModelLabel = 'Баг';

    protected static ?string $slug = 'staff';

    protected static function viewRoles(): array
    {
        return [StaffRole::Owner];
    }

    protected static function writeRoles(): array
    {
        return [StaffRole::Owner];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', UserType::Staff->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Нэр')->required(),
            TextInput::make('email')->label('Имэйл (нэвтрэх)')->email()->required()->unique(ignoreRecord: true),
            Select::make('staff_role')->label('Үүрэг')->options(StaffRole::class)->required(),
            TextInput::make('password')->label('Нууц үг')->password()->revealable()
                ->rule(Password::min(10)->letters()->numbers())
                ->required(fn (?Model $record): bool => $record === null)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Засварлахдаа хоосон үлдээвэл өөрчлөгдөхгүй.'),
            Toggle::make('active')->label('Идэвхтэй')->default(true),
            Toggle::make('bookable')->label('Цаг товлох боломжтой (механик)'),
            CheckboxList::make('capabilities')->label('Нэмэлт эрх')->options(['qc' => 'Чанарын шалгалт хийх'])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Нэр')->searchable()->weight('bold'),
                TextColumn::make('email')->label('Имэйл'),
                TextColumn::make('staff_role')->label('Үүрэг')->badge(),
                IconColumn::make('active')->label('Идэвхтэй')->boolean(),
                IconColumn::make('staffProfile.bookable')->label('Цаг товлох')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, User $record): array => $data + ['bookable' => (bool) $record->staffProfile?->bookable, 'capabilities' => $record->staffProfile?->capabilities ?? []])
                    ->using(fn (User $record, array $data): User => self::persist($data, $record)),
                Action::make('logoutEverywhere')->label('Сешн хаах')->icon('heroicon-o-arrow-right-start-on-rectangle')->color('gray')->requiresConfirmation()
                    ->action(function (User $record): void {
                        DB::table('sessions')->where('user_id', $record->id)->delete();
                        Audit::record('staff.sessions_revoked', $record);
                    }),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persist(array $data, ?User $record = null): User
    {
        return DB::transaction(function () use ($data, $record): User {
            $user = $record ?? new User;
            $user->fill(collect($data)->only(['name', 'email', 'password'])->all());
            $user->forceFill([
                'type' => UserType::Staff,
                'staff_role' => StaffRole::from(EnumValue::of($data['staff_role'])),
                'active' => (bool) ($data['active'] ?? true),
            ])->save();

            $user->staffProfile()->updateOrCreate([], [
                'display_name' => $user->name,
                'bookable' => (bool) ($data['bookable'] ?? false),
                'capabilities' => array_values($data['capabilities'] ?? []),
            ]);

            if (! $user->active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            Audit::record($record ? 'staff.updated' : 'staff.created', $user, ['role' => $user->staff_role->value, 'active' => $user->active]);

            return $user;
        });
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaff::route('/')];
    }
}
