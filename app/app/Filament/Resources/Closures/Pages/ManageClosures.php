<?php

namespace App\Filament\Resources\Closures\Pages;

use App\Filament\Resources\Closures\ClosureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageClosures extends ManageRecords
{
    protected static string $resource = ClosureResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Нэмэх')];
    }
}
