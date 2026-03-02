<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Decision;
use App\Models\Invoice;
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
}
