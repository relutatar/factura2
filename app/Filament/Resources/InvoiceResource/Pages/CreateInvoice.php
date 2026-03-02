<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Contract;
use App\Services\InvoiceService;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

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
                $data = app(InvoiceService::class)->prepareDataFromContract($contract);
                $this->form->fill($data);
            }
        }
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
