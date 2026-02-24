<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\NumberingRange;
use App\Models\VatRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function reserveNextNumber(
        int $companyId,
        InvoiceType|string $documentType,
        ?CarbonInterface $issuedAt = null
    ): array {
        $issuedAt = $issuedAt ?? now();
        $type = $documentType instanceof InvoiceType ? $documentType->value : $documentType;
        $year = (int) $issuedAt->year;

        return DB::transaction(function () use ($companyId, $type, $year): array {
            $range = NumberingRange::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('document_type', $type)
                ->where('fiscal_year', $year)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $range) {
                throw new \RuntimeException("Nu există plajă activă pentru {$type} în anul {$year}.");
            }

            if ($range->next_number > $range->end_number) {
                throw new \RuntimeException("Plaja de numerotare {$range->series} este epuizată.");
            }

            $number = (int) $range->next_number;
            $range->update(['next_number' => $number + 1]);

            return [
                'series'      => $range->series,
                'number'      => $number,
                'full_number' => $range->series . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            ];
        });
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
        $numbering = $this->reserveNextNumber($company->id, InvoiceType::Factura, now());

        $invoice = Invoice::create([
            'company_id'     => $contract->company_id,
            'client_id'      => $contract->client_id,
            'contract_id'    => $contract->id,
            'type'           => InvoiceType::Factura,
            'status'         => InvoiceStatus::Draft,
            'series'         => $numbering['series'],
            'number'         => $numbering['number'],
            'full_number'    => $numbering['full_number'],
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'currency'       => 'RON',
            'payment_method' => 'ordin_plata',
        ]);

        // Default first line referencing the contract
        $contractDate = $contract->signed_date?->format('d.m.Y')
            ?? $contract->start_date?->format('d.m.Y')
            ?? now()->format('d.m.Y');

        $defaultVatRateId = optional(VatRate::defaultRate())->id ?? VatRate::first()?->id;
        $lineTotal        = (float) ($contract->value ?? 0);
        $vatRate          = VatRate::find($defaultVatRateId);
        $vatAmount        = $vatRate ? round($lineTotal * ((float) $vatRate->value / 100), 2) : 0;
        $billingCycleValues = array_map(
            static fn (BillingCycle $cycle): string => $cycle->value,
            BillingCycle::cases(),
        );
        $billingCycle = (string) data_get($contract->additional_attributes, 'billing_cycle', '');
        $unit = in_array($billingCycle, $billingCycleValues, true)
            ? $billingCycle
            : BillingCycle::Unic->value;

        InvoiceLine::create([
            'invoice_id'     => $invoice->id,
            'description'    => "Servicii conform contract nr. {$contract->number} din {$contractDate}",
            'quantity'       => 1,
            'unit'           => $unit,
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
