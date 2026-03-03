<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\Decision;
use App\Models\Invoice;
use App\Models\Proforma;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    /**
     * Generate a PDF for the given invoice and store it.
     * Returns the absolute path to the saved file.
     */
    public function generateInvoice(Invoice $invoice): string
    {
        $invoice->loadMissing(['company', 'client', 'lines.product', 'lines.vatRate']);

        $dir = storage_path("app/invoices/{$invoice->company_id}");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$invoice->full_number}.pdf";

        Pdf::loadView('pdf.invoice', compact('invoice'))
            ->setPaper('a4', 'portrait')
            ->save($path);

        Invoice::withoutGlobalScopes()
            ->where('id', $invoice->id)
            ->update(['pdf_path' => $path]);

        return $path;
    }

    /**
     * Generate a PDF for a contract and store it.
     * Returns the absolute path to the saved file.
     */
    public function generateContract(Contract $contract): string
    {
        $contract->loadMissing(['company', 'client', 'template']);

        $dir = storage_path('app/contracts');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$contract->id}.pdf";
        $content = app(ContractTemplateService::class)->render($contract);

        Pdf::loadView('pdf.contract', compact('contract', 'content'))
            ->setPaper('a4', 'portrait')
            ->save($path);

        return $path;
    }

    /**
     * Generate a PDF for an administrative decision and store it.
     * Returns the absolute path to the saved file.
     */
    public function generateDecision(Decision $decision): string
    {
        $decision->loadMissing(['company', 'template']);

        if (blank($decision->content_snapshot)) {
            $decision->content_snapshot = app(DecisionService::class)->renderDecisionContent($decision);
            $decision->save();
        }

        $dir = storage_path('app/decisions');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $suffix = $decision->number ?: $decision->id;
        $path = "{$dir}/decision-{$suffix}.pdf";

        Pdf::loadView('pdf.decision', compact('decision'))
            ->setPaper('a4', 'portrait')
            ->save($path);

        return $path;
    }

    /**
     * Generate a PDF for the given proforma and store it.
     * Returns the absolute path to the saved file.
     */
    public function generateProforma(Proforma $proforma): string
    {
        $proforma->loadMissing(['company', 'client', 'lines.vatRate']);

        $dir = storage_path("app/proformas/{$proforma->company_id}");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $proforma->full_number
            ? preg_replace('/[^A-Za-z0-9\-_]/', '_', $proforma->full_number)
            : "proforma-{$proforma->id}";

        $path = "{$dir}/{$filename}.pdf";

        Pdf::loadView('pdf.proforma', compact('proforma'))
            ->setPaper('a4', 'portrait')
            ->save($path);

        Proforma::withoutGlobalScopes()
            ->where('id', $proforma->id)
            ->update(['pdf_path' => $path]);

        return $path;
    }

    /**
     * Generate a PDF for the given receipt and store it.
     * Returns the absolute path to the saved file.
     */
    public function generateReceipt(Receipt $receipt): string
    {
        $receipt->loadMissing(['company', 'invoice.client']);

        $dir = storage_path("app/receipts/{$receipt->company_id}");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $receipt->full_number ?: ('receipt-' . $receipt->id));
        $path = "{$dir}/{$filename}.pdf";

        Pdf::loadView('pdf.receipt', compact('receipt'))
            ->setPaper('a4', 'portrait')
            ->save($path);

        Receipt::withoutGlobalScopes()
            ->where('id', $receipt->id)
            ->update(['pdf_path' => $path]);

        return $path;
    }

    /**
     * Generate a PDF for a contract amendment and store it.
     * Returns the absolute path to the saved file.
     */
    public function generateContractAmendment(ContractAmendment $amendment): string
    {
        $amendment->loadMissing(['contract.company', 'contract.client']);

        $dir = storage_path('app/amendments');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$amendment->id}.pdf";

        Pdf::loadView('pdf.contract-amendment', compact('amendment'))
            ->setPaper('a4', 'portrait')
            ->save($path);

        ContractAmendment::withoutGlobalScopes()
            ->where('id', $amendment->id)
            ->update(['pdf_path' => $path]);

        return $path;
    }
}
