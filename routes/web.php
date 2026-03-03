<?php

use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\Decision;
use App\Models\Invoice;
use App\Models\Proforma;
use App\Models\Receipt;
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

Route::get('/proformas/{proforma}/pdf', function (Proforma $proforma, PdfService $pdfService) {
    $path = $pdfService->generateProforma($proforma);

    $filename = $proforma->full_number
        ? 'Proforma-' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $proforma->full_number) . '.pdf'
        : 'Proforma-' . $proforma->id . '.pdf';

    return response()->download($path, $filename, [
        'Content-Type' => 'application/pdf',
    ]);
})->middleware(['auth'])->name('proformas.pdf');

Route::get('/receipts/{receipt}/pdf', function (Receipt $receipt, PdfService $pdfService) {
    $path = $receipt->pdf_path;

    if (! $path || ! file_exists($path)) {
        $path = $pdfService->generateReceipt($receipt);
    }

    return response()->download($path, 'chitanta-' . $receipt->full_number . '.pdf', [
        'Content-Type' => 'application/pdf',
    ]);
})->middleware(['auth'])->name('receipts.pdf');

Route::get('/contract-amendments/{amendment}/pdf', function (ContractAmendment $amendment, PdfService $pdfService) {
    $path = $pdfService->generateContractAmendment($amendment);

    return response()->download($path, 'act-aditional-' . $amendment->amendment_number . '.pdf', [
        'Content-Type' => 'application/pdf',
    ]);
})->middleware(['auth'])->name('contract-amendments.pdf');
