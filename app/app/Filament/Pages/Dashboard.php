<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OperationalStats;
use App\Filament\Widgets\OwnerAlerts;
use App\Filament\Widgets\TodayBoard;
use Filament\Pages\Dashboard as BaseDashboard;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    protected static string|UnitEnum|null $navigationGroup = 'Тойм';

    protected static ?string $title = 'Өнөөдөр';

    protected static ?string $navigationLabel = 'Хяналтын самбар';

    public function getWidgets(): array
    {
        return [OperationalStats::class, TodayBoard::class, OwnerAlerts::class];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
