<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
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
            $company = $invoice->company()->withoutGlobalScopes()->find($invoice->company_id);
            $year    = now()->year;
            $series  = $invoice->series ?? (($company->invoice_prefix ?? 'F') . '-' . $year);
            $number  = $invoice->number ?? $svc->nextNumber($invoice->company_id, $series);

            $invoice->update([
                'series'      => $series,
                'number'      => $number,
                'full_number' => $series . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            ]);
        }

        $svc->recalculateTotals($invoice);
    }
}
