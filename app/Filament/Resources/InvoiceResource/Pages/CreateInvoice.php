<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Contract;
use App\Services\InvoiceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $companyId = (int) session('active_company_id');

        if ($companyId <= 0) {
            throw ValidationException::withMessages([
                'data.number' => 'Nu există companie activă pentru alocarea numărului de factură.',
            ]);
        }

        try {
            $numbering = app(InvoiceService::class)->reserveNextNumber(
                $companyId,
                'factura',
                now()
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'data.number' => $exception->getMessage(),
            ]);
        }

        $data['company_id'] = $companyId;
        $data['series'] = $numbering['series'];
        $data['number'] = $numbering['number'];
        $data['full_number'] = $numbering['full_number'];

        return $data;
    }

    protected function afterCreate(): void
    {
        $invoice = $this->record;

        app(InvoiceService::class)->recalculateTotals($invoice);
    }
}
