# FACTURA2 – Clients & Contacts Module

## Context
Clients are company-scoped entities. Each client can be a legal entity (Persoană Juridică) or an individual (Persoană Fizică). Clients can have multiple contacts. When a CIF is entered for a legal entity, the ANAF public API is called to auto-fill company details.

## Prerequisites
- `Company` model, `CompanyScope`, and `SetActiveCompany` middleware must exist (see `01-setup.prompt.md`).
- `app/Models/Scopes/CompanyScope.php` must be present.

---

## Task

### Step 1 – Create `ClientType` enum

Create `app/Enums/ClientType.php`:
```php
<?php

namespace App\Enums;

enum ClientType: string
{
    case PersoanăJuridică = 'persoana_juridica';
    case PersoanăFizică   = 'persoana_fizica';

    public function label(): string
    {
        return match($this) {
            self::PersoanăJuridică => 'Persoană Juridică',
            self::PersoanăFizică   => 'Persoană Fizică',
        };
    }
}
```

### Step 2 – Generate model and migration

```bash
docker compose exec app php artisan make:model Client -m
docker compose exec app php artisan make:model Contact -m
```

**`database/migrations/xxxx_create_clients_table.php`**:
```php
Schema::create('clients', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['persoana_juridica', 'persoana_fizica'])->default('persoana_juridica');
    $table->string('name');
    $table->string('cif')->nullable();
    $table->string('cnp')->nullable();
    $table->string('reg_com')->nullable();
    $table->text('address')->nullable();
    $table->string('city')->nullable();
    $table->string('county')->nullable();
    $table->string('country')->default('România');
    $table->string('email')->nullable();
    $table->string('phone')->nullable();
    $table->string('iban')->nullable();
    $table->string('bank')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

**`database/migrations/xxxx_create_contacts_table.php`**:
```php
Schema::create('contacts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('role')->nullable();
    $table->string('email')->nullable();
    $table->string('phone')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### Step 3 – Create the models

**`app/Models/Client.php`**:
```php
<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'type', 'name', 'cif', 'cnp', 'reg_com',
        'address', 'city', 'county', 'country', 'email', 'phone',
        'iban', 'bank', 'notes',
    ];

    protected $casts = [
        'type' => ClientType::class,
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

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
```

**`app/Models/Contact.php`**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = ['client_id', 'name', 'role', 'email', 'phone'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
```

### Step 4 – Create `AnafService` (CIF lookup)

Create `app/Services/AnafService.php`:
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AnafService
{
    /**
     * Look up company data by CIF from the ANAF public REST API.
     * Returns null on failure or if CIF not found.
     *
     * @return array{denumire: string, adresa: string, nrRegCom: string}|null
     */
    public function lookupCif(string $cif): ?array
    {
        $cif = preg_replace('/[^0-9]/', '', $cif); // strip RO prefix

        return Cache::remember("anaf_cif_{$cif}", now()->addHours(24), function () use ($cif) {
            $response = Http::post(
                'https://webservicesp.anaf.ro/PlatitorTvaRest/api/v8/ws/tva',
                [['cui' => (int) $cif, 'data' => now()->format('Y-m-d')]]
            );

            if (! $response->successful()) {
                return null;
            }

            $found = $response->json('found.0');
            if (empty($found)) {
                return null;
            }

            return [
                'denumire'  => $found['date_generale']['denumire'] ?? '',
                'adresa'    => $found['date_generale']['adresa'] ?? '',
                'nrRegCom'  => $found['date_generale']['nrRegCom'] ?? '',
            ];
        });
    }
}
```

### Step 5 – Create `ClientResource` (Filament)

```bash
docker compose exec app php artisan make:filament-resource Client --generate
```

Replace the generated resource with the full implementation in `app/Filament/Resources/ClientResource.php`:

**Key form schema**:
```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Services\AnafService;

public static function form(Form $form): Form
{
    return $form->schema([
        Tabs::make()->tabs([
            Tabs\Tab::make('Date generale')->schema([
                Select::make('type')
                    ->label('Tip client')
                    ->options([
                        'persoana_juridica' => 'Persoană Juridică',
                        'persoana_fizica'   => 'Persoană Fizică',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('cif')
                    ->label('CIF')
                    ->visible(fn (Get $get) => $get('type') === 'persoana_juridica')
                    ->suffixAction(
                        Action::make('lookup_anaf')
                            ->label('Caută ANAF')
                            ->icon('heroicon-o-magnifying-glass')
                            ->action(function (Get $get, Set $set) {
                                $data = app(AnafService::class)->lookupCif($get('cif'));
                                if ($data) {
                                    $set('name', $data['denumire']);
                                    $set('reg_com', $data['nrRegCom']);
                                    $set('address', $data['adresa']);
                                }
                            })
                    ),

                TextInput::make('cnp')
                    ->label('CNP')
                    ->visible(fn (Get $get) => $get('type') === 'persoana_fizica'),

                TextInput::make('name')->label('Denumire / Nume')->required(),
                TextInput::make('reg_com')->label('Nr. Reg. Com.')
                    ->visible(fn (Get $get) => $get('type') === 'persoana_juridica'),
                Textarea::make('address')->label('Adresă')->rows(2),
                TextInput::make('city')->label('Localitate'),
                TextInput::make('county')->label('Județ'),
                TextInput::make('country')->label('Țară')->default('România'),
                TextInput::make('email')->label('Email')->email(),
                TextInput::make('phone')->label('Telefon'),
                TextInput::make('iban')->label('IBAN'),
                TextInput::make('bank')->label('Bancă'),
                Textarea::make('notes')->label('Observații')->rows(2),
            ]),

            Tabs\Tab::make('Contacte')->schema([
                Repeater::make('contacts')
                    ->relationship()
                    ->label('Contacte')
                    ->schema([
                        TextInput::make('name')->label('Nume')->required(),
                        TextInput::make('role')->label('Funcție'),
                        TextInput::make('email')->label('Email')->email(),
                        TextInput::make('phone')->label('Telefon'),
                    ])
                    ->columns(2),
            ]),
        ])->columnSpanFull(),
    ]);
}
```

**Table columns**:
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')->label('Denumire')->searchable()->sortable(),
            TextColumn::make('cif')->label('CIF')->searchable(),
            TextColumn::make('cnp')->label('CNP')->searchable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('city')->label('Localitate')->sortable(),
            TextColumn::make('phone')->label('Telefon'),
            TextColumn::make('type')
                ->label('Tip')
                ->badge()
                ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\ClientType ? $state->label() : $state)
                ->color(fn ($state) => 'info'),
            TextColumn::make('created_at')->label('Creat la')->dateTime('d.m.Y')->sortable()->toggleable(),
        ])
        ->filters([
            SelectFilter::make('type')
                ->label('Tip client')
                ->options([
                    'persoana_juridica' => 'Persoană Juridică',
                    'persoana_fizica'   => 'Persoană Fizică',
                ]),
            SelectFilter::make('city')->label('Localitate')->relationship('clients', 'city'),
        ])
        ->actions([
            Tables\Actions\ViewAction::make()->label('Vezi'),
            Tables\Actions\EditAction::make()->label('Editează'),
            Tables\Actions\DeleteAction::make()->label('Șterge'),
        ])
        ->defaultSort('name');
}
```

**Resource navigation properties** (add at the top of the class):
```php
protected static ?string $navigationGroup    = 'Clienți';
protected static ?string $navigationLabel    = 'Clienți';
protected static ?string $modelLabel         = 'Client';
protected static ?string $pluralModelLabel   = 'Clienți';
protected static ?string $navigationIcon     = 'heroicon-o-users';
```

---

## Acceptance Criteria
- [ ] `docker compose exec app php artisan migrate` runs without errors (clients + contacts tables).
- [ ] CIF auto-fill calls ANAF API and populates name, reg_com, address fields.
- [ ] `company_id` is never shown in the form — it is set automatically from `session('active_company_id')`.
- [ ] Contacts tab in the client form allows adding/editing contacts inline.
- [ ] Table search works on `name`, `cif`, and `cnp` simultaneously.
- [ ] All form labels and notifications are in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
