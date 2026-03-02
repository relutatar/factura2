<?php

namespace App\Filament\Resources\DecisionResource\Pages;

use App\Filament\Resources\DecisionResource;
use App\Models\Company;
use Filament\Resources\Pages\CreateRecord;

class CreateDecision extends CreateRecord
{
    protected static string $resource = DecisionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['company_id'])) {
            $data['company_id'] = session('active_company_id');
        }

        if (empty($data['legal_representative_name'])) {
            $company = Company::withoutGlobalScopes()->find($data['company_id'] ?? null);
            $data['legal_representative_name'] = $company?->administrator ?? '';
        }

        return $data;
    }
}
