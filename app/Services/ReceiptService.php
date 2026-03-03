<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Jobs\GenerateReceiptPdf;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Receipt;

class ReceiptService
{
    /**
     * Create a receipt for a paid cash invoice.
     */
    public function createForInvoice(Invoice $invoice): Receipt
    {
        if ($invoice->status !== InvoiceStatus::Platita) {
            throw new \RuntimeException('Chitanța se poate genera doar pentru facturi marcate ca plătite.');
        }

        if ($invoice->payment_method !== PaymentMethod::Numerar) {
            throw new \RuntimeException('Chitanța se poate genera doar pentru facturi cu modalitate de plată numerar.');
        }

        if ($invoice->receipt()->exists()) {
            throw new \RuntimeException('Factura are deja o chitanță emisă.');
        }

        $company = Company::withoutGlobalScopes()->find($invoice->company_id);

        if (! $company) {
            throw new \RuntimeException('Compania facturii nu a fost găsită.');
        }

        $reservation = app(DocumentNumberService::class)->reserveNextNumber(
            company: $company,
            documentType: 'chitanta',
            issuedAt: $invoice->paid_at ?? now(),
        );

        $receipt = Receipt::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'status' => ReceiptStatus::Emisa,
            'series' => $reservation['series'],
            'number' => $reservation['number'],
            'full_number' => $reservation['full_number'],
            'numbering_range_id' => $reservation['numbering_range_id'] ?? null,
            'work_point_code' => $reservation['work_point_code'] ?? null,
            'issue_date' => ($invoice->paid_at ?? now())->toDateString(),
            'amount' => (float) $invoice->total,
            'currency' => $invoice->currency ?: 'RON',
        ]);

        GenerateReceiptPdf::dispatch($receipt);

        return $receipt;
    }

    /**
     * Cancel an issued receipt.
     */
    public function cancel(Receipt $receipt): void
    {
        if ($receipt->status === ReceiptStatus::Anulata) {
            throw new \RuntimeException('Chitanța este deja anulată.');
        }

        $receipt->update([
            'status' => ReceiptStatus::Anulata,
        ]);
    }
}
