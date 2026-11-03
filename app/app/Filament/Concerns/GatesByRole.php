<?php

namespace App\Filament\Concerns;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Server-side role gate for Filament resources. Records with history/finance meaning are never deleted
 * from the panel; corrections go through audited domain actions.
 */
trait GatesByRole
{
    /**
     * @return list<StaffRole>
     */
    protected static function viewRoles(): array
    {
        return [StaffRole::Owner, StaffRole::Advisor];
    }

    /**
     * @return list<StaffRole>
     */
    protected static function writeRoles(): array
    {
        return [StaffRole::Owner, StaffRole::Advisor];
    }

    /**
     * Mongolian labels must not be title-cased word by word.
     */
    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? static::$pluralModelLabel ?? parent::getNavigationLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::$pluralModelLabel ?? parent::getPluralModelLabel();
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getTitleCaseModelLabel(): string
    {
        return mb_strtoupper(mb_substr(static::getModelLabel(), 0, 1)).mb_substr(static::getModelLabel(), 1);
    }

    protected static function userHas(array $roles): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasRole(...$roles);
    }

    public static function canViewAny(): bool
    {
        return static::userHas(static::viewRoles());
    }

    public static function canView(Model $record): bool
    {
        return static::userHas(static::viewRoles());
    }

    public static function canCreate(): bool
    {
        return static::userHas(static::writeRoles());
    }

    public static function canEdit(Model $record): bool
    {
        return static::userHas(static::writeRoles());
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
