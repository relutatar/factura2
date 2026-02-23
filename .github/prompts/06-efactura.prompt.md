# FACTURA2 – e-Factura Module (ANAF)

## Context
Romanian law requires B2B and B2G invoices to be submitted as UBL 2.1 XML to ANAF's SPV (Spațiul Privat Virtual) e-Factura system. This module handles XML generation, upload, and polling for validation results.

## Prerequisites
- `Invoice` and `InvoiceLine` models must exist (see `05-invoicing.prompt.md`).
- `database` queue driver must be configured (`QUEUE_CONNECTION=database`).
- Company model must have e-Factura credentials fields: `efactura_certificate_path`, `efactura_certificate_password`, `efactura_test_mode` (boolean).
- Install the ANAF package: `docker compose exec app composer require pristavu/laravel-anaf`

---

## Task

### Step 1 – Add e-Factura fields to companies table

```bash
docker compose exec app php artisan make:migration add_efactura_fields_to_companies_table
```

**Migration**:
```php
Schema::table('companies', function (Blueprint $table) {
    $table->string('efactura_certificate_path')->nullable()->after('logo');
    $table->string('efactura_certificate_password')->nullable()->after('efactura_certificate_path');
    $table->boolean('efactura_test_mode')->default(true)->after('efactura_certificate_password');
    $table->string('efactura_cif')->nullable()->after('efactura_test_mode'); // CIF used for ANAF auth
});
```

**Fillable on `Company` model** — add:
```php
'efactura_certificate_path',
'efactura_certificate_password',
'efactura_test_mode',
'efactura_cif',
```

### Step 2 – Add e-Factura tab to CompanyResource form (or create it)

In `app/Filament/Resources/CompanyResource.php` (create if it doesn't exist), add a tab:
```php
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;

Tabs\Tab::make('e-Factura ANAF')
    ->schema([
        TextInput::make('efactura_cif')
            ->label('CIF pentru ANAF')
            ->helperText('CIF-ul utilizat la autentificarea în SPV ANAF.'),
        FileUpload::make('efactura_certificate_path')
            ->label('Certificat digital (.p12 / .pfx)')
            ->acceptedFileTypes(['application/x-pkcs12'])
            ->directory('certificates')
            ->visibility('private'),
        TextInput::make('efactura_certificate_password')
            ->label('Parolă certificat')
            ->password()
            ->revealable(),
        Toggle::make('efactura_test_mode')
            ->label('Mod test ANAF')
            ->helperText('Activat = trimitere în sandbox ANAF, fără efecte legale.'),
    ]),
```

### Step 3 – Create `AnafService`

Create `app/Services/AnafService.php`:
```php
<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnafService
{
    private const ANAF_BASE_PROD = 'https://api.anaf.ro/prod/FCTEL/rest';
    private const ANAF_BASE_TEST = 'https://api.anaf.ro/test/FCTEL/rest';

    private function baseUrl(Company $company): string
    {
        return $company->efactura_test_mode ? self::ANAF_BASE_TEST : self::ANAF_BASE_PROD;
    }

    /**
     * Look up company data by CIF from ANAF public API.
     * Returns array with keys: denumire, adresa, regcom — or throws on failure.
     */
    public function lookupCif(string $cif): array
    {
        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post('https://webservicesp.anaf.ro/PlatitorTvaRest/api/v8/ws/tva', [
                [
                    'cui'  => preg_replace('/\D/', '', $cif),
                    'data' => now()->format('Y-m-d'),
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Serviciul ANAF nu răspunde.');
        }

        $found = $response->json('found.0') ?? null;
        if (! $found) {
            throw new \RuntimeException("CIF-ul {$cif} nu a fost găsit în registrul ANAF.");
        }

        return [
            'denumire' => $found['date_generale']['denumire'] ?? '',
            'adresa'   => $found['date_generale']['adresa'] ?? '',
            'regcom'   => $found['date_generale']['nrRegCom'] ?? '',
        ];
    }

    /**
     * Generate UBL 2.1 XML for the given invoice.
     */
    public function generateXml(Invoice $invoice): string
    {
        $invoice->loadMissing(['company', 'client', 'lines.product']);

        $issueDateStr    = $invoice->issue_date->format('Y-m-d');
        $dueDateStr      = $invoice->due_date?->format('Y-m-d') ?? $issueDateStr;

        $linesXml = '';
        foreach ($invoice->lines as $i => $line) {
            $linesXml .= <<<XML

    <cac:InvoiceLine>
        <cbc:ID>{$i}</cbc:ID>
        <cbc:InvoicedQuantity unitCode="{$line->unit}">{$line->quantity}</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="{$invoice->currency}">{$line->line_total}</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Name>{$line->description}</cbc:Name>
            <cac:ClassifiedTaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>{$line->vat_rate}</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="{$invoice->currency}">{$line->unit_price}</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>
XML;
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:ID>{$invoice->full_number}</cbc:ID>
    <cbc:IssueDate>{$issueDateStr}</cbc:IssueDate>
    <cbc:DueDate>{$dueDateStr}</cbc:DueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>{$invoice->currency}</cbc:DocumentCurrencyCode>

    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>{$invoice->company->name}</cbc:Name></cac:PartyName>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>RO{$invoice->company->cif}</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>

    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>{$invoice->client->name}</cbc:Name></cac:PartyName>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>RO{$invoice->client->cif}</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingCustomerParty>

    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="{$invoice->currency}">{$invoice->vat_total}</cbc:TaxAmount>
    </cac:TaxTotal>

    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="{$invoice->currency}">{$invoice->subtotal}</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="{$invoice->currency}">{$invoice->subtotal}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="{$invoice->currency}">{$invoice->total}</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="{$invoice->currency}">{$invoice->total}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    {$linesXml}
</Invoice>
XML;
    }

    /**
     * Upload UBL XML to ANAF SPV.
     * Returns the upload ID (used for polling).
     */
    public function uploadInvoice(Invoice $invoice, Company $company): string
    {
        $xml  = $this->generateXml($invoice);
        $base = $this->baseUrl($company);

        $response = Http::withOptions(['cert' => [
            storage_path('app/' . $company->efactura_certificate_path),
            $company->efactura_certificate_password,
        ]])->withBody($xml, 'application/xml')
            ->post("{$base}/upload?standard=UBL&cif={$company->efactura_cif}");

        if ($response->failed() || empty($response->json('index_incarcare'))) {
            Log::error('ANAF upload failed', ['response' => $response->body(), 'invoice' => $invoice->id]);
            throw new \RuntimeException('Eroare la trimiterea la ANAF: ' . $response->body());
        }

        return (string) $response->json('index_incarcare');
    }

    /**
     * Poll ANAF for the result of a previously uploaded invoice.
     * Returns one of: 'ok', 'nok', 'in_prelucrare'
     */
    public function pollStatus(string $uploadId, Company $company): string
    {
        $base = $this->baseUrl($company);

        $response = Http::withOptions(['cert' => [
            storage_path('app/' . $company->efactura_certificate_path),
            $company->efactura_certificate_password,
        ]])->get("{$base}/stareMesaj", ['id_incarcare' => $uploadId]);

        if ($response->failed()) {
            throw new \RuntimeException('Eroare la interogarea ANAF: ' . $response->body());
        }

        return match($response->json('stare')) {
            'ok'            => 'ok',
            'nok'           => 'nok',
            'in prelucrare' => 'in_prelucrare',
            default         => 'necunoscut',
        };
    }
}
```

### Step 4 – Create `SubmitEfactura` queued job

```bash
docker compose exec app php artisan make:job SubmitEfactura
```

**`app/Jobs/SubmitEfactura.php`**:
```php
<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\AnafService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitEfactura implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // retry after 60 seconds

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Company $company,
    ) {}

    public function handle(AnafService $anaf): void
    {
        try {
            $uploadId = $anaf->uploadInvoice($this->invoice, $this->company);

            $this->invoice->withoutGlobalScopes()->where('id', $this->invoice->id)->update([
                'efactura_id'     => $uploadId,
                'efactura_status' => 'in_prelucrare',
            ]);
        } catch (\Throwable $e) {
            Log::error("SubmitEfactura failed for invoice {$this->invoice->id}: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
```

### Step 5 – Create `PollEfacturaStatus` scheduled job

```bash
docker compose exec app php artisan make:job PollEfacturaStatus
```

**`app/Jobs/PollEfacturaStatus.php`**:
```php
<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\AnafService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollEfacturaStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AnafService $anaf): void
    {
        // Find all invoices still in processing
        Invoice::withoutGlobalScopes()
            ->where('efactura_status', 'in_prelucrare')
            ->whereNotNull('efactura_id')
            ->with('company')
            ->chunk(50, function ($invoices) use ($anaf) {
                foreach ($invoices as $invoice) {
                    try {
                        $status = $anaf->pollStatus($invoice->efactura_id, $invoice->company);
                        $invoice->withoutGlobalScopes()->where('id', $invoice->id)
                            ->update(['efactura_status' => $status]);
                    } catch (\Throwable $e) {
                        Log::warning("PollEfacturaStatus error for invoice {$invoice->id}: " . $e->getMessage());
                    }
                }
            });
    }
}
```

### Step 6 – Register scheduler in `routes/console.php`

Open `routes/console.php` and add:
```php
use App\Jobs\PollEfacturaStatus;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new PollEfacturaStatus())->everyTenMinutes()->name('poll-efactura');
```

### Step 7 – Add "Trimite la e-Factura" action to `InvoiceResource`

In `app/Filament/Resources/InvoiceResource.php`, add to `->actions([])`:
```php
use App\Jobs\SubmitEfactura;

Action::make('trimite_efactura')
    ->label('Trimite la e-Factura')
    ->icon('heroicon-o-paper-airplane')
    ->requiresConfirmation()
    ->modalHeading('Trimite factura la ANAF e-Factura')
    ->modalDescription('Factura va fi transmisă la ANAF. Asigurați-vă că factura este finalizată și clientul este persoană juridică.')
    ->modalSubmitActionLabel('Trimite')
    ->visible(fn (Invoice $record) =>
        $record->status === InvoiceStatus::Trimisa
        && empty($record->efactura_id)
        && $record->client->type === \App\Enums\ClientType::PersoanaJuridica
    )
    ->action(function (Invoice $record) {
        $company = Company::withoutGlobalScopes()->find($record->company_id);

        SubmitEfactura::dispatch($record, $company);

        Notification::make()
            ->title('Factură trimisă în coadă pentru e-Factura')
            ->body('Statusul va fi actualizat automat în câteva minute.')
            ->success()
            ->send();
    }),
```

Also add an `efactura_status` column to the table:
```php
TextColumn::make('efactura_status')
    ->label('Status e-Factura')
    ->badge()
    ->color(fn (?string $state) => match($state) {
        'ok'            => 'success',
        'nok'           => 'danger',
        'in_prelucrare' => 'warning',
        default         => 'gray',
    })
    ->formatStateUsing(fn (?string $state) => match($state) {
        'ok'            => 'Acceptat ANAF',
        'nok'           => 'Respins ANAF',
        'in_prelucrare' => 'În prelucrare',
        default         => '—',
    })
    ->toggleable(isToggledHiddenByDefault: true),
```

### Step 8 – Run migration

```bash
docker compose exec app php artisan migrate
```

---

## Acceptance Criteria
- [x] Migration adds e-Factura credential fields to `companies`.
- [x] `AnafService::generateXml()` produces valid UBL 2.1 XML with correct namespaces.
- [x] `SubmitEfactura` job uploads XML and stores the `index_incarcare` in `invoices.efactura_id`.
- [x] `PollEfacturaStatus` job runs every 10 minutes and updates `efactura_status` for pending invoices.
- [x] "Trimite la e-Factura" action is visible only for finalized invoices of juridical clients.
- [x] e-Factura status badge shows correct color: green (ok), red (nok), amber (in prelucrare).
- [x] Scheduler service in Docker (`scheduler` container) runs `php artisan schedule:work`.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| 2026-02-23 | Migration for company efactura fields; Company model update; e-Factura section in CompanyResource; AnafService extended with generateXml/uploadInvoice/pollStatus; SubmitEfactura job; PollEfacturaStatus job; scheduler registered; InvoiceResource trimite_efactura action + efactura_status badge column. Migration ran. Committed `029c24d`. | — | ✅ Complete |
