<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ProformaStatus;
use App\Jobs\GenerateProformaPdf;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Proforma;

class ProformaService
{
    /**
     * Recalculate subtotal, vat_total and total from the proforma lines.
     * Also updates line_total, vat_amount, and total_with_vat on every line.
     */
    public function recalculateTotals(Proforma $proforma): void
    {
        $proforma->loadMissing('lines.vatRate');

        $subtotal = 0;
        $vatTotal = 0;

        foreach ($proforma->lines as $line) {
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

        $proforma->update([
            'subtotal'  => $subtotal,
            'vat_total' => $vatTotal,
            'total'     => $subtotal + $vatTotal,
        ]);
    }

    /**
     * Emit the proforma: allocate number from numbering ranges and generate PDF.
     *
     * @throws \RuntimeException if no active numbering range for 'proforma'
     */
    public function emit(Proforma $proforma): void
    {
        if ($proforma->status !== ProformaStatus::Draft) {
            throw new \RuntimeException('Pot fi emise doar proformele în status Ciornă.');
        }

        $reservation = app(InvoiceService::class)->reserveNextNumber(
            $proforma->company_id,
            'proforma',
            $proforma->issue_date
        );

        $proforma->update([
            'status'      => ProformaStatus::Trimisa,
            'series'      => $reservation['series'],
            'number'      => $reservation['number'],
            'full_number' => $reservation['full_number'],
        ]);

        GenerateProformaPdf::dispatch($proforma->fresh());
    }

    /**
     * Convert this proforma to a new fiscal Invoice with pre-filled lines.
     * The proforma status becomes 'convertita'.
     *
     * @throws \RuntimeException if proforma is not in status Trimisa
     */
    public function convertToInvoice(Proforma $proforma): Invoice
    {
        if ($proforma->status !== ProformaStatus::Trimisa) {
            throw new \RuntimeException('Proforma trebuie să fie în status Trimisă pentru a fi convertită.');
        }

        $proforma->loadMissing('lines');

        $invoice = Invoice::create([
            'company_id'     => $proforma->company_id,
            'client_id'      => $proforma->client_id,
            'contract_id'    => $proforma->contract_id,
            'proforma_id'    => $proforma->id,
            'status'         => InvoiceStatus::Draft,
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'payment_method' => 'virament_bancar',
            'currency'       => $proforma->currency,
        ]);

        foreach ($proforma->lines as $line) {
            $invoice->lines()->create([
                'product_id'     => $line->product_id,
                'description'    => $line->description,
                'quantity'       => $line->quantity,
                'unit'           => $line->unit,
                'unit_price'     => $line->unit_price,
                'vat_rate_id'    => $line->vat_rate_id,
                'vat_amount'     => $line->vat_amount,
                'line_total'     => $line->line_total,
                'total_with_vat' => $line->total_with_vat,
                'sort_order'     => $line->sort_order,
            ]);
        }

        app(InvoiceService::class)->recalculateTotals($invoice);

        $proforma->update([
            'status'     => ProformaStatus::Convertita,
            'invoice_id' => $invoice->id,
        ]);

        return $invoice;
    }

    /**
     * Create a draft proforma pre-filled from a contract.
     */
    public function createFromContract(Contract $contract): Proforma
    {
        return Proforma::create([
            'company_id'  => $contract->company_id,
            'client_id'   => $contract->client_id,
            'contract_id' => $contract->id,
            'status'      => ProformaStatus::Draft,
            'issue_date'  => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
        ]);
    }
}
