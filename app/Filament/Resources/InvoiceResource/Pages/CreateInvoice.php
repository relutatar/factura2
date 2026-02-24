<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Enums\InvoiceType;
use App\Services\InvoiceService;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

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
                    $invoice->type ?? InvoiceType::Factura,
                    $invoice->issue_date
                );

                $invoice->update($numbering);
            }
        }

        $svc->recalculateTotals($invoice);
    }
}
