<?php

namespace App\Filament\Resources\OpeningRules\Pages;

use App\Filament\Resources\OpeningRules\OpeningRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOpeningRules extends ManageRecords
{
    protected static string $resource = OpeningRuleResource::class;

    public function getSubheading(): ?string
    {
        return 'Улаанбаатарын цагаар. Үдийн завсарлагаа бол нэг өдөрт хоёр мөр (жишээ: 09:00–13:00, 14:00–18:00). DEMO мөрүүдийг бодит цагаар солино уу.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Цаг нэмэх')->mutateDataUsing(fn (array $data): array => $data + ['scope' => 'shop', 'is_demo' => false])];
    }
}
