# FACTURA2 – Contracts Module

## Context
Contracts are the core business documents linking clients to recurring services. NOD CONSULTING uses DDD pest-control maintenance contracts; PAINTBALL MUREȘ uses event/session contracts. The form uses conditional tabs to show only the relevant fields per contract type.

## Prerequisites
- `Client` model and `ClientResource` must exist (see `02-clients-contacts.prompt.md`).
- `Invoice` model must exist before wiring the "Generează Factură" action (see `05-invoicing.prompt.md`). Create a stub first if needed.

---

## Task

### Step 1 – Create Enums

**`app/Enums/ContractType.php`**:
```php
<?php

namespace App\Enums;

enum ContractType: string
{
    case MentenantaDDD      = 'mentenanta_ddd';
    case EvenimentPaintball = 'eveniment_paintball';

    public function label(): string
    {
        return match($this) {
            self::MentenantaDDD      => 'Mentenanță DDD',
            self::EvenimentPaintball => 'Eveniment Paintball',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::MentenantaDDD      => 'info',
            self::EvenimentPaintball => 'warning',
        };
    }
}
```

**`app/Enums/ContractStatus.php`**:
```php
<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Activ     = 'activ';
    case Suspendat = 'suspendat';
    case Expirat   = 'expirat';
    case Reziliat  = 'reziliat';

    public function label(): string
    {
        return match($this) {
            self::Activ     => 'Activ',
            self::Suspendat => 'Suspendat',
            self::Expirat   => 'Expirat',
            self::Reziliat  => 'Reziliat',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Activ     => 'success',
            self::Suspendat => 'warning',
            self::Expirat   => 'danger',
            self::Reziliat  => 'gray',
        };
    }
}
```

**`app/Enums/BillingCycle.php`**:
```php
<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Lunar       = 'lunar';
    case Trimestrial = 'trimestrial';
    case Anual       = 'anual';
    case Unic        = 'unic';

    public function label(): string
    {
        return match($this) {
            self::Lunar       => 'Lunar',
            self::Trimestrial => 'Trimestrial',
            self::Anual       => 'Anual',
            self::Unic        => 'Unic',
        };
    }
}
```

### Step 2 – Generate model and migration

```bash
docker compose exec app php artisan make:model Contract -m
```

**Migration**:
```php
Schema::create('contracts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['mentenanta_ddd', 'eveniment_paintball']);
    $table->string('number');
    $table->string('title');
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->decimal('value', 15, 2)->default(0);
    $table->string('currency', 3)->default('RON');
    $table->enum('billing_cycle', ['lunar', 'trimestrial', 'anual', 'unic'])->default('lunar');
    $table->enum('status', ['activ', 'suspendat', 'expirat', 'reziliat'])->default('activ');
    $table->string('ddd_frequency')->nullable();
    $table->json('ddd_locations')->nullable();
    $table->unsignedInteger('paintball_sessions')->nullable();
    $table->unsignedInteger('paintball_players')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['company_id', 'number']);
});
```

**`app/Models/Contract.php`**:
```php
<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'type', 'number', 'title',
        'start_date', 'end_date', 'value', 'currency', 'billing_cycle',
        'status', 'ddd_frequency', 'ddd_locations',
        'paintball_sessions', 'paintball_players', 'notes',
    ];

    protected $casts = [
        'type'          => ContractType::class,
        'status'        => ContractStatus::class,
        'billing_cycle' => BillingCycle::class,
        'ddd_locations' => 'array',
        'start_date'    => 'date',
        'end_date'      => 'date',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
```

### Step 3 – Create `ContractResource`

```bash
docker compose exec app php artisan make:filament-resource Contract --generate
```

**Resource navigation properties**:
```php
protected static ?string $navigationGroup  = 'Contracte';
protected static ?string $navigationLabel  = 'Contracte';
protected static ?string $modelLabel       = 'Contract';
protected static ?string $pluralModelLabel = 'Contracte';
protected static ?string $navigationIcon   = 'heroicon-o-document-text';
```

**Form schema** – use Tabs with conditional visibility:
```php
use Filament\Forms\Components\{Tabs, Select, TextInput, DatePicker, Textarea, Repeater};
use Filament\Forms\Get;

public static function form(Form $form): Form
{
    return $form->schema([
        Tabs::make()->tabs([
            Tabs\Tab::make('General')->schema([
                TextInput::make('number')->label('Număr contract')->required(),
                Select::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('type')
                    ->label('Tip contract')
                    ->options(\App\Enums\ContractType::class)
                    ->required()
                    ->live(),
                TextInput::make('title')->label('Titlu contract')->required(),
                DatePicker::make('start_date')->label('Data început')->required()->displayFormat('d.m.Y'),
                DatePicker::make('end_date')->label('Data sfârșit')->displayFormat('d.m.Y'),
                TextInput::make('value')->label('Valoare')->numeric()->suffix('RON'),
                Select::make('billing_cycle')
                    ->label('Ciclu facturare')
                    ->options(\App\Enums\BillingCycle::class),
                Select::make('status')
                    ->label('Status')
                    ->options(\App\Enums\ContractStatus::class)
                    ->default('activ'),
                Textarea::make('notes')->label('Observații')->rows(2),
            ]),

            Tabs\Tab::make('DDD (NOD)')
                ->visible(fn (Get $get) => $get('type') === 'mentenanta_ddd')
                ->schema([
                    Select::make('ddd_frequency')
                        ->label('Frecvență tratament')
                        ->options([
                            'lunar'    => 'Lunar',
                            'bilunar'  => 'La 2 luni',
                            'trimestrial' => 'Trimestrial',
                            'semestrial'  => 'Semestrial',
                            'anual'    => 'Anual',
                        ]),
                    Repeater::make('ddd_locations')
                        ->label('Locații tratate')
                        ->schema([
                            TextInput::make('name')->label('Denumire locație')->required(),
                            TextInput::make('address')->label('Adresă'),
                            TextInput::make('treatment_type')->label('Tip tratament'),
                        ])
                        ->columns(3),
                ]),

            Tabs\Tab::make('Paintball')
                ->visible(fn (Get $get) => $get('type') === 'eveniment_paintball')
                ->schema([
                    TextInput::make('paintball_sessions')->label('Număr ședințe')->numeric(),
                    TextInput::make('paintball_players')->label('Jucători per ședință')->numeric(),
                ]),
        ])->columnSpanFull(),
    ]);
}
```

**Table columns**:
```php
->columns([
    TextColumn::make('number')->label('Nr. contract')->searchable()->sortable(),
    TextColumn::make('client.name')->label('Client')->searchable()->sortable(),
    TextColumn::make('type')->label('Tip')
        ->badge()
        ->formatStateUsing(fn ($state) => $state instanceof ContractType ? $state->label() : $state)
        ->color(fn ($state) => $state instanceof ContractType ? $state->color() : 'gray'),
    TextColumn::make('status')->label('Status')
        ->badge()
        ->formatStateUsing(fn ($state) => $state instanceof ContractStatus ? $state->label() : $state)
        ->color(fn ($state) => $state instanceof ContractStatus ? $state->color() : 'gray'),
    TextColumn::make('start_date')->label('Data început')->date('d.m.Y')->sortable(),
    TextColumn::make('end_date')->label('Data sfârșit')->date('d.m.Y')->sortable()
        ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && $state->diffInDays(now()) <= 30 ? 'warning' : null)),
    TextColumn::make('value')->label('Valoare')->money('RON')->sortable(),
])
->recordClasses(fn (Contract $record) =>
    $record->end_date && $record->end_date->diffInDays(now(), false) >= 0 && $record->end_date->diffInDays(now(), false) <= 30
        ? 'bg-yellow-50 dark:bg-yellow-950'
        : null
)
```

**Table actions** – add "Generează Factură":
```php
->actions([
    Tables\Actions\ViewAction::make()->label('Vezi'),
    Tables\Actions\EditAction::make()->label('Editează'),
    Action::make('genereaza_factura')
        ->label('Generează Factură')
        ->icon('heroicon-o-document-plus')
        ->requiresConfirmation()
        ->modalHeading('Generează factură din contract')
        ->modalDescription('Se va crea o factură draft pe baza acestui contract.')
        ->modalSubmitActionLabel('Generează')
        ->action(function (Contract $record) {
            $invoice = app(\App\Services\InvoiceService::class)->createFromContract($record);
            Notification::make()->title('Factură creată cu succes')->success()->send();
            return redirect(\App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $invoice]));
        }),
    Tables\Actions\DeleteAction::make()->label('Șterge'),
])
```

### Step 4 – PDF template (stub)

Create `resources/views/pdf/contract.blade.php`:
```blade
<!DOCTYPE html>
<html lang="ro">
<head><meta charset="UTF-8"><title>Contract {{ $contract->number }}</title></head>
<body>
    <h1>{{ $contract->company->name }}</h1>
    <p>CIF: {{ $contract->company->cif }} | Reg. Com.: {{ $contract->company->reg_com }}</p>
    <hr>
    <h2>CONTRACT Nr. {{ $contract->number }}</h2>
    <p>Client: {{ $contract->client->name }}</p>
    <p>Tip: {{ $contract->type->label() }}</p>
    <p>Perioadă: {{ $contract->start_date->format('d.m.Y') }} – {{ $contract->end_date?->format('d.m.Y') ?? 'nedeterminat' }}</p>
    <p>Valoare: {{ number_format($contract->value, 2, ',', '.') }} {{ $contract->currency }}</p>
    @if($contract->type->value === 'mentenanta_ddd')
        <h3>Locații DDD</h3>
        @foreach($contract->ddd_locations ?? [] as $loc)
            <p>{{ $loc['name'] }} – {{ $loc['address'] ?? '' }} ({{ $loc['treatment_type'] ?? '' }})</p>
        @endforeach
    @endif
    <br><br>
    <table width="100%"><tr>
        <td>Furnizor: _______________</td>
        <td>Beneficiar: _______________</td>
    </tr></table>
</body>
</html>
```

Add method to `app/Services/PdfService.php`:
```php
public function generateContract(Contract $contract): string
{
    $path = storage_path("app/contracts/{$contract->id}.pdf");
    \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.contract', compact('contract'))
        ->save($path);
    return $path;
}
```

---

## Acceptance Criteria
- [x] `docker compose exec app php artisan migrate` runs without errors (contracts table created).
- [x] Contract form shows DDD tab only when type is `mentenanta_ddd`.
- [x] Contract form shows Paintball tab only when type is `eveniment_paintball`.
- [x] Contracts expiring within 30 days have a yellow row highlight in the table.
- [x] "Generează Factură" action creates a draft Invoice and redirects to the invoice edit page.
- [ ] PDF download works from the contract view page. *(requires full PdfService wiring – prompt #5)*
- [x] All labels and notifications are in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
| 2026-02-23 | ContractType, ContractStatus, BillingCycle enums; Contract model (SoftDeletes, CompanyScope, all casts/relationships); contracts migration (all columns, unique company_id+number); ContractResource with tabbed form (General/DDD/Paintball), conditional visibility, table with badge columns + yellow row highlight + Generează Factură action; InvoiceResource stub + pages; Invoice model stub; InvoiceService stub; PdfService + contract.blade.php | "Generează Factură" redirect + PDF download (need invoices table from prompt #5) | Ownership fixed (sudo chown -R relu:relu). Files now created via create_file tool successfully. |
| 2026-02-23 | Status cleanup audit: verified `Generează Factură` creates draft invoice via `InvoiceService::createFromContract()` and redirects to Invoice edit. | Contract PDF template/download flow from contract page. | `resources/views/pdf/contract.blade.php` and a contract PDF download action/route are not wired in the current code. |
