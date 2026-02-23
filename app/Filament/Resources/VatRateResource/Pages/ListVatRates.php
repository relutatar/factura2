<?php

namespace App\Filament\Resources\VatRateResource\Pages;

use App\Filament\Resources\VatRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVatRates extends ListRecords
{
    protected static string $resource = VatRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
