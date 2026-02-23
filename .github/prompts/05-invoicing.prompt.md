# FACTURA2 – Invoicing Module

## Context
Core invoicing module. Supports four document types: invoice (factura), proforma, receipt (chitanta), delivery note (aviz). Each company has its own sequential numeric series. Invoices can be created manually or auto-generated from a contract. Finalizing an invoice triggers PDF generation and stock deduction.

## Prerequisites
- `Client`, `Contract`, `Product` models must exist.
- `StockService` must exist (see `04-products-stock.prompt.md`).
- Queue (`database` driver) must be configured.

---

## Task

### Step 1 – Create the `VatRate` model (database-driven, admin-manageable)

```bash
docker compose exec app php artisan make:model VatRate -m
```

**`database/migrations/xxxx_create_vat_rates_table.php`**:
```php
Schema::create('vat_rates', function (Blueprint $table) {
    $table->id();
    $table->decimal('value', 5, 2);          // e.g. 21.00, 11.00, 0.00
    $table->string('label');                  // e.g. '21% – Standard'
    $table->string('description')->nullable(); // e.g. 'Majoritate bunuri și servicii'
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();
});
```

**`app/Models/VatRate.php`**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VatRate extends Model
{
    protected $fillable = [
        'value', 'label', 'description', 'is_default', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'value'      => 'decimal:2',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function invoiceLines(): HasMany { return $this->hasMany(InvoiceLine::class); }
    public function products(): HasMany     { return $this->hasMany(Product::class); }

    /**
     * Returns options array for Filament Select::make()->options().
     * Keyed by ID so foreign keys store the VatRate ID.
     */
    public static function selectOptions(): array
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label', 'id')
            ->all();
    }

    public static function defaultRate(): self
    {
        return static::where('is_default', true)->firstOrFail();
    }
}
```

**Seeder** – add to `DatabaseSeeder` or a dedicated `VatRateSeeder`:
```php
// database/seeders/VatRateSeeder.php
<?php

namespace Database\Seeders;

use App\Models\VatRate;
use Illuminate\Database\Seeder;

class VatRateSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            ['value' => 21.00, 'label' => '21% – Standard',  'description' => 'Majoritate bunuri și servicii (electronice, mobilier, consultanță)', 'is_default' => true,  'sort_order' => 1],
            ['value' => 11.00, 'label' => '11% – Redusă',    'description' => 'Alimente, cazare, publicații, acces muzee',                         'is_default' => false, 'sort_order' => 2],
            ['value' =>  0.00, 'label' =>  '0% – Scutit',    'description' => 'Exporturi și livrări intra-UE',                                     'is_default' => false, 'sort_order' => 3],
        ];

        foreach ($rates as $rate) {
            VatRate::updateOrCreate(
                ['value' => $rate['value']],
                $rate
            );
        }
    }
}
```

Run it:
```bash
docker compose exec app php artisan db:seed --class=VatRateSeeder
```

### Step 2 – Create `VatRateResource` (admin-manageable)

```bash
docker compose exec app php artisan make:filament-resource VatRate --generate
```

**`app/Filament/Resources/VatRateResource.php`** (key parts):
```php
protected static ?string $navigationGroup  = 'Configurare';
protected static ?string $navigationLabel  = 'Cote TVA';
protected static ?string $modelLabel       = 'Cotă TVA';
protected static ?string $pluralModelLabel = 'Cote TVA';
protected static ?string $navigationIcon   = 'heroicon-o-percent-badge';

public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('value')
            ->label('Valoare (%)')
            ->numeric()
            ->minValue(0)
            ->maxValue(100)
            ->step(0.01)
            ->suffix('%')
            ->required(),
        TextInput::make('label')
            ->label('Etichetă (ex: 21% – Standard)')
            ->required(),
        TextInput::make('description')
            ->label('Descriere'),
        Toggle::make('is_default')
            ->label('Cotă implicită')
            ->helperText('Bifați o singură cotă ca implicită.'),
        Toggle::make('is_active')
            ->label('Activă')
            ->default(true),
        TextInput::make('sort_order')
            ->label('Ordine afișare')
            ->numeric()
            ->default(0),
    ]);
}

public static function table(Table $table): Table
{
    return $table->columns([
        TextColumn::make('value')->label('Cotă (%)')->suffix('%')->sortable(),
        TextColumn::make('label')->label('Etichetă')->searchable(),
        TextColumn::make('description')->label('Descriere')->limit(50),
        IconColumn::make('is_default')->label('Implicită')->boolean(),
        IconColumn::make('is_active')->label('Activă')->boolean(),
    ])->defaultSort('sort_order');
}
```

### Step 3 – Create Enums

**`app/Enums/InvoiceType.php`**:
```php
<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Factura  = 'factura';
    case Proforma = 'proforma';
    case Chitanta = 'chitanta';
    case Aviz     = 'aviz';

    public function label(): string
    {
        return match($this) {
            self::Factura  => 'Factură',
            self::Proforma => 'Proformă',
            self::Chitanta => 'Chitanță',
            self::Aviz     => 'Aviz',
        };
    }

    public function prefix(): string
    {
        return match($this) {
            self::Factura  => 'F',
            self::Proforma => 'P',
            self::Chitanta => 'C',
            self::Aviz     => 'A',
        };
    }
}
```

**`app/Enums/InvoiceStatus.php`**:
```php
<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft   = 'draft';
    case Trimisa = 'trimisa';
    case Platita = 'platita';
    case Anulata = 'anulata';

    public function label(): string
    {
        return match($this) {
            self::Draft   => 'Ciornă',
            self::Trimisa => 'Trimisă',
            self::Platita => 'Plătită',
            self::Anulata => 'Anulată',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft   => 'gray',
            self::Trimisa => 'warning',
            self::Platita => 'success',
            self::Anulata => 'danger',
        };
    }
}
```

**`app/Enums/PaymentMethod.php`**:
```php
<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Numerar     = 'numerar';
    case OrdinPlata  = 'ordin_plata';
    case Card        = 'card';
    case Compensare  = 'compensare';

    public function label(): string
    {
        return match($this) {
            self::Numerar    => 'Numerar',
            self::OrdinPlata => 'Ordin de plată',
            self::Card       => 'Card bancar',
            self::Compensare => 'Compensare',
        };
    }
}
```

### Step 2 – Generate models and migrations

```bash
docker compose exec app php artisan make:model Invoice -m
docker compose exec app php artisan make:model InvoiceLine -m
```

**`database/migrations/xxxx_create_invoices_table.php`**:
```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
    $table->enum('type', ['factura', 'proforma', 'chitanta', 'aviz'])->default('factura');
    $table->enum('status', ['draft', 'trimisa', 'platita', 'anulata'])->default('draft');
    $table->string('series');
    $table->unsignedInteger('number');
    $table->string('full_number')->unique();
    $table->date('issue_date');
    $table->date('due_date')->nullable();
    $table->date('delivery_date')->nullable();
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('vat_total', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->string('currency', 3)->default('RON');
    $table->enum('payment_method', ['numerar', 'ordin_plata', 'card', 'compensare'])->default('ordin_plata');
    $table->string('payment_reference')->nullable();
    $table->datetime('paid_at')->nullable();
    $table->string('efactura_id')->nullable();
    $table->string('efactura_status')->nullable();
    $table->string('pdf_path')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

**`database/migrations/xxxx_create_invoice_lines_table.php`**:
```php
Schema::create('invoice_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
    $table->string('description');
    $table->decimal('quantity', 15, 3)->default(1);
    $table->string('unit')->default('bucată');
    $table->decimal('unit_price', 15, 2)->default(0);
    $table->foreignId('vat_rate_id')->constrained('vat_rates')->restrictOnDelete();
    $table->decimal('vat_amount', 15, 2)->default(0);
    $table->decimal('line_total', 15, 2)->default(0);
    $table->decimal('total_with_vat', 15, 2)->default(0);
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

### Step 3 – Create the models

**`app/Models/Invoice.php`**:
```php
<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'contract_id', 'type', 'status',
        'series', 'number', 'full_number', 'issue_date', 'due_date',
        'delivery_date', 'subtotal', 'vat_total', 'total', 'currency',
        'payment_method', 'payment_reference', 'paid_at',
        'efactura_id', 'efactura_status', 'pdf_path', 'notes',
    ];

    protected $casts = [
        'type'           => InvoiceType::class,
        'status'         => InvoiceStatus::class,
        'payment_method' => PaymentMethod::class,
        'issue_date'     => 'date',
        'due_date'       => 'date',
        'paid_at'        => 'datetime',
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

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function client(): BelongsTo    { return $this->belongsTo(Client::class); }
    public function contract(): BelongsTo  { return $this->belongsTo(Contract::class)->withDefault(); }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && $this->status !== InvoiceStatus::Platita
            && $this->status !== InvoiceStatus::Anulata;
    }
}
```

**`app/Models/InvoiceLine.php`**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id', 'product_id', 'description', 'quantity', 'unit',
        'unit_price', 'vat_rate_id', 'vat_amount', 'line_total', 'total_with_vat', 'sort_order',
    ];

    protected $casts = [
        'quantity'       => 'decimal:3',
        'unit_price'     => 'decimal:2',
        'vat_amount'     => 'decimal:2',
        'line_total'     => 'decimal:2',
        'total_with_vat' => 'decimal:2',
    ];

    public function invoice(): BelongsTo  { return $this->belongsTo(Invoice::class); }
    public function product(): BelongsTo  { return $this->belongsTo(Product::class)->withoutGlobalScopes(); }
    public function vatRate(): BelongsTo  { return $this->belongsTo(VatRate::class); }
}
```

### Step 4 – Create `InvoiceService`

Create `app/Services/InvoiceService.php`:
```php
<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Contract;
use App\Models\Invoice;

class InvoiceService
{
    /**
     * Get the next sequential invoice number for a given company and series.
     * Uses a DB lock to prevent duplicates under concurrent requests.
     */
    public function nextNumber(int $companyId, string $series): int
    {
        $max = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('series', $series)
            ->lockForUpdate()
            ->max('number');

        return ($max ?? 0) + 1;
    }

    /**
     * Recalculate subtotal, vat_total and total from the invoice lines.
     * Always call this after editing lines.
     */
    public function recalculateTotals(Invoice $invoice): void
    {
        $invoice->loadMissing('lines');

        $subtotal = 0;
        $vatTotal = 0;

        foreach ($invoice->lines as $line) {
            $lineTotal    = round($line->quantity * $line->unit_price, 2);
            $vatAmount    = round($lineTotal * ($line->vatRate->value / 100), 2);
            $subtotal    += $lineTotal;
            $vatTotal    += $vatAmount;
        }

        $invoice->update([
            'subtotal'  => $subtotal,
            'vat_total' => $vatTotal,
            'total'     => $subtotal + $vatTotal,
        ]);
    }

    /**
     * Transition invoice status and trigger side effects:
     * - draft → trimisa: generate PDF, deduct stock
     * - trimisa → platita: set paid_at
     * - any → anulata: no side effects allowed after
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
        $year    = now()->year;
        $series  = $company->invoice_prefix . '-' . $year;
        $number  = $this->nextNumber($company->id, $series);

        return Invoice::create([
            'company_id'     => $contract->company_id,
            'client_id'      => $contract->client_id,
            'contract_id'    => $contract->id,
            'type'           => InvoiceType::Factura,
            'status'         => InvoiceStatus::Draft,
            'series'         => $series,
            'number'         => $number,
            'full_number'    => $series . '-' . str_pad($number, 4, '0', STR_PAD_LEFT),
            'issue_date'     => now(),
            'due_date'       => now()->addDays(30),
            'payment_method' => 'ordin_plata',
        ]);
    }
}
```

### Step 5 – Create `GenerateInvoicePdf` job

```bash
docker compose exec app php artisan make:job GenerateInvoicePdf
```

**`app/Jobs/GenerateInvoicePdf.php`**:
```php
<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Invoice $invoice) {}

    public function handle(): void
    {
        app(\App\Services\PdfService::class)->generateInvoice($this->invoice);
    }
}
```

### Step 6 – Create `PdfService`

Create `app/Services/PdfService.php`:
```php
<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    public function generateInvoice(Invoice $invoice): string
    {
        $invoice->loadMissing(['company', 'client', 'lines.product']);

        $dir = storage_path("app/invoices/{$invoice->company_id}");
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = "{$dir}/{$invoice->full_number}.pdf";

        Pdf::loadView('pdf.invoice', compact('invoice'))
            ->setPaper('a4')
            ->save($path);

        $invoice->withoutGlobalScopes()->where('id', $invoice->id)
            ->update(['pdf_path' => $path]);

        return $path;
    }
}
```

### Step 7 – PDF Blade template

Create `resources/views/pdf/invoice.blade.php`:
```blade
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f5f5f5; }
        .right { text-align: right; }
        .total-row td { font-weight: bold; }
    </style>
</head>
<body>

<table style="border:none; margin-bottom: 20px;">
    <tr>
        <td style="border:none; width:50%">
            @if($invoice->company->logo)
                <img src="{{ storage_path('app/public/' . $invoice->company->logo) }}" height="60">
            @endif
            <strong>{{ $invoice->company->name }}</strong><br>
            CIF: {{ $invoice->company->cif }}<br>
            Reg. Com.: {{ $invoice->company->reg_com }}<br>
            {{ $invoice->company->address }}<br>
            IBAN: {{ $invoice->company->iban }} | {{ $invoice->company->bank }}
        </td>
        <td style="border:none; text-align:right;">
            <h2>{{ strtoupper($invoice->type->label()) }}</h2>
            Nr: <strong>{{ $invoice->full_number }}</strong><br>
            Data: {{ $invoice->issue_date->format('d.m.Y') }}<br>
            Scadență: {{ $invoice->due_date?->format('d.m.Y') ?? '—' }}
        </td>
    </tr>
</table>

<h3>Client:</h3>
<p>
    {{ $invoice->client->name }}<br>
    CIF: {{ $invoice->client->cif ?? $invoice->client->cnp }}<br>
    {{ $invoice->client->address }}, {{ $invoice->client->city }}
</p>

<table>
    <thead>
        <tr>
            <th>#</th><th>Descriere</th><th>UM</th><th>Cant.</th>
            <th class="right">Preț/um</th><th class="right">TVA %</th>
            <th class="right">Valoare fără TVA</th><th class="right">Total cu TVA</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->lines as $i => $line)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $line->description }}</td>
            <td>{{ $line->unit }}</td>
            <td class="right">{{ number_format($line->quantity, 2, ',', '.') }}</td>
            <td class="right">{{ number_format($line->unit_price, 2, ',', '.') }}</td>
            <td class="right">{{ $line->vatRate->label }}</td>
            <td class="right">{{ number_format($line->line_total, 2, ',', '.') }}</td>
            <td class="right">{{ number_format($line->total_with_vat, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="6" class="right">Subtotal:</td><td colspan="2" class="right">{{ number_format($invoice->subtotal, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
        <tr><td colspan="6" class="right">TVA:</td><td colspan="2" class="right">{{ number_format($invoice->vat_total, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
        <tr class="total-row"><td colspan="6" class="right">TOTAL:</td><td colspan="2" class="right">{{ number_format($invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
    </tfoot>
</table>

@if($invoice->notes)
    <p><strong>Observații:</strong> {{ $invoice->notes }}</p>
@endif

<p>Modalitate plată: {{ $invoice->payment_method->label() }}</p>

</body>
</html>
```

### Step 8 – Create `InvoiceResource`

```bash
docker compose exec app php artisan make:filament-resource Invoice --generate
```

**Navigation properties**:
```php
protected static ?string $navigationGroup  = 'Facturi';
protected static ?string $navigationLabel  = 'Facturi';
protected static ?string $modelLabel       = 'Factură';
protected static ?string $pluralModelLabel = 'Facturi';
protected static ?string $navigationIcon   = 'heroicon-o-receipt-percent';
```

**Table columns** (key ones):
```php
->columns([
    TextColumn::make('full_number')->label('Număr')->searchable()->sortable(),
    TextColumn::make('client.name')->label('Client')->searchable()->sortable(),
    TextColumn::make('type')->label('Tip')->badge()
        ->formatStateUsing(fn ($state) => $state instanceof InvoiceType ? $state->label() : $state),
    TextColumn::make('status')->label('Status')->badge()
        ->formatStateUsing(fn ($state) => $state instanceof InvoiceStatus ? $state->label() : $state)
        ->color(fn ($state) => $state instanceof InvoiceStatus ? $state->color() : 'gray'),
    TextColumn::make('issue_date')->label('Data emiterii')->date('d.m.Y')->sortable(),
    TextColumn::make('due_date')->label('Scadență')->date('d.m.Y')->sortable()
        ->color(fn (Invoice $record) => $record->isOverdue() ? 'danger' : null),
    TextColumn::make('total')->label('Total')->money('RON')->sortable(),
])
->recordClasses(fn (Invoice $record) => $record->isOverdue() ? 'bg-red-50 dark:bg-red-950' : null)
```

**Table actions** – include status transitions:
> ⚠️ In the InvoiceLines repeater, use a database-driven Select for VAT rates:
> ```php
> Select::make('vat_rate_id')
>     ->label('Cotă TVA')
>     ->options(VatRate::selectOptions()) // from App\Models\VatRate — reads from DB
>     ->default(fn () => \App\Models\VatRate::defaultRate()->id)
>     ->required()
>     ->preload(),
> ```

```php
->actions([
    Tables\Actions\ViewAction::make()->label('Vezi'),
    Tables\Actions\EditAction::make()->label('Editează')
        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft),

    Action::make('finalizeaza')
        ->label('Finalizează')
        ->icon('heroicon-o-check-circle')
        ->requiresConfirmation()
        ->modalHeading('Finalizează factura')
        ->modalDescription('Factura va fi trimisă, PDF-ul va fi generat și stocul va fi dedus.')
        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft)
        ->action(fn (Invoice $record) => app(InvoiceService::class)->transition($record, InvoiceStatus::Trimisa)),

    Action::make('marcheaza_platita')
        ->label('Marchează ca plătită')
        ->icon('heroicon-o-banknotes')
        ->requiresConfirmation()
        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Trimisa)
        ->action(fn (Invoice $record) => app(InvoiceService::class)->transition($record, InvoiceStatus::Platita)),

    Action::make('anuleaza')
        ->label('Anulează')
        ->icon('heroicon-o-x-circle')
        ->color('danger')
        ->requiresConfirmation()
        ->visible(fn (Invoice $record) => ! in_array($record->status, [InvoiceStatus::Anulata]))
        ->action(fn (Invoice $record) => app(InvoiceService::class)->transition($record, InvoiceStatus::Anulata)),

    Action::make('descarca_pdf')
        ->label('Descarcă PDF')
        ->icon('heroicon-o-arrow-down-tray')
        ->visible(fn (Invoice $record) => ! empty($record->pdf_path))
        ->url(fn (Invoice $record) => route('invoices.pdf', $record))
        ->openUrlInNewTab(),
])
```

---

## Acceptance Criteria
- [x] `docker compose exec app php artisan migrate` creates `invoices` and `invoice_lines` tables.
- [x] Invoice numbering is sequential per company/series with no gaps (e.g. `NOD-2026-0001`).
- [x] Totals are always computed from lines — never entered manually.
- [x] Finalizing a draft invoice dispatches `GenerateInvoicePdf` job and deducts stock.
- [x] PDF is saved to `storage/app/invoices/{company_id}/` and linked to the invoice.
- [x] Overdue invoices are highlighted red in the table.
- [x] Status transition buttons appear/hide based on current status.
- [x] All labels and notifications are in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
| 2026-02-23 | InvoiceType/InvoiceStatus/PaymentMethod enums, Invoice+InvoiceLine models, alter-invoices migration (added full_number/subtotal/vat_total/total/payment_method etc.), invoice_lines migration, InvoiceService (nextNumber, recalculateTotals, transition, createFromContract), PdfService (generateInvoice), GenerateInvoicePdf job, invoice.blade.php PDF template, full InvoiceResource (tabs form, repeater lines, status badge table, 5 table actions), PDF download route | — | ✅ Complete |
