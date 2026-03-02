# FACTURA2 – Facturi Proforme

## Context
Factura proformă este un document de solicitare a plății prealabil emiterii facturii fiscale. Nu are valoare contabilă, dar servește ca bază pentru documentarea comenzilor și plăților anticipate.

**Flux de lucru:**
1. Se creează o factură proformă (draft) – manual sau din contract.
2. Se trimite clientului (status → `trimisa`).
3. Clientul plătește → din proformă se generează o **factură fiscală nouă** cu liniile pre-completate. Proforma rămâne arhivată cu statusul `convertita`.

**Proforma este un model și o resursă distinctă de Invoice.** (nu sunt același model)

Numerotarea proformelor urmează regulile din modulul `09-document-numbering-ranges.prompt.md`, cu tip document `proforma`.

---

## Prerequisites
- `Client`, `Contract`, `Product`, `VatRate` modele trebuie să existe.
- `DocumentNumberService` trebuie să existe (vezi `09-document-numbering-ranges.prompt.md`).
- `InvoiceService` trebuie să existe (vezi `05-invoicing.prompt.md`).
- `CompanyScope` activ.

---

## Task

### Step 1 – Enum `ProformaStatus`

**`app/Enums/ProformaStatus.php`**:
```php
<?php

namespace App\Enums;

enum ProformaStatus: string
{
    case Draft      = 'draft';
    case Trimisa    = 'trimisa';
    case Convertita = 'convertita';
    case Anulata    = 'anulata';

    public function label(): string
    {
        return match($this) {
            self::Draft      => 'Ciornă',
            self::Trimisa    => 'Trimisă',
            self::Convertita => 'Convertită',
            self::Anulata    => 'Anulată',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft      => 'gray',
            self::Trimisa    => 'warning',
            self::Convertita => 'success',
            self::Anulata    => 'danger',
        };
    }
}
```

---

### Step 2 – Modele și migrări

```bash
docker compose exec app php artisan make:model Proforma -m
docker compose exec app php artisan make:model ProformaLine -m
```

**`database/migrations/xxxx_create_proformas_table.php`**:
```php
Schema::create('proformas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete(); // FK towards generated invoice
    $table->enum('status', ['draft', 'trimisa', 'convertita', 'anulata'])->default('draft');
    // Numerotare (alocată de DocumentNumberService la finalizare)
    $table->string('series')->nullable();
    $table->unsignedInteger('number')->nullable();
    $table->string('full_number')->nullable();
    $table->foreignId('numbering_range_id')->nullable()->constrained('numbering_ranges')->nullOnDelete();
    // Date document
    $table->date('issue_date');
    $table->date('valid_until')->nullable();    // data până la care proforma este valabilă
    // Totaluri
    $table->decimal('subtotal', 15, 2)->default(0);
    $table->decimal('vat_total', 15, 2)->default(0);
    $table->decimal('total', 15, 2)->default(0);
    $table->string('currency', 3)->default('RON');
    $table->string('pdf_path')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['company_id', 'full_number']);
});
```

**`database/migrations/xxxx_create_proforma_lines_table.php`**:
```php
Schema::create('proforma_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('proforma_id')->constrained()->cascadeOnDelete();
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

---

### Step 3 – Modele Eloquent

**`app/Models/Proforma.php`**:
```php
<?php

namespace App\Models;

use App\Enums\ProformaStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proforma extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'contract_id', 'invoice_id',
        'status', 'series', 'number', 'full_number', 'numbering_range_id',
        'issue_date', 'valid_until',
        'subtotal', 'vat_total', 'total', 'currency',
        'pdf_path', 'notes',
    ];

    protected $casts = [
        'status'      => ProformaStatus::class,
        'issue_date'  => 'date',
        'valid_until' => 'date',
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

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function client(): BelongsTo     { return $this->belongsTo(Client::class); }
    public function contract(): BelongsTo   { return $this->belongsTo(Contract::class)->withDefault(); }
    public function invoice(): BelongsTo    { return $this->belongsTo(Invoice::class)->withDefault(); }

    public function lines(): HasMany
    {
        return $this->hasMany(ProformaLine::class)->orderBy('sort_order');
    }

    public function isEditable(): bool
    {
        return $this->status === ProformaStatus::Draft;
    }
}
```

**`app/Models/ProformaLine.php`**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaLine extends Model
{
    protected $fillable = [
        'proforma_id', 'product_id', 'description', 'quantity', 'unit',
        'unit_price', 'vat_rate_id', 'vat_amount', 'line_total', 'total_with_vat', 'sort_order',
    ];

    protected $casts = [
        'quantity'       => 'decimal:3',
        'unit_price'     => 'decimal:2',
        'vat_amount'     => 'decimal:2',
        'line_total'     => 'decimal:2',
        'total_with_vat' => 'decimal:2',
    ];

    public function proforma(): BelongsTo  { return $this->belongsTo(Proforma::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class)->withoutGlobalScopes(); }
    public function vatRate(): BelongsTo   { return $this->belongsTo(VatRate::class); }
}
```

---

### Step 4 – `ProformaService`

**`app/Services/ProformaService.php`**:
```php
<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ProformaStatus;
use App\Jobs\GenerateProformaPdf;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Proforma;
use App\Models\ProformaLine;

class ProformaService
{
    /**
     * Recalculate subtotal, vat_total and total from the proforma lines.
     */
    public function recalculateTotals(Proforma $proforma): void
    {
        $proforma->loadMissing('lines.vatRate');

        $subtotal = 0;
        $vatTotal = 0;

        foreach ($proforma->lines as $line) {
            $lineTotal  = round($line->quantity * $line->unit_price, 2);
            $vatAmount  = round($lineTotal * ($line->vatRate->value / 100), 2);
            $subtotal  += $lineTotal;
            $vatTotal  += $vatAmount;
        }

        $proforma->update([
            'subtotal'  => $subtotal,
            'vat_total' => $vatTotal,
            'total'     => $subtotal + $vatTotal,
        ]);
    }

    /**
     * Emit the proforma: allocate number from DocumentNumberService and generate PDF.
     */
    public function emit(Proforma $proforma): void
    {
        if ($proforma->status !== ProformaStatus::Draft) {
            throw new \RuntimeException('Pot fi emise doar proformele în status Draft.');
        }

        // Reserve number from numbering ranges (document_type = proforma)
        $company    = $proforma->company()->withoutGlobalScopes()->find($proforma->company_id);
        $reservation = app(DocumentNumberService::class)->reserveNextNumber(
            $company, 'proforma', null, $proforma->issue_date
        );

        $proforma->update([
            'status'              => ProformaStatus::Trimisa,
            'series'              => $reservation->series,
            'number'              => $reservation->number,
            'full_number'         => $reservation->fullNumber,
            'numbering_range_id'  => $reservation->rangeId,
        ]);

        GenerateProformaPdf::dispatch($proforma);
    }

    /**
     * Convert this proforma to a new fiscal Invoice with pre-filled lines.
     * The proforma status becomes 'convertita'.
     */
    public function convertToInvoice(Proforma $proforma): Invoice
    {
        if ($proforma->status !== ProformaStatus::Trimisa) {
            throw new \RuntimeException('Proforma trebuie să fie în status Trimisă pentru a fi convertită.');
        }

        $invoice = Invoice::create([
            'company_id'     => $proforma->company_id,
            'client_id'      => $proforma->client_id,
            'contract_id'    => $proforma->contract_id,
            'proforma_id'    => $proforma->id,
            'status'         => InvoiceStatus::Draft,
            'issue_date'     => now(),
            'due_date'       => now()->addDays(30),
            'payment_method' => 'virament_bancar',
        ]);

        foreach ($proforma->lines as $line) {
            $invoice->lines()->create([
                'product_id'    => $line->product_id,
                'description'   => $line->description,
                'quantity'      => $line->quantity,
                'unit'          => $line->unit,
                'unit_price'    => $line->unit_price,
                'vat_rate_id'   => $line->vat_rate_id,
                'vat_amount'    => $line->vat_amount,
                'line_total'    => $line->line_total,
                'total_with_vat'=> $line->total_with_vat,
                'sort_order'    => $line->sort_order,
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
            'issue_date'  => now(),
            'valid_until' => now()->addDays(30),
        ]);
    }
}
```

---

### Step 5 – Job `GenerateProformaPdf`

```bash
docker compose exec app php artisan make:job GenerateProformaPdf
```

**`app/Jobs/GenerateProformaPdf.php`**:
```php
<?php

namespace App\Jobs;

use App\Models\Proforma;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateProformaPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Proforma $proforma) {}

    public function handle(): void
    {
        app(\App\Services\PdfService::class)->generateProforma($this->proforma);
    }
}
```

Adaugă în `PdfService`:
```php
public function generateProforma(Proforma $proforma): string
{
    $proforma->loadMissing(['company', 'client', 'lines.vatRate']);
    $dir  = storage_path("app/proformas/{$proforma->company_id}");
    if (! is_dir($dir)) { mkdir($dir, 0755, true); }
    $path = "{$dir}/{$proforma->full_number}.pdf";
    Pdf::loadView('pdf.proforma', compact('proforma'))->setPaper('a4')->save($path);
    $proforma->withoutGlobalScopes()->where('id', $proforma->id)->update(['pdf_path' => $path]);
    return $path;
}
```

---

### Step 6 – PDF template

**`resources/views/pdf/proforma.blade.php`**: Similar structurii `pdf/invoice.blade.php`, cu modificările:
- Titlul documentului: `FACTURĂ PROFORMĂ`
- Adăugat câmpul `Valabilă până la: {{ $proforma->valid_until?->format('d.m.Y') ?? '—' }}`
- Numărul: `Nr: {{ $proforma->full_number }}`
- Footer: _"Prezentul document nu reprezintă o factură fiscală și nu este supus înregistrării contabile."_

---

### Step 7 – Resursă Filament `ProformaResource`

```bash
docker compose exec app php artisan make:filament-resource Proforma --generate
```

**Navigation properties**:
```php
protected static ?string $navigationGroup  = 'Facturi';
protected static ?string $navigationLabel  = 'Proforme';
protected static ?string $modelLabel       = 'Proformă';
protected static ?string $pluralModelLabel = 'Proforme';
protected static ?string $navigationIcon   = 'heroicon-o-document-currency-dollar';
```

**Form schema**: Similar cu `InvoiceResource`, cu:
- Câmp suplimentar `valid_until` (data valabilității).
- Repeater pentru linii (`ProformaLine`).
- Fără câmpuri de numerotare (series/number) în form – se alocă automat la emitere.

**Table columns**:
```php
->columns([
    TextColumn::make('full_number')->label('Număr')->searchable()->sortable()->default('—'),
    TextColumn::make('client.name')->label('Client')->searchable(),
    TextColumn::make('status')->label('Status')->badge()
        ->formatStateUsing(fn ($state) => $state instanceof ProformaStatus ? $state->label() : $state)
        ->color(fn ($state) => $state instanceof ProformaStatus ? $state->color() : 'gray'),
    TextColumn::make('issue_date')->label('Data emiterii')->date('d.m.Y')->sortable(),
    TextColumn::make('valid_until')->label('Valabilă până la')->date('d.m.Y'),
    TextColumn::make('total')->label('Total')->money('RON'),
])
```

**Table actions**:
```php
->actions([
    Tables\Actions\ViewAction::make()->label('Vezi'),
    Tables\Actions\EditAction::make()->label('Editează')
        ->visible(fn (Proforma $record) => $record->status === ProformaStatus::Draft),

    Action::make('emite')
        ->label('Emite Proforma')
        ->icon('heroicon-o-paper-airplane')
        ->requiresConfirmation()
        ->modalHeading('Emite factură proformă')
        ->modalDescription('Proforma va primi un număr din plaja de proforme și va fi generată ca PDF.')
        ->modalSubmitActionLabel('Emite')
        ->visible(fn (Proforma $record) => $record->status === ProformaStatus::Draft)
        ->action(function (Proforma $record) {
            app(\App\Services\ProformaService::class)->emit($record);
            Notification::make()->title('Proformă emisă cu succes')->success()->send();
        }),

    Action::make('converteste_factura')
        ->label('Generează Factură Fiscală')
        ->icon('heroicon-o-document-plus')
        ->requiresConfirmation()
        ->modalHeading('Generare factură fiscală din proformă')
        ->modalDescription('Se va genera o factură fiscală draft cu liniile din această proformă. Proforma va rămâne arhivată.')
        ->modalSubmitActionLabel('Generează')
        ->visible(fn (Proforma $record) => $record->status === ProformaStatus::Trimisa)
        ->action(function (Proforma $record) {
            $invoice = app(\App\Services\ProformaService::class)->convertToInvoice($record);
            Notification::make()->title('Factură fiscală creată')->success()->send();
            return redirect(\App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $invoice]));
        }),

    Action::make('descarca_pdf')
        ->label('Descarcă PDF')
        ->icon('heroicon-o-arrow-down-tray')
        ->visible(fn (Proforma $record) => ! empty($record->pdf_path))
        ->url(fn (Proforma $record) => route('proformas.pdf', $record))
        ->openUrlInNewTab(),

    Action::make('anuleaza')
        ->label('Anulează')
        ->color('danger')
        ->requiresConfirmation()
        ->visible(fn (Proforma $record) => ! in_array($record->status, [ProformaStatus::Convertita, ProformaStatus::Anulata]))
        ->action(fn (Proforma $record) => $record->update(['status' => ProformaStatus::Anulata])),
])
```

---

### Step 8 – Rută PDF

```php
Route::get('/proformas/{proforma}/pdf', function (\App\Models\Proforma $proforma) {
    $path = app(\App\Services\PdfService::class)->generateProforma($proforma);
    return response()->download($path, "proforma-{$proforma->full_number}.pdf");
})->name('proformas.pdf')->middleware('auth');
```

---

## Acceptance Criteria
- [x] `proformas` și `proforma_lines` tabele create prin migrare.
- [x] Proforma poate fi creată manual sau din contract.
- [x] Emiterea proformei alocă număr din plaja `proforma` prin `InvoiceService::reserveNextNumber()` (DocumentNumberService neimplementat încă).
- [x] Conversia proformă → factură fiscală crează o factură draft cu liniile copiate; proforma devine `convertita`.
- [x] PDF proformă conține mențiunea că nu este factură fiscală.
- [x] Status badge afișat corect în tabel.
- [x] Numerotare imutabilă după emitere.
- [x] Toate etichetele și notificările sunt în **română**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
| 2026-06-01 | ProformaStatus enum, migrări (proformas, proforma_lines, proforma_id on invoices), modele Proforma + ProformaLine, ProformaService (recalculateTotals / emit / convertToInvoice / createFromContract), GenerateProformaPdf job, PdfService::generateProforma(), template PDF proforma.blade.php, ProformaResource cu paginile Create/Edit/List, ruta proformas.pdf, migrări rulate cu succes | — | DocumentNumberService neimplementat încă – se folosește InvoiceService::reserveNextNumber() ca fallback; rangeId nu se setează pe proformas.numbering_range_id |
