<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\VatRate;

class InvoiceService
{
    /**
     * Get the next sequential invoice number for a given company and series.
     * Uses a DB lock to prevent duplicates under concurrent requests.
     */
    public function nextNumber(int $companyId, string $series): int
    {
        $max = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('series', $series)
            ->lockForUpdate()
            ->max('number');

        return ($max ?? 0) + 1;
    }

    /**
     * Recalculate subtotal, vat_total and total from the invoice lines.
     * Also updates line_total, vat_amount and total_with_vat on every line.
     * Always call this after editing lines.
     */
    public function recalculateTotals(Invoice $invoice): void
    {
        $invoice->loadMissing('lines.vatRate');

        $subtotal = 0;
        $vatTotal = 0;

        foreach ($invoice->lines as $line) {
            $lineTotal = round((float) $line->quantity * (float) $line->unit_price, 2);
            $vatRate   = (float) ($line->vatRate->value ?? 0);
            $vatAmount = round($lineTotal * ($vatRate / 100), 2);

            $line->update([
                'line_total'     => $lineTotal,
                'vat_amount'     => $vatAmount,
                'total_with_vat' => $lineTotal + $vatAmount,
            ]);

            $subtotal += $lineTotal;
            $vatTotal += $vatAmount;
        }

        $invoice->update([
            'subtotal'  => $subtotal,
            'vat_total' => $vatTotal,
            'total'     => $subtotal + $vatTotal,
        ]);

        $invoice->load('lines.vatRate');
    }

    /**
     * Transition invoice status and trigger side effects:
     * draft → trimisa: generate PDF (queued), deduct stock
     * trimisa → platita: set paid_at
     * any → anulata: mark cancelled
     */
    public function transition(Invoice $invoice, InvoiceStatus $newStatus): void
    {
        if ($newStatus === InvoiceStatus::Trimisa) {
            GenerateInvoicePdf::dispatch($invoice);
            app(StockService::class)->deductForInvoice($invoice);
        }

        if ($newStatus === InvoiceStatus::Platita) {
            $invoice->paid_at = now();
        }

        $invoice->status = $newStatus;
        $invoice->save();
    }

    /**
     * Create a draft invoice pre-filled from a contract.
     */
    public function createFromContract(Contract $contract): Invoice
    {
        $company = $contract->company()->withoutGlobalScopes()->find($contract->company_id);
        $year    = now()->year;
        $series  = ($company->invoice_prefix ?? 'F') . '-' . $year;
        $number  = $this->nextNumber($company->id, $series);

        $invoice = Invoice::create([
            'company_id'     => $contract->company_id,
            'client_id'      => $contract->client_id,
            'contract_id'    => $contract->id,
            'type'           => InvoiceType::Factura,
            'status'         => InvoiceStatus::Draft,
            'series'         => $series,
            'number'         => $number,
            'full_number'    => $series . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'payment_method' => 'ordin_plata',
        ]);

        // Default first line referencing the contract
        $contractDate = $contract->start_date
            ? $contract->start_date->format('d.m.Y')
            : now()->format('d.m.Y');

        $defaultVatRateId = optional(VatRate::defaultRate())->id ?? VatRate::first()?->id;
        $lineTotal        = (float) ($contract->value ?? 0);
        $vatRate          = VatRate::find($defaultVatRateId);
        $vatAmount        = $vatRate ? round($lineTotal * ((float) $vatRate->value / 100), 2) : 0;

        InvoiceLine::create([
            'invoice_id'     => $invoice->id,
            'description'    => "Servicii conform contract nr. {$contract->number} din {$contractDate}",
            'quantity'       => 1,
            'unit'           => $contract->billing_cycle instanceof BillingCycle
                ? $contract->billing_cycle->value
                : (string) ($contract->billing_cycle ?? BillingCycle::Lunar->value),
            'unit_price'     => $lineTotal,
            'vat_rate_id'    => $defaultVatRateId,
            'vat_amount'     => $vatAmount,
            'line_total'     => $lineTotal,
            'total_with_vat' => $lineTotal + $vatAmount,
            'sort_order'     => 0,
        ]);

        $this->recalculateTotals($invoice);

        return $invoice;
    }
}
