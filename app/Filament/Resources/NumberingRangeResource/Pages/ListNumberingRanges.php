<?php

namespace App\Filament\Resources\NumberingRangeResource\Pages;

use App\Filament\Resources\NumberingRangeResource;
use Filament\Resources\Pages\ListRecords;

class ListNumberingRanges extends ListRecords
{
    protected static string $resource = NumberingRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()->label('Adaugă plajă'),
        ];
    }
}
