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
     * Override fillForm() so the form is filled exactly once.
     * Calling form->fill() a second time after parent::mount() does not
     * reliably populate relationship Repeater items in Filament 3.
     */
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $contractId = (int) request()->query('contract_id') ?: null;
        if ($contractId) {
            $contract = Contract::withoutGlobalScopes()->find($contractId);
            if ($contract) {
                $this->prefillContractId = $contract->id;
                $this->form->fill(
                    app(ProformaService::class)->prepareDataFromContract($contract)
                );
                $this->callHook('afterFill');
                return;
            }
        }

        $this->form->fill();
        $this->callHook('afterFill');
    }

    protected function afterCreate(): void
    {
        app(ProformaService::class)->recalculateTotals($this->record);
    }
}
