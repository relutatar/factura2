<?php

namespace App\Filament\Resources\DecisionTemplateResource\Pages;

use App\Filament\Resources\DecisionTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDecisionTemplate extends EditRecord
{
    protected static string $resource = DecisionTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
