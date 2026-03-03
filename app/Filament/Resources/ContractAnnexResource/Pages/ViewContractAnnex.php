<?php

namespace App\Filament\Resources\ContractAnnexResource\Pages;

use App\Filament\Resources\ContractAnnexResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContractAnnex extends ViewRecord
{
    protected static string $resource = ContractAnnexResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
