<?php

use App\Models\Contract;
use App\Models\Decision;
use App\Models\Invoice;
use App\Services\PdfService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invoices/{invoice}/pdf', function (Invoice $invoice) {
    $path = $invoice->pdf_path;

    if (! $path || ! file_exists($path)) {
        abort(404, 'PDF-ul nu a fost generat încă.');
    }

    return response()->download($path, $invoice->full_number . '.pdf', [
        'Content-Type' => 'application/pdf',
    ]);
})->middleware(['auth'])->name('invoices.pdf');

Route::get('/contracts/{contract}/pdf', function (Contract $contract, PdfService $pdfService) {
    $path = $pdfService->generateContract($contract);

    return response()->download($path, 'Contract-' . $contract->number . '.pdf', [
        'Content-Type' => 'application/pdf',
    ]);
})->middleware(['auth'])->name('contracts.pdf');

Route::get('/decisions/{decision}/pdf', function (Decision $decision, PdfService $pdfService) {
    $path = $pdfService->generateDecision($decision);

    $number = $decision->number ?: $decision->id;

    return response()->download($path, 'Decizie-' . $number . '.pdf', [
        'Content-Type' => 'application/pdf',
    ]);
})->middleware(['auth'])->name('decisions.pdf');
