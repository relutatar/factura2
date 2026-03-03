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
                    app(InvoiceService::class)->prepareDataFromContract($contract)
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
