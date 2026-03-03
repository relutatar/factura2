# FACTURA2 – Chitanțe (Receipts)

## Context
Chitanța este un document fiscal care confirmă încasarea în numerar a sumei de pe o factură. Conform regulilor stabilite:

- **Chitanța se generează MANUAL** prin butonul **„Generare chitanță"** din `InvoiceResource`, disponibil pe facturile cu `status = platita` și `payment_method = numerar` care nu au deja o chitanță emisă.
- Dacă modalitatea de plată este `virament_bancar`, butonul nu apare și nu se poate genera chitanță.
- Chitanța este un **model și resursă distinctă** față de Invoice.
- Numerotarea chitanțelor urmează regulile din `09-document-numbering-ranges.prompt.md`, cu tip document `chitanta`.
- Odată emisă, chitanța nu poate fi editată – doar anulată.

---

## Prerequisites
- `Invoice` model și `InvoiceService` trebuie să existe (vezi `05-invoicing.prompt.md`).
- `PaymentMethod` enum cu valorile `virament_bancar` și `numerar` trebuie să existe.
- `DocumentNumberService` trebuie să existe (vezi `09-document-numbering-ranges.prompt.md`).
- `PdfService` existent.
- `CompanyScope` activ.

---

## Task

### Step 1 – Enum `ReceiptStatus`

**`app/Enums/ReceiptStatus.php`**:
```php
<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Emisa  = 'emisa';
    case Anulata = 'anulata';

    public function label(): string
    {
        return match($this) {
            self::Emisa   => 'Emisă',
            self::Anulata => 'Anulată',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Emisa   => 'success',
            self::Anulata => 'danger',
        };
    }
}
```

---

### Step 2 – Model și migrare

```bash
docker compose exec app php artisan make:model Receipt -m
```

**`database/migrations/xxxx_create_receipts_table.php`**:
```php
Schema::create('receipts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->enum('status', ['emisa', 'anulata'])->default('emisa');
    // Numerotare (alocată de DocumentNumberService)
    $table->string('series');
    $table->unsignedInteger('number');
    $table->string('full_number');
    $table->foreignId('numbering_range_id')->nullable()->constrained('numbering_ranges')->nullOnDelete();
    // Date document
    $table->date('issue_date');
    $table->decimal('amount', 15, 2);           // suma încasată (= invoice.total)
    $table->string('currency', 3)->default('RON');
    $table->string('received_by')->nullable();   // persoana care a primit banii
    $table->string('pdf_path')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['company_id', 'full_number']);
});
```

---

### Step 3 – Model Eloquent

**`app/Models/Receipt.php`**:
```php
<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'invoice_id', 'status',
        'series', 'number', 'full_number', 'numbering_range_id',
        'issue_date', 'amount', 'currency',
        'received_by', 'pdf_path', 'notes',
    ];

    protected $casts = [
        'status'     => ReceiptStatus::class,
        'issue_date' => 'date',
        'amount'     => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model) {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScopes();
    }
}
```

---

### Step 4 – `ReceiptService`

**`app/Services/ReceiptService.php`**:
```php
<?php

namespace App\Services;

use App\Enums\ReceiptStatus;
use App\Jobs\GenerateReceiptPdf;
use App\Models\Invoice;
use App\Models\Receipt;

class ReceiptService
{
    /**
     * Create a receipt for a paid cash invoice.
     * Called manually via the „Generare chitanță" action in InvoiceResource.
     * Invoice must have status = platita and payment_method = numerar.
     *
     * If no active numbering range exists for 'chitanta', a RuntimeException is thrown.
     */
    public function createForInvoice(Invoice $invoice): Receipt
    {
        $company = $invoice->company()->withoutGlobalScopes()->find($invoice->company_id);

        // Reserve number from DocumentNumberService
        $reservation = app(DocumentNumberService::class)->reserveNextNumber(
            $company, 'chitanta', null, $invoice->paid_at ?? now()
        );

        $receipt = Receipt::create([
            'company_id'         => $invoice->company_id,
            'invoice_id'         => $invoice->id,
            'status'             => ReceiptStatus::Emisa,
            'series'             => $reservation->series,
            'number'             => $reservation->number,
            'full_number'        => $reservation->fullNumber,
            'numbering_range_id' => $reservation->rangeId,
            'issue_date'         => $invoice->paid_at ?? now(),
            'amount'             => $invoice->total,
            'currency'           => $invoice->currency,
        ]);

        GenerateReceiptPdf::dispatch($receipt);

        return $receipt;
    }

    /**
     * Cancel a receipt (status → anulata).
     * Note: cancelling a receipt does NOT automatically cancel the invoice payment.
     */
    public function cancel(Receipt $receipt): void
    {
        if ($receipt->status === ReceiptStatus::Anulata) {
            throw new \RuntimeException('Chitanța este deja anulată.');
        }

        $receipt->update(['status' => ReceiptStatus::Anulata]);
    }
}
```

---

### Step 5 – Job `GenerateReceiptPdf`

```bash
docker compose exec app php artisan make:job GenerateReceiptPdf
```

**`app/Jobs/GenerateReceiptPdf.php`**:
```php
<?php

namespace App\Jobs;

use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReceiptPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Receipt $receipt) {}

    public function handle(): void
    {
        app(\App\Services\PdfService::class)->generateReceipt($this->receipt);
    }
}
```

Adaugă în `PdfService`:
```php
public function generateReceipt(Receipt $receipt): string
{
    $receipt->loadMissing(['company', 'invoice.client']);
    $dir  = storage_path("app/receipts/{$receipt->company_id}");
    if (! is_dir($dir)) { mkdir($dir, 0755, true); }
    $path = "{$dir}/{$receipt->full_number}.pdf";
    Pdf::loadView('pdf.receipt', compact('receipt'))->setPaper('a4')->save($path);
    $receipt->withoutGlobalScopes()->where('id', $receipt->id)->update(['pdf_path' => $path]);
    return $path;
}
```

---

### Step 6 – PDF template

**`resources/views/pdf/receipt.blade.php`**:
```blade
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 40px; }
        h1 { font-size: 16px; text-align: center; text-transform: uppercase; margin-bottom: 5px; }
        .number { text-align: center; font-size: 13px; margin-bottom: 20px; }
        table.info { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table.info td { padding: 6px; vertical-align: top; }
        .amount-box { border: 2px solid #333; text-align: center; padding: 20px; font-size: 18px; font-weight: bold; margin: 20px 0; }
        .signatures { margin-top: 60px; }
        .signatures table { width: 100%; }
        .signatures td { width: 50%; vertical-align: top; text-align: center; }
    </style>
</head>
<body>
    @if($receipt->company->logo)
        <img src="{{ storage_path('app/public/' . $receipt->company->logo) }}" height="50" style="margin-bottom: 10px;">
    @endif
    <h1>CHITANȚĂ</h1>
    <p class="number">Nr. <strong>{{ $receipt->full_number }}</strong> &nbsp;|&nbsp; Data: <strong>{{ $receipt->issue_date->format('d.m.Y') }}</strong></p>

    <table class="info">
        <tr>
            <td><strong>Furnizor (primitor):</strong></td>
            <td>{{ $receipt->company->name }}<br>CIF: {{ $receipt->company->cif }}<br>{{ $receipt->company->address }}</td>
        </tr>
        <tr>
            <td><strong>Client (plătitor):</strong></td>
            <td>{{ $receipt->invoice->client->name }}<br>CIF: {{ $receipt->invoice->client->cif ?? $receipt->invoice->client->cnp ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Factura aferentă:</strong></td>
            <td>Nr. {{ $receipt->invoice->full_number }} din {{ $receipt->invoice->issue_date->format('d.m.Y') }}</td>
        </tr>
    </table>

    <div class="amount-box">
        Am primit suma de:
        {{ number_format($receipt->amount, 2, ',', '.') }} {{ $receipt->currency }}
        ({{ \App\Helpers\NumberToWords::toRomanian($receipt->amount) }} {{ $receipt->currency }})
    </div>

    <p style="text-align: center;">Modalitate de plată: <strong>Numerar</strong></p>

    <div class="signatures">
        <table>
            <tr>
                <td><strong>Primit,</strong><br><br>{{ $receipt->received_by ?? $receipt->company->name }}<br><br>Semnătură: _______________</td>
                <td><strong>Plătit,</strong><br><br>{{ $receipt->invoice->client->name }}<br><br>Semnătură: _______________</td>
            </tr>
        </table>
    </div>
</body>
</html>
```

> **Notă:** Funcția `NumberToWords::toRomanian()` este opțională (conversie sumă în litere în română). Se poate implementa ca helper sau omite în prima versiune.

---

### Step 7 – Resursă Filament `ReceiptResource`

```bash
docker compose exec app php artisan make:filament-resource Receipt --generate
```

**Navigation properties**:
```php
protected static ?string $navigationGroup  = 'Facturi';
protected static ?string $navigationLabel  = 'Chitanțe';
protected static ?string $modelLabel       = 'Chitanță';
protected static ?string $pluralModelLabel = 'Chitanțe';
protected static ?string $navigationIcon   = 'heroicon-o-banknotes';
```

> Resursa este **read-only**. Chitanțele nu pot fi create manual (se generează automat). Se permite doar vizualizarea și anularea.

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListReceipts::route('/'),
        'view'  => Pages\ViewReceipt::route('/{record}'),
    ];
}
```

**Table columns**:
```php
->columns([
    TextColumn::make('full_number')->label('Număr')->searchable()->sortable(),
    TextColumn::make('invoice.full_number')->label('Factură aferentă')->searchable(),
    TextColumn::make('invoice.client.name')->label('Client')->searchable(),
    TextColumn::make('issue_date')->label('Data emiterii')->date('d.m.Y')->sortable(),
    TextColumn::make('amount')->label('Sumă')->money('RON'),
    TextColumn::make('status')->label('Status')->badge()
        ->formatStateUsing(fn ($state) => $state instanceof ReceiptStatus ? $state->label() : $state)
        ->color(fn ($state) => $state instanceof ReceiptStatus ? $state->color() : 'gray'),
])
->defaultSort('issue_date', 'desc')
```

**Actions**:
```php
->actions([
    Tables\Actions\ViewAction::make()->label('Vezi'),
    Action::make('descarca_pdf')
        ->label('Descarcă PDF')
        ->icon('heroicon-o-arrow-down-tray')
        ->visible(fn (Receipt $record) => ! empty($record->pdf_path))
        ->url(fn (Receipt $record) => route('receipts.pdf', $record))
        ->openUrlInNewTab(),
    Action::make('anuleaza')
        ->label('Anulează')
        ->color('danger')
        ->requiresConfirmation()
        ->visible(fn (Receipt $record) => $record->status === ReceiptStatus::Emisa)
        ->action(function (Receipt $record) {
            app(\App\Services\ReceiptService::class)->cancel($record);
            Notification::make()->title('Chitanță anulată')->warning()->send();
        }),
])
```

---

### Step 8 – Integrare cu InvoiceResource

În `InvoiceResource`, adaugă coloana și acțiunea de vizualizare chitanță:

**Coloană tabel** (vizibilă dacă există chitanță):
```php
TextColumn::make('receipt.full_number')
    ->label('Chitanță')
    ->default('—')
    ->url(fn (Invoice $record) => $record->receipt
        ? \App\Filament\Resources\ReceiptResource::getUrl('view', ['record' => $record->receipt])
        : null)
    ->toggleable(isToggledHiddenByDefault: true),
```

---

### Step 9 – Rute PDF

```php
Route::get('/receipts/{receipt}/pdf', function (\App\Models\Receipt $receipt) {
    $path = app(\App\Services\PdfService::class)->generateReceipt($receipt);
    return response()->download($path, "chitanta-{$receipt->full_number}.pdf");
})->name('receipts.pdf')->middleware('auth');
```

---

## Acceptance Criteria
- [x] `receipts` tabel creat prin migrare.
- [x] Chitanța se generează prin butonul „Generare chitanță" din `InvoiceResource`, vizibil doar pe facturile `platita` + `payment_method = numerar` fără chitanță deja emisă.
- [x] Dacă nu există plajă activă de chitanțe pentru an, butonul afișează o notificare de eroare explicită.
- [x] Numerotarea chitanțelor este imutabilă după emitere.
- [x] PDF chitanță conține: firmă, client, factură aferentă, sumă, dată, semnătura.
- [x] `ReceiptResource` este read-only (fără Create/Edit).
- [x] Anulare chitanță posibilă din resursă.
- [x] Coloana `Chitanță` vizibilă în `InvoiceResource` (link către chitanță).
- [x] Toate etichetele și notificările sunt în **română**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
| 2026-03-03 | Implemented complete receipts module: `ReceiptStatus` enum, `receipts` migration, `Receipt` model with CompanyScope + immutable numbering guard, `ReceiptService`, `GenerateReceiptPdf` job, receipt PDF template, `ReceiptResource` read-only (index + view + cancel + download), receipt PDF route, and invoice integration (`Generare chitanță` action + `Chitanță` column link) | — | ✅ Complete |
