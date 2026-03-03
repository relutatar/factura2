<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Contract;
use App\Services\InvoiceService;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

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
            app(InvoiceService::class)->prepareDataFromContract($contract)
        );
    }

    protected function afterCreate(): void
    {
        $invoice = $this->record;
        $svc = app(InvoiceService::class);

        // Auto-assign series/number/full_number if not already set
        if (empty($invoice->full_number)) {
            if (! empty($invoice->series) && ! empty($invoice->number)) {
                $invoice->update([
                    'full_number' => $invoice->series . '-' . str_pad((string) $invoice->number, 4, '0', STR_PAD_LEFT),
                ]);
            } else {
                $numbering = $svc->reserveNextNumber(
                    $invoice->company_id,
                    'factura',
                    $invoice->issue_date
                );

                $invoice->update($numbering);
            }
        }

        $svc->recalculateTotals($invoice);
    }
}
