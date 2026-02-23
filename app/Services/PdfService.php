<?php

namespace App\Services;

use App\Models\Contract;

class PdfService
{
    /**
     * Generate a PDF for a contract and return the saved path.
     */
    public function generateContract(Contract $contract): string
    {
        $dir = storage_path('app/contracts');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = "{$dir}/{$contract->id}.pdf";

        \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.contract', compact('contract'))
            ->save($path);

        return $path;
    }
}
