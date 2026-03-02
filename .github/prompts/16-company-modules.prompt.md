# FACTURA2 – Module per Firmă (Company Modules)

## Context
Aplicația suportă **mai multe firme**, fiecare cu un set diferit de module/funcționalități activate. Modulele inactivate nu apar în navigare și resursele lor nu sunt accesibile.

**Firme seed și modulele lor:**
| Firmă | Module activate |
|---|---|
| NOD CONSULTING | `acte_aditionale`, `procese_verbale`, `stocuri`, `efactura` |
| PAINTBALL MUREȘ | `bonuri_fiscale`, `stocuri` |

**Module disponibile:**
| Cheie modul | Descriere |
|---|---|
| `acte_aditionale` | Acte adiționale și Anexe la contracte |
| `procese_verbale` | Procese verbale de lucrări |
| `stocuri` | Gestiunea produselor și stocurilor |
| `efactura` | Trimitere facturi la ANAF e-Factura |
| `bonuri_fiscale` | Bonuri fiscale (casă de marcat Paintball) |

---

## Task

### Step 1 – Migrare: adaugă coloana `modules` la `companies`

```bash
docker compose exec app php artisan make:migration add_modules_to_companies_table
```

**`database/migrations/xxxx_add_modules_to_companies_table.php`**:
```php
public function up(): void
{
    Schema::table('companies', function (Blueprint $table) {
        $table->json('modules')->nullable()->after('is_active');
    });
}

public function down(): void
{
    Schema::table('companies', function (Blueprint $table) {
        $table->dropColumn('modules');
    });
}
```

---

### Step 2 – Enum `CompanyModule` (opțional, recomandat)

**`app/Enums/CompanyModule.php`**:
```php
<?php

namespace App\Enums;

enum CompanyModule: string
{
    case ActeAditionale  = 'acte_aditionale';
    case ProceseVerbale  = 'procese_verbale';
    case Stocuri         = 'stocuri';
    case Efactura        = 'efactura';
    case BonuriFiscale   = 'bonuri_fiscale';

    public function label(): string
    {
        return match($this) {
            self::ActeAditionale  => 'Acte adiționale și Anexe',
            self::ProceseVerbale  => 'Procese verbale de lucrări',
            self::Stocuri         => 'Gestiunea produselor și stocurilor',
            self::Efactura        => 'E-Factura (ANAF)',
            self::BonuriFiscale   => 'Bonuri Fiscale',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->label()]
        )->all();
    }
}
```

---

### Step 3 – Model `Company` – adaugă metoda `hasModule()`

**`app/Models/Company.php`** – adaugă în `$casts` și metoda helper:
```php
protected $casts = [
    // restul cast-urilor existente...
    'modules' => 'array',
];

/**
 * Check if the company has a specific module activated.
 */
public function hasModule(string $key): bool
{
    return in_array($key, $this->modules ?? [], true);
}
```

---

### Step 4 – Pattern `canAccess()` în resurse Filament

Fiecare resursă legată de un modul trebuie să suprascrie `canAccess()`:

```php
// Exemplu: FiscalReceiptResource, ContractAmendmentResource, etc.
public static function canAccess(): bool
{
    $company = \App\Models\Company::find(session('active_company_id'));
    return $company?->hasModule('bonuri_fiscale') ?? false;
}
```

**Resurse și cheile de modul corespunzătoare:**
| Resource | Cheie modul |
|---|---|
| `ContractAmendmentResource` | `acte_aditionale` |
| `ContractAnnexResource` | `acte_aditionale` |
| `WorkCompletionResource` | `procese_verbale` |
| `ProductResource` | `stocuri` |
| `StockMovementResource` | `stocuri` |
| `FiscalReceiptResource` | `bonuri_fiscale` |
| Acțiunea `Trimite la ANAF` din `InvoiceResource` | `efactura` |

> **Notă:** `InvoiceResource`, `ProformaResource`, `ReceiptResource`, `ClientResource`, `ContractResource` sunt **întotdeauna vizibile** (nu depind de module).

---

### Step 5 – `CompanyResource` – adaugă editarea modulelor

În `CompanyResource::form()`, adaugă un `CheckboxList` pentru selecția modulelor:

```php
use Filament\Forms\Components\CheckboxList;
use App\Enums\CompanyModule;

CheckboxList::make('modules')
    ->label('Module active')
    ->options(CompanyModule::options())
    ->columns(2)
    ->helperText('Selectați modulele disponibile pentru această firmă.')
    ->columnSpanFull(),
```

---

### Step 6 – Actualizare seeder

**`database/seeders/CompanySeeder.php`**:
```php
<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::updateOrCreate(
            ['name' => 'NOD CONSULTING'],
            [
                'slug'     => 'nod-consulting',
                'is_active' => true,
                'modules'  => ['acte_aditionale', 'procese_verbale', 'stocuri', 'efactura'],
                // alte câmpuri necesare (CIF, adresă etc.)
            ]
        );

        Company::updateOrCreate(
            ['name' => 'PAINTBALL MUREȘ'],
            [
                'slug'     => 'paintball-mures',
                'is_active' => true,
                'modules'  => ['bonuri_fiscale', 'stocuri'],
                // alte câmpuri necesare
            ]
        );
    }
}
```

---

### Step 7 – NavigationGroup condițional (opțional, optimizare UX)

Dacă este nevoie de ascunderea grupurilor de navigare goale, în `AppServiceProvider::boot()`:

```php
// Nu este necesar dacă canAccess() este implementat corect pe resurse.
// Filament va ascunde automat resourcele pentru care canAccess() returnează false.
// Grupurile fără nici o resursă accesibilă dispar automat.
```

Nu este nevoie de cod suplimentar – Filament ascunde automat grupurile de navigare care nu au resurse accesibile.

---

## Acceptance Criteria
- [ ] Coloana `modules` (JSON) prezentă pe tabela `companies`.
- [ ] `Company::hasModule(string $key): bool` funcționează corect.
- [ ] `CompanyModule` enum definit cu 5 valori și labels în română.
- [ ] `CompanyResource` permite editarea modulelor cu `CheckboxList`.
- [ ] Seeder actualizat: NOD cu 4 module, PAINTBALL cu 2 module.
- [ ] Resursele cu `canAccess()` conditionat nu apar în navigare pentru firma ce nu are modulul activ.
- [ ] Grupurile de navigare fără resurse accesibile dispar automat.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
