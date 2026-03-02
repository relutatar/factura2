# FACTURA2 – Bonuri Fiscale (Modul Paintball)

## Context
Firma de Paintball vinde pachete de servicii (ex: "Pachet Paintball 100 bile", "Pachet Paintball 200 bile") prin **casă de marcat fizică** (hardware extern). Aplicația nu emite ea bonul fiscal – acesta este emis de casa de marcat.

**Rolul aplicației** este de a:
1. Permite operatorului să **înregistreze manual** ce s-a vândut pe un bon fiscal (ce pachete, pentru câți jucători).
2. **Genera automat un bon de consum** (ConsumptionNote) pentru cantitatea de bile consumate aferente bonului fiscal.
3. Ținea evidența **stocului de bile** (materiale consumabile) descăzut prin bonul de consum.

**Modulul este activ doar dacă firma are modulul `bonuri_fiscale` activat.**

---

## Prerequisites
- `Product` model cu câmpul `is_consumable` trebuie să existe (vezi `04-products-stock.prompt.md` și `08-consum-bon-consum.prompt.md`).
- `ConsumptionNote` model și `ConsumptionService` trebuie să existe (vezi `08-consum-bon-consum.prompt.md`).
- `CompanyScope` activ.
- Modulul `bonuri_fiscale` activat pe firma PAINTBALL MUREȘ.

---

## Task

### Step 1 – Enum `FiscalReceiptStatus`

**`app/Enums/FiscalReceiptStatus.php`**:
```php
<?php

namespace App\Enums;

enum FiscalReceiptStatus: string
{
    case Inregistrat = 'inregistrat';
    case Anulat      = 'anulat';

    public function label(): string
    {
        return match($this) {
            self::Inregistrat => 'Înregistrat',
            self::Anulat      => 'Anulat',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Inregistrat => 'success',
            self::Anulat      => 'danger',
        };
    }
}
```

---

### Step 2 – Modele și migrări

```bash
docker compose exec app php artisan make:model FiscalReceipt -m
docker compose exec app php artisan make:model FiscalReceiptLine -m
```

**`database/migrations/xxxx_create_fiscal_receipts_table.php`**:
```php
Schema::create('fiscal_receipts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    // Referință opțională la bonul de consum generat automat
    $table->foreignId('consumption_note_id')->nullable()->constrained('consumption_notes')->nullOnDelete();
    $table->enum('status', ['inregistrat', 'anulat'])->default('inregistrat');
    // Datele bonului fiscal (emis de casa de marcat externă)
    $table->date('receipt_date');
    $table->string('cash_register_number')->nullable();   // numărul casei de marcat
    $table->string('fiscal_receipt_number')->nullable();  // numărul de pe bonul fizic
    $table->decimal('total_amount', 15, 2)->default(0);   // suma totală de pe bon
    $table->string('currency', 3)->default('RON');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

**`database/migrations/xxxx_create_fiscal_receipt_lines_table.php`**:
```php
Schema::create('fiscal_receipt_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fiscal_receipt_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();  // pachetul de servicii
    $table->string('description');                         // ex: "Pachet Paintball 100 bile"
    $table->decimal('quantity', 15, 3)->default(1);        // nr. pachete vândute
    $table->string('unit')->default('pachet');
    $table->decimal('unit_price', 15, 2)->default(0);
    $table->decimal('line_total', 15, 2)->default(0);
    // Consum asociat (bile)
    $table->decimal('consumable_quantity', 15, 3)->nullable(); // cantitate consumabila per pachet (ex: 100 bile/pachet)
    $table->foreignId('consumable_product_id')->nullable()->constrained('products')->nullOnDelete(); // produsul consumabil (bile)
    $table->timestamps();
});
```

---

### Step 3 – Modele Eloquent

**`app/Models/FiscalReceipt.php`**:
```php
<?php

namespace App\Models;

use App\Enums\FiscalReceiptStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'consumption_note_id', 'status',
        'receipt_date', 'cash_register_number', 'fiscal_receipt_number',
        'total_amount', 'currency', 'notes',
    ];

    protected $casts = [
        'status'       => FiscalReceiptStatus::class,
        'receipt_date' => 'date',
        'total_amount' => 'decimal:2',
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

    public function consumptionNote(): BelongsTo
    {
        return $this->belongsTo(ConsumptionNote::class)->withoutGlobalScopes();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FiscalReceiptLine::class);
    }
}
```

**`app/Models/FiscalReceiptLine.php`**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalReceiptLine extends Model
{
    protected $fillable = [
        'fiscal_receipt_id', 'product_id', 'description', 'quantity',
        'unit', 'unit_price', 'line_total',
        'consumable_quantity', 'consumable_product_id',
    ];

    protected $casts = [
        'quantity'            => 'decimal:3',
        'unit_price'          => 'decimal:2',
        'line_total'          => 'decimal:2',
        'consumable_quantity' => 'decimal:3',
    ];

    public function fiscalReceipt(): BelongsTo      { return $this->belongsTo(FiscalReceipt::class); }
    public function product(): BelongsTo            { return $this->belongsTo(Product::class)->withoutGlobalScopes(); }
    public function consumableProduct(): BelongsTo  { return $this->belongsTo(Product::class, 'consumable_product_id')->withoutGlobalScopes(); }
}
```

---

### Step 4 – `FiscalReceiptService`

**`app/Services/FiscalReceiptService.php`**:
```php
<?php

namespace App\Services;

use App\Enums\FiscalReceiptStatus;
use App\Models\FiscalReceipt;
use Illuminate\Support\Facades\DB;

class FiscalReceiptService
{
    /**
     * Register a fiscal receipt and auto-generate a ConsumptionNote
     * for all consumable lines (e.g. paintballs used).
     *
     * @throws \RuntimeException if insufficient stock for consumables
     */
    public function register(FiscalReceipt $fiscalReceipt): void
    {
        DB::transaction(function () use ($fiscalReceipt) {
            $fiscalReceipt->loadMissing(['lines.consumableProduct', 'lines.product']);

            // Collect consumable lines to create a ConsumptionNote
            $consumableLines = $fiscalReceipt->lines->filter(
                fn ($line) => $line->consumable_product_id && $line->consumable_quantity > 0
            );

            if ($consumableLines->isNotEmpty()) {
                $consumptionData = [
                    'company_id'   => $fiscalReceipt->company_id,
                    'issued_at'    => $fiscalReceipt->receipt_date,
                    'reason'       => "Bon fiscal #{$fiscalReceipt->fiscal_receipt_number} din {$fiscalReceipt->receipt_date->format('d.m.Y')}",
                    'context_type' => 'fiscal_receipt',
                    'context_id'   => $fiscalReceipt->id,
                    'lines'        => $consumableLines->map(fn ($line) => [
                        'product_id' => $line->consumable_product_id,
                        'quantity'   => $line->quantity * $line->consumable_quantity,
                        'uom'        => $line->consumableProduct?->unit ?? 'bucată',
                    ])->values()->all(),
                ];

                $consumptionNote = app(ConsumptionService::class)->createDraft($consumptionData);
                app(ConsumptionService::class)->post($consumptionNote);

                $fiscalReceipt->update([
                    'consumption_note_id' => $consumptionNote->id,
                    'status'              => FiscalReceiptStatus::Inregistrat,
                ]);
            } else {
                $fiscalReceipt->update(['status' => FiscalReceiptStatus::Inregistrat]);
            }
        });
    }

    /**
     * Cancel a fiscal receipt. Also cancels the associated ConsumptionNote if it exists.
     */
    public function cancel(FiscalReceipt $fiscalReceipt): void
    {
        DB::transaction(function () use ($fiscalReceipt) {
            if ($fiscalReceipt->consumptionNote) {
                app(ConsumptionService::class)->cancel($fiscalReceipt->consumptionNote);
            }

            $fiscalReceipt->update(['status' => FiscalReceiptStatus::Anulat]);
        });
    }
}
```

---

### Step 5 – Resursă Filament `FiscalReceiptResource`

```bash
docker compose exec app php artisan make:filament-resource FiscalReceipt --generate
```

**Navigation properties**:
```php
protected static ?string $navigationGroup  = 'Vânzări';
protected static ?string $navigationLabel  = 'Bonuri Fiscale';
protected static ?string $modelLabel       = 'Bon Fiscal';
protected static ?string $pluralModelLabel = 'Bonuri Fiscale';
protected static ?string $navigationIcon   = 'heroicon-o-receipt-refund';
```

**Vizibilitate condiționată pe modul**:
```php
public static function canAccess(): bool
{
    $company = \App\Models\Company::find(session('active_company_id'));
    return $company?->hasModule('bonuri_fiscale') ?? false;
}
```

**Form schema** (tabs: Date bon / Linii pachete):
```php
Tabs::make()->tabs([
    Tabs\Tab::make('Date bon fiscal')->schema([
        DatePicker::make('receipt_date')
            ->label('Data bonului')
            ->required()
            ->displayFormat('d.m.Y')
            ->default(now()),
        TextInput::make('fiscal_receipt_number')
            ->label('Nr. bon (de pe casa de marcat)')
            ->required(),
        TextInput::make('cash_register_number')
            ->label('Nr. casă de marcat')
            ->nullable(),
        Textarea::make('notes')->label('Observații')->rows(2),
    ]),
    Tabs\Tab::make('Pachete vândute')->schema([
        Repeater::make('lines')
            ->relationship()
            ->label('Linii bon')
            ->schema([
                Select::make('product_id')
                    ->label('Pachet / Serviciu')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('description')
                    ->label('Descriere')
                    ->required(),
                TextInput::make('quantity')
                    ->label('Cantitate')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('unit_price')
                    ->label('Preț/um')
                    ->numeric()
                    ->suffix('RON')
                    ->required(),
                // Consum asociat (bile)
                Select::make('consumable_product_id')
                    ->label('Produs consumabil (ex: bile)')
                    ->relationship('consumableProduct', 'name',
                        fn ($query) => $query->where('is_consumable', true))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Produsul consumat (ex: bile de paintball)'),
                TextInput::make('consumable_quantity')
                    ->label('Cantitate consumabil per pachet')
                    ->numeric()
                    ->nullable()
                    ->helperText('Ex: 100 bile per pachet'),
            ])
            ->columns(3)
            ->afterStateUpdated(function ($state, $set, $get) {
                // Recalculează total bon
                $total = collect($get('lines') ?? [])->sum(fn ($l) => ($l['quantity'] ?? 0) * ($l['unit_price'] ?? 0));
                $set('total_amount', round($total, 2));
            }),
    ]),
])->columnSpanFull()
```

**Table columns**:
```php
->columns([
    TextColumn::make('fiscal_receipt_number')->label('Nr. bon')->searchable()->sortable(),
    TextColumn::make('receipt_date')->label('Data')->date('d.m.Y')->sortable(),
    TextColumn::make('total_amount')->label('Total')->money('RON'),
    TextColumn::make('consumptionNote.number')
        ->label('Bon consum')
        ->default('—'),
    TextColumn::make('status')->label('Status')->badge()
        ->formatStateUsing(fn ($state) => $state instanceof FiscalReceiptStatus ? $state->label() : $state)
        ->color(fn ($state) => $state instanceof FiscalReceiptStatus ? $state->color() : 'gray'),
])
->defaultSort('receipt_date', 'desc')
```

**Actions**:
```php
->actions([
    Tables\Actions\ViewAction::make()->label('Vezi'),
    Tables\Actions\EditAction::make()->label('Editează')
        ->visible(fn (FiscalReceipt $record) => $record->status === FiscalReceiptStatus::Inregistrat
            && ! $record->consumption_note_id),
    Action::make('inregistreaza')
        ->label('Înregistrează')
        ->icon('heroicon-o-check-circle')
        ->requiresConfirmation()
        ->modalHeading('Înregistrare bon și generare bon de consum')
        ->modalDescription('Se va genera automat un bon de consum pentru consumabilele din liniile bonului fiscal.')
        ->modalSubmitActionLabel('Înregistrează')
        ->visible(fn (FiscalReceipt $record) => $record->status === FiscalReceiptStatus::Inregistrat
            && ! $record->consumption_note_id)
        ->action(function (FiscalReceipt $record) {
            try {
                app(\App\Services\FiscalReceiptService::class)->register($record);
                Notification::make()->title('Bon înregistrat, bon de consum generat')->success()->send();
            } catch (\RuntimeException $e) {
                Notification::make()->title('Eroare la înregistrare')->body($e->getMessage())->danger()->send();
            }
        }),
    Action::make('anuleaza')
        ->label('Anulează')
        ->color('danger')
        ->requiresConfirmation()
        ->visible(fn (FiscalReceipt $record) => $record->status === FiscalReceiptStatus::Inregistrat)
        ->action(function (FiscalReceipt $record) {
            app(\App\Services\FiscalReceiptService::class)->cancel($record);
            Notification::make()->title('Bon anulat')->warning()->send();
        }),
])
```

---

## Acceptance Criteria
- [ ] `fiscal_receipts` și `fiscal_receipt_lines` tabele create prin migrare.
- [ ] Modulul `bonuri_fiscale` controlează vizibilitatea `FiscalReceiptResource`.
- [ ] La înregistrarea unui bon fiscal, dacă există linii cu consumabil definit, se generează automat un `ConsumptionNote` (bon de consum postat).
- [ ] Bonul de consum scade stocul de consumabile (bile).
- [ ] Anularea bonului fiscal anulează și bonul de consum aferent.
- [ ] Fără consum definit pe linii, bonul se înregistrează fără bon de consum.
- [ ] Toate etichetele și notificările sunt în **română**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
