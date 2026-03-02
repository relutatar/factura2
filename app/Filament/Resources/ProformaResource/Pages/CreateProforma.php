<?php

namespace App\Filament\Resources\ProformaResource\Pages;

use App\Filament\Resources\ProformaResource;
use App\Models\Contract;
use App\Services\ProformaService;
use Filament\Resources\Pages\CreateRecord;

class CreateProforma extends CreateRecord
{
    protected static string $resource = ProformaResource::class;

    /**
     * If a contract_id query parameter is present, pre-fill the form
     * with data from that contract (nothing is saved to DB yet).
     */
    public function mount(): void
    {
        parent::mount();

        $contractId = request()->query('contract_id');
        if ($contractId) {
            $contract = Contract::withoutGlobalScopes()->find($contractId);
            if ($contract) {
                $data = app(ProformaService::class)->prepareDataFromContract($contract);
                $this->form->fill($data);
            }
        }
    }

    protected function afterCreate(): void
    {
        app(ProformaService::class)->recalculateTotals($this->record);
    }
}
