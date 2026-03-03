<?php

namespace App\Filament\Resources\ContractAnnexResource\Pages;

use App\Filament\Resources\ContractAnnexResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContractAnnex extends EditRecord
{
    protected static string $resource = ContractAnnexResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
