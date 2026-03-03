<?php

namespace App\Filament\Resources\ContractAmendmentResource\Pages;

use App\Filament\Resources\ContractAmendmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContractAmendment extends EditRecord
{
    protected static string $resource = ContractAmendmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
