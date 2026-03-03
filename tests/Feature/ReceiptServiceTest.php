<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\NumberingRange;
use App\Services\ReceiptService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_receipt_for_paid_cash_invoice(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $invoice = $this->createInvoice($company, $client, InvoiceStatus::Platita, PaymentMethod::Numerar);
        $this->createNumberingRange($company, 'chitanta', 'CHNOD');

        $receipt = app(ReceiptService::class)->createForInvoice($invoice);

        $this->assertSame($invoice->id, $receipt->invoice_id);
        $this->assertSame('CHNOD', $receipt->series);
        $this->assertSame('CHNOD-0001', $receipt->full_number);
        $this->assertSame(ReceiptStatus::Emisa, $receipt->status);
    }

    public function test_it_fails_when_no_active_receipt_range_exists(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $invoice = $this->createInvoice($company, $client, InvoiceStatus::Platita, PaymentMethod::Numerar);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Nu există plajă activă pentru chitanta');

        app(ReceiptService::class)->createForInvoice($invoice);
    }

    public function test_it_fails_for_non_cash_or_unpaid_invoice(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $this->createNumberingRange($company, 'chitanta', 'CHNOD');

        $invoiceBank = $this->createInvoice($company, $client, InvoiceStatus::Platita, PaymentMethod::ViramentBancar);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('modalitate de plată numerar');

        app(ReceiptService::class)->createForInvoice($invoiceBank);
    }

    public function test_receipt_numbering_is_immutable_after_issue(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $invoice = $this->createInvoice($company, $client, InvoiceStatus::Platita, PaymentMethod::Numerar);
        $this->createNumberingRange($company, 'chitanta', 'CHNOD');

        $receipt = app(ReceiptService::class)->createForInvoice($invoice);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Numerotarea chitanței este imutabilă după emitere.');

        $receipt->update(['number' => 2]);
    }

    private function createCompany(): Company
    {
        return Company::withoutGlobalScopes()->create([
            'name' => 'Companie Test',
            'administrator' => 'Admin',
            'cif' => 'RO' . random_int(10000000, 99999999),
        ]);
    }

    private function createClient(Company $company): Client
    {
        return Client::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'type' => 'persoana_juridica',
            'name' => 'Client Test',
            'cif' => 'RO' . random_int(10000000, 99999999),
        ]);
    }

    private function createInvoice(
        Company $company,
        Client $client,
        InvoiceStatus $status,
        PaymentMethod $paymentMethod
    ): Invoice {
        return Invoice::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => $status,
            'issue_date' => Carbon::create(2026, 3, 3)->toDateString(),
            'due_date' => Carbon::create(2026, 4, 3)->toDateString(),
            'payment_method' => $paymentMethod,
            'total' => 100,
            'currency' => 'RON',
            'paid_at' => now(),
        ]);
    }

    private function createNumberingRange(Company $company, string $documentType, string $series): NumberingRange
    {
        return NumberingRange::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'document_type' => $documentType,
            'fiscal_year' => (int) now()->year,
            'series' => $series,
            'start_number' => 1,
            'end_number' => 9999,
            'next_number' => 1,
            'is_active' => true,
        ]);
    }
}
