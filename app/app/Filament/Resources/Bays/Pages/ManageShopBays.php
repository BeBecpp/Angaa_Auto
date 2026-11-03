<?php

namespace App\Filament\Resources\Bays\Pages;

use App\Filament\Resources\Bays\ShopBayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShopBays extends ManageRecords
{
    protected static string $resource = ShopBayResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Пост нэмэх')];
    }
}
