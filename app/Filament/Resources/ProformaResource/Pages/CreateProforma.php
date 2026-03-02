<?php

namespace App\Filament\Resources\ProformaResource\Pages;

use App\Filament\Resources\ProformaResource;
use App\Services\ProformaService;
use Filament\Resources\Pages\CreateRecord;

class CreateProforma extends CreateRecord
{
    protected static string $resource = ProformaResource::class;

    protected function afterCreate(): void
    {
        app(ProformaService::class)->recalculateTotals($this->record);
    }
}
