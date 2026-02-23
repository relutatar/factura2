# FACTURA2 – Products & Stock Module

## Context
Products and stock are tracked per company. NOD CONSULTING tracks DDD chemicals/substances; PAINTBALL MUREȘ tracks paintballs, CO2, and equipment. Stock is automatically deducted when an invoice is finalized via `StockService::deductForInvoice()`.

## Prerequisites
- `CompanyScope` must exist (see `01-setup.prompt.md`).
- The `Invoice` model must exist before wiring the deduction trigger (see `05-invoicing.prompt.md`).

---

## Task

### Step 1 – Create `StockMovementType` enum

Create `app/Enums/StockMovementType.php`:
```php
<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Intrare   = 'intrare';
    case Iesire    = 'iesire';
    case Ajustare  = 'ajustare';

    public function label(): string
    {
        return match($this) {
            self::Intrare  => 'Intrare',
            self::Iesire   => 'Ieșire',
            self::Ajustare => 'Ajustare',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Intrare  => 'success',
            self::Iesire   => 'danger',
            self::Ajustare => 'warning',
        };
    }
}
```

### Step 2 – Generate models and migrations

```bash
docker compose exec app php artisan make:model Product -m
docker compose exec app php artisan make:model StockMovement -m
```

**`database/migrations/xxxx_create_products_table.php`**:
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('code');
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('unit')->default('bucată');
    $table->decimal('unit_price', 15, 2)->default(0);
    $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete(); // set after VatRateSeeder runs
    $table->decimal('stock_quantity', 15, 3)->default(0);
    $table->decimal('stock_minimum', 15, 3)->default(0);
    $table->string('category')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['company_id', 'code']);
});
```

**`database/migrations/xxxx_create_stock_movements_table.php`**:
```php
Schema::create('stock_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
    $table->enum('type', ['intrare', 'iesire', 'ajustare']);
    $table->decimal('quantity', 15, 3);
    $table->decimal('unit_price', 15, 2)->nullable();
    $table->string('notes')->nullable();
    $table->datetime('moved_at');
    $table->timestamps();
    $table->softDeletes();
});
```

### Step 3 – Create the models

**`app/Models/Product.php`**:
```php
<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'description', 'unit',
        'unit_price', 'vat_rate_id', 'stock_quantity', 'stock_minimum',
        'category', 'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'unit_price'     => 'decimal:2',
        // vat_rate_id is a FK – access the rate via $product->vatRate->value
        'stock_quantity' => 'decimal:3',
        'stock_minimum'  => 'decimal:3',
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

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * Returns true when current stock is at or below the minimum threshold.
     */
    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->stock_minimum;
    }
}
```

**`app/Models/StockMovement.php`**:
```php
<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'product_id', 'invoice_id', 'type',
        'quantity', 'unit_price', 'notes', 'moved_at',
    ];

    protected $casts = [
        'type'     => StockMovementType::class,
        'moved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model) {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
        });

        // Update product stock after every new movement
        static::created(function (StockMovement $movement) {
            $delta = in_array($movement->type->value, ['iesire'])
                ? -abs($movement->quantity)
                : abs($movement->quantity);

            $movement->product()->withoutGlobalScopes()->increment('stock_quantity', $delta);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScopes();
    }
}
```

### Step 4 – Create `StockService`

Create `app/Services/StockService.php`:
```php
<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class StockService
{
    /**
     * Record an incoming stock movement (purchase/receipt).
     */
    public function recordEntry(
        Product $product,
        float $quantity,
        float $unitPrice,
        ?string $notes = null
    ): StockMovement {
        return StockMovement::create([
            'product_id' => $product->id,
            'type'       => StockMovementType::Intrare,
            'quantity'   => $quantity,
            'unit_price' => $unitPrice,
            'notes'      => $notes,
            'moved_at'   => now(),
        ]);
    }

    /**
     * Deduct stock for all lines of a finalized invoice.
     * Called when invoice status transitions to 'trimisa' or 'platita'.
     */
    public function deductForInvoice(Invoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            if (! $line->product_id) {
                continue;
            }

            StockMovement::create([
                'product_id' => $line->product_id,
                'invoice_id' => $invoice->id,
                'type'       => StockMovementType::Iesire,
                'quantity'   => $line->quantity,
                'unit_price' => $line->unit_price,
                'notes'      => "Factură {$invoice->full_number}",
                'moved_at'   => now(),
            ]);
        }
    }

    /**
     * Return all products for the active company that are below minimum stock.
     */
    public function getLowStockProducts(): Collection
    {
        return Product::whereColumn('stock_quantity', '<=', 'stock_minimum')
            ->where('is_active', true)
            ->orderByRaw('stock_quantity - stock_minimum ASC')
            ->get();
    }
}
```

### Step 5 – Create `ProductResource`

```bash
docker compose exec app php artisan make:filament-resource Product --generate
```

**Navigation properties**:
```php
protected static ?string $navigationGroup  = 'Produse & Stocuri';
protected static ?string $navigationLabel  = 'Produse';
protected static ?string $modelLabel       = 'Produs';
protected static ?string $pluralModelLabel = 'Produse';
protected static ?string $navigationIcon   = 'heroicon-o-cube';
```

**Form schema**:
```php
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('code')->label('Cod produs')->required(),
        TextInput::make('name')->label('Denumire')->required(),
        Textarea::make('description')->label('Descriere')->rows(2),
        Select::make('unit')
            ->label('UM')
            ->options([
                'bucată'  => 'Bucată',
                'litru'   => 'Litru',
                'kg'      => 'Kg',
                'cutie'   => 'Cutie',
                'set'     => 'Set',
                'doză'    => 'Doză',
            ]),
        TextInput::make('unit_price')->label('Preț unitar')->numeric()->suffix('RON')->required(),
        Select::make('vat_rate_id')
            ->label('Cotă TVA')
            ->options(\App\Models\VatRate::selectOptions()) // reads from DB – admin-manageable
            ->default(fn () => \App\Models\VatRate::defaultRate()->id)
            ->required()
            ->preload()
            ->label('Cotă TVA')
            ->options([
                21 => '21% – Standard',
                11 => '11% – Redusă (alimente, cazare, cărți)',
                0  => '0% – Scutit / Export',
            ])
            ->default(21)
            ->required(),
        TextInput::make('stock_quantity')->label('Stoc curent')->numeric(),
        TextInput::make('stock_minimum')->label('Stoc minim')->numeric()->default(0),
        TextInput::make('category')->label('Categorie'),
        Toggle::make('is_active')->label('Activ')->default(true),
    ])->columns(2);
}
```

**Table columns** (highlight low-stock rows in red):
```php
->columns([
    TextColumn::make('code')->label('Cod')->searchable()->sortable(),
    TextColumn::make('name')->label('Denumire')->searchable()->sortable(),
    TextColumn::make('category')->label('Categorie'),
    TextColumn::make('stock_quantity')
        ->label('Stoc')
        ->formatStateUsing(fn ($state, Product $record) => "{$state} {$record->unit}")
        ->color(fn (Product $record) => $record->isLowStock() ? 'danger' : null),
    TextColumn::make('stock_minimum')->label('Stoc minim'),
    TextColumn::make('unit_price')->label('Preț')->money('RON'),
    IconColumn::make('is_active')->label('Activ')->boolean(),
])
->recordClasses(fn (Product $record) => $record->isLowStock() ? 'bg-red-50 dark:bg-red-950' : null)
```

**"Înregistrează intrare stoc" action** (add to `->actions()`):
```php
Action::make('intrare_stoc')
    ->label('Înregistrează intrare')
    ->icon('heroicon-o-arrow-down-tray')
    ->form([
        TextInput::make('quantity')->label('Cantitate')->numeric()->required(),
        TextInput::make('unit_price')->label('Preț achiziție')->numeric()->suffix('RON'),
        TextInput::make('notes')->label('Observații'),
    ])
    ->action(function (Product $record, array $data) {
        app(\App\Services\StockService::class)->recordEntry(
            $record,
            $data['quantity'],
            $data['unit_price'] ?? 0,
            $data['notes'] ?? null
        );
        Notification::make()->title('Intrare stoc înregistrată')->success()->send();
    }),
```

### Step 6 – Create `StockMovementResource` (read-only)

```bash
docker compose exec app php artisan make:filament-resource StockMovement --generate
```

**Navigation properties**:
```php
protected static ?string $navigationGroup  = 'Produse & Stocuri';
protected static ?string $navigationLabel  = 'Mișcări stoc';
protected static ?string $modelLabel       = 'Mișcare stoc';
protected static ?string $pluralModelLabel = 'Mișcări stoc';
protected static ?string $navigationIcon   = 'heroicon-o-arrow-path';
```

Remove `CreateStockMovement` and `EditStockMovement` pages — this resource is **read-only**. Only list and view pages are needed:
```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListStockMovements::route('/'),
    ];
}
```

---

## Acceptance Criteria
- [x] `docker compose exec app php artisan migrate` creates `products` and `stock_movements` tables.
- [x] Creating a `StockMovement` of type `intrare` increases `products.stock_quantity`.
- [x] Creating a `StockMovement` of type `iesire` decreases `products.stock_quantity`.
- [x] Products at or below `stock_minimum` show a red row and red stock value in the table.
- [x] "Înregistrează intrare stoc" modal works and updates stock immediately.
- [x] `StockMovementResource` table is read-only (no create/edit form).
- [x] All labels and notifications are in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
| 2026-02-23 | StockMovementType enum, VatRate model + migration + seeder (21%/11%/0%), Product + StockMovement models with CompanyScope + stock auto-update trigger, StockService, ProductResource (full form/table/intrare action), VatRateResource (Configurare group), StockMovementResource (read-only list), all migrations run | — | ✅ Complete |
