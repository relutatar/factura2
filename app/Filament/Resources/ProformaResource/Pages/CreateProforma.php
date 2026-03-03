<?php

namespace App\Filament\Resources\ProformaResource\Pages;

use App\Filament\Resources\ProformaResource;
use App\Models\Contract;
use App\Services\ProformaService;
use Filament\Resources\Pages\CreateRecord;

class CreateProforma extends CreateRecord
{
    protected static string $resource = ProformaResource::class;

    /** Set when page is opened from a contract — keeps client/contract locked across Livewire calls. */
    public ?int $prefillContractId = null;

    /**
     * If a contract_id query parameter is present, lock the pre-filled fields
     * and fill the form with contract data. Nothing is saved until the user submits.
     */
    public function mount(): void
    {
        parent::mount();

        $contractId = (int) request()->query('contract_id') ?: null;
        if (! $contractId) {
            return;
        }

        $contract = Contract::withoutGlobalScopes()->find($contractId);
        if (! $contract) {
            return;
        }

        $this->prefillContractId = $contract->id;

        $this->form->fill(
            app(ProformaService::class)->prepareDataFromContract($contract)
        );
    }

    protected function afterCreate(): void
    {
        app(ProformaService::class)->recalculateTotals($this->record);
    }
}
