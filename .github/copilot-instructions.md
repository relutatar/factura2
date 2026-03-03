# GitHub Copilot Instructions – FACTURA2

## Project Overview

**FACTURA2** is an internal invoicing and business management application built for two companies operating under the same system:

- **NOD CONSULTING** – DDD (pest control) services company
- **PAINTBALL MUREȘ** – Paintball leisure/events company

The application handles clients, contacts, contracts (with addendums and annexes), products, stock movements, work completion reports (procese verbale), invoicing (fiscal invoices, proforma invoices, receipts / chitanțe), fiscal receipts for cash-register sales (bonuri fiscale), and Romanian e-Factura (ANAF) electronic invoice submission.

The system is **generic**: additional companies with other service types can be added without code changes, using the per-company module activation system.

**Application language: Romanian** – all UI labels, validation messages, notifications, and user-facing text must be in Romanian.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3, Laravel 11 / 12 |
| Admin Panel | FilamentPHP 3.2+ |
| Database | MySQL 8+ |
| Queue | MySQL (`database` driver) |
| Cache / Session | File driver |
| Dev Environment | Docker (docker-compose) |
| PDF Generation | `spatie/laravel-pdf` (primary), `barryvdh/laravel-dompdf` (fallback) |
| Permissions | `spatie/laravel-permission` |
| e-Factura | `pristavu/laravel-anaf` (or latest 2026-compatible package) |
| Frontend | Livewire 3 (via Filament), Alpine.js, Tailwind CSS |

---

## Development Environment (Docker)

The application **must run in Docker** during development. Never assume a local PHP/MySQL setup.

### Required Services (`docker-compose.yml`)

| Service | Image | Purpose |
|---|---|---|
| `app` | `php:8.3-fpm` (custom Dockerfile) | Laravel application + php-fpm |
| `nginx` | `nginx:alpine` | Web server, proxies to `app` |
| `mysql` | `mysql:8.0` | Primary database + queue table |
| `queue` | same image as `app` | Runs `php artisan queue:work` |
| `scheduler` | same image as `app` | Runs `php artisan schedule:work` |

### Conventions
- All `php artisan` commands must be run **inside the `app` container**: `docker compose exec app php artisan ...`
- Composer commands: `docker compose exec app composer ...`
- The `.env` file used in Docker must set `DB_HOST=mysql`, `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `SESSION_DRIVER=file`.
- Expose only port **80** (nginx) and **3306** (mysql, for local DB clients).
- Use a named volume `mysql_data` to persist data across container restarts.
- A `Makefile` (or `docker-compose` shortcuts) should document common commands: `make up`, `make migrate`, `make seed`, `make pint`, `make test`.
- Store the custom `Dockerfile` in the project root. Base it on `php:8.3-fpm`, install required PHP extensions: `pdo_mysql`, `gd`, `zip`, `intl`, `bcmath`, `pcntl`.

---

## Key Code Patterns

These are the exact patterns to use throughout the project. Always follow these, never invent alternatives.

### CompanyScope – how to define it
```php
// app/Models/Scopes/CompanyScope.php
namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = session('active_company_id');
        if ($companyId) {
            $builder->where('company_id', $companyId);
        }
    }
}
```

### How to register CompanyScope on every scoped model
```php
// Inside any model that belongs to a company:
protected static function booted(): void
{
    static::addGlobalScope(new \App\Models\Scopes\CompanyScope());

    static::creating(function (self $model) {
        if (empty($model->company_id)) {
            $model->company_id = session('active_company_id');
        }
    });
}
```

### Filament Resource – Romanian navigation label pattern
```php
protected static ?string $navigationGroup = 'Clienți';   // Romanian group
protected static ?string $navigationLabel = 'Clienți';   // Romanian nav item
protected static ?string $modelLabel = 'Client';          // Romanian singular
protected static ?string $pluralModelLabel = 'Clienți';  // Romanian plural
protected static ?string $navigationIcon = 'heroicon-o-users';
```

### Filament Form – Romanian labels + conditional visibility
```php
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;

Select::make('type')
    ->label('Tip client')
    ->options(['persoana_juridica' => 'Persoană Juridică', 'persoana_fizica' => 'Persoană Fizică'])
    ->required()
    ->live(), // required for reactive fields

TextInput::make('cif')
    ->label('CIF')
    ->visible(fn (Get $get) => $get('type') === 'persoana_juridica'),
```

### Filament Notification – Romanian toast
```php
use Filament\Notifications\Notification;

Notification::make()
    ->title('Salvat cu succes')
    ->success()
    ->send();

Notification::make()
    ->title('Eroare la salvare')
    ->body('Verificați câmpurile marcate.')
    ->danger()
    ->send();
```

### Filament Table – colored status badge
```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('status')
    ->label('Status')
    ->badge()
    ->color(fn (string $state) => match($state) {
        'activ'    => 'success',
        'suspendat'=> 'warning',
        'expirat'  => 'danger',
        'reziliat' => 'gray',
        default    => 'gray',
    })
    ->formatStateUsing(fn (string $state) => match($state) {
        'activ'    => 'Activ',
        'suspendat'=> 'Suspendat',
        'expirat'  => 'Expirat',
        'reziliat' => 'Reziliat',
        default    => $state,
    }),
```

### Filament Action in Table – with confirmation
```php
use Filament\Tables\Actions\Action;

Action::make('genereaza_factura')
    ->label('Generează Factură')
    ->icon('heroicon-o-document-plus')
    ->requiresConfirmation()
    ->modalHeading('Generează factură din contract')
    ->modalDescription('Se va crea o factură draft pe baza acestui contract.')
    ->modalSubmitActionLabel('Generează')
    ->action(function (Contract $record) {
        // delegate to InvoiceService, never put business logic here
        $invoice = app(InvoiceService::class)->createFromContract($record);
        Notification::make()->title('Factură creată')->success()->send();
        return redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
    }),
```

### PHP Backed Enum with Romanian label
```php
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

### Queued Job skeleton
```php
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

---

## Architecture Rules

### Multi-Company (CompanyScope)
- Every model that belongs to a company **must** have a `company_id` column.
- Apply `CompanyScope` as a **global scope** on all company-scoped models so queries are always filtered automatically.
- Company is resolved from the **session** (`session('active_company_id')`).
- A **Company Switcher** component in the Filament header lets the authenticated user switch between NOD CONSULTING and PAINTBALL MUREȘ.
- Never expose data of one company to a user of the other company.

### Filament Panel
- Panel ID: `admin`, route prefix: `/admin`.
- Use **standard Filament login** (email + password).
- Resources are grouped logically: Clienți, Contracte, Produse & Stocuri, Facturi.
- All Filament form labels, table column headers, filter labels, action names, and notifications **must be in Romanian**.
- Use Filament `Tabs` in forms when a model has company-specific fields.
- Table columns must include sensible default sort and search.

### Models & Migrations
- Follow Laravel naming conventions: `snake_case` columns, `PascalCase` models.
- All models use `SoftDeletes`.
- All money/price columns are stored as `decimal(15, 2)`.
- Use `enum` database columns (or PHP-backed enums) for status and type fields.
- Every migration must be **reversible** (implement `down()`).

### Seeders
- A `CompanySeeder` must always seed exactly two companies: NOD CONSULTING and PAINTBALL MUREȘ.
- Seeders must be idempotent (`updateOrCreate`, not plain `create`).

### Services & Jobs
- Business logic (PDF generation, e-Factura upload, stock deduction) goes in dedicated **Service classes** under `app/Services/`.
- Long-running operations (PDF, e-Factura polling) must be dispatched as **queued Jobs**.
- e-Factura polling Job runs every 10 minutes via the scheduler.

### Enums
- Define PHP 8.1 backed enums in `app/Enums/`.
- Invoice status (applies to facturi fiscale): `draft`, `trimisa`, `platita`, `anulata`.
- Proforma status: `draft`, `trimisa`, `convertita`, `anulata`.
- Receipt status: `emisa`, `anulata`.
- Payment methods: `virament_bancar`, `numerar` — **only these two** are supported. A chitanță (receipt) is generated **manually** via the „Generare chitanță" button in `InvoiceResource`, available only on invoices with `status = platita`, `payment_method = numerar`, and no receipt yet.
- Contract addendum status: `draft`, `semnat`, `anulat`.
- Work-completion (PV) status: `draft`, `semnat`, `anulat`.
- Fiscal receipt status: `inregistrat`, `anulat`.
- VAT rates: `21`, `11`, `0` (stored in the `vat_rates` database table, not hardcoded in enums).

### Company Modules
- Every `Company` record has a `modules` JSON column listing active optional modules.
- Available module keys: `bonuri_fiscale`, `procese_verbale`, `acte_aditionale`, `stocuri`, `efactura`.
- Navigation groups and resources are conditionally shown based on the active company's modules.
- Helper: `$company->hasModule('bonuri_fiscale')` — implement as a method on `Company`.
- Seed NOD CONSULTING with modules: `acte_aditionale`, `procese_verbale`, `stocuri`, `efactura`.
- Seed PAINTBALL MUREȘ with modules: `bonuri_fiscale`, `stocuri`.

---

## Key Models & Relationships

```
Company
  ├── hasMany: Client, Contract, Product, Invoice, Proforma, Receipt
  ├── hasMany: ContractTemplate, DecisionTemplate, DocumentTemplate
  ├── hasMany: NumberingRange, Decision
  └── modules: JSON column (active module keys)

Client
  ├── hasMany: Contact
  ├── hasMany: Contract
  ├── hasMany: Invoice
  └── hasMany: Proforma

Contract
  ├── belongsTo: Client
  ├── belongsTo: ContractTemplate
  ├── hasMany: Invoice
  ├── hasMany: Proforma
  ├── hasMany: ContractAmendment   (acte adiționale – separate documents with body + PDF)
  ├── hasMany: ContractAnnex       (anexe – file attachments OR template-generated docs)
  └── hasMany: WorkCompletion      (procese verbale de lucrări)

ContractAmendment                  (act adițional)
  ├── belongsTo: Contract
  └── belongsTo: DocumentTemplate (nullable)

ContractAnnex                      (anexă)
  ├── belongsTo: Contract
  ├── belongsTo: DocumentTemplate (nullable – for generated annexes)
  └── file_path: nullable string  (for uploaded file annexes)

WorkCompletion                     (proces verbal de lucrări)
  ├── belongsTo: Contract
  └── belongsTo: DocumentTemplate
  └── number: sequential per company, reset annually

Invoice                            (factură fiscală)
  ├── belongsTo: Client
  ├── belongsTo: Contract (nullable)
  ├── belongsTo: Proforma (nullable – if generated from proforma)
  ├── hasMany: InvoiceLine
  └── hasOne: Receipt (nullable – only if payment_method = numerar)

Proforma                           (factură proformă)
  ├── belongsTo: Client
  ├── belongsTo: Contract (nullable)
  ├── hasMany: ProformaLine
  └── hasOne: Invoice (nullable – after conversion)

Receipt                            (chitanță)
  └── belongsTo: Invoice

FiscalReceipt                      (bon fiscal – modul Paintball)
  ├── belongsTo: Company
  ├── hasMany: FiscalReceiptLine
  └── hasOne: ConsumptionNote (auto-generated on save)

FiscalReceiptLine
  ├── belongsTo: FiscalReceipt
  └── belongsTo: Product

InvoiceLine
  ├── belongsTo: Invoice
  └── belongsTo: Product

ProformaLine
  ├── belongsTo: Proforma
  └── belongsTo: Product

Product
  └── hasMany: StockMovement

StockMovement
  ├── belongsTo: Product
  └── belongsTo: Invoice (nullable – stock deducted on invoice finalize)

DocumentTemplate                   (template generic: PV, anexe generate etc.)
  ├── belongsTo: Company (nullable – null = system template)
  ├── context_type: enum (contract, invoice, client)
  └── body_template: text with placeholders
```

---

## Romanian Locale Requirements

- Locale: `ro` / `ro_RO`.
- Date format: `d.m.Y` in views, `Y-m-d` in database.
- Currency: **RON (lei)**, format `1.234,56 lei`.
- VAT rates are stored in the **`vat_rates` database table** and managed by the admin via `VatRateResource` (navigation group: *Configurare*).
- Each `VatRate` record has: `value` (decimal %), `label`, `description`, `is_default`, `is_active`, `sort_order`.
- Initial Romanian rates (seeded, can be changed by admin): **21% Standard**, **11% Redusă**, **0% Scutit**.
- `Product` and `InvoiceLine` have a `vat_rate_id` foreign key → `vat_rates.id`.
- Use `VatRate::selectOptions()` (from `App\Models\VatRate`) in all Filament Select fields — never hardcode a percentage.
- The default VAT rate is resolved via `VatRate::defaultRate()` (the record with `is_default = true`).
- CIF (Cod de Identificare Fiscală) lookup via ANAF public API on Client form.
- All validation error messages must be in Romanian.

---

## Coding Conventions

- **PSR-12** code style enforced by Laravel Pint.
- Use **typed properties** and **return types** everywhere.
- Use **Form Request** classes for validation (not inline `$request->validate()`).
- Use **Resource Collections** for JSON responses if any API endpoints are added.
- Write **PHPDoc** blocks on all public methods.
- Avoid business logic in Controllers or Filament Resources – delegate to Services.
- Name Filament actions descriptively in Romanian: `Generează Factură`, `Trimite la e-Factura`, etc.

---

## File & Folder Structure

```
app/
├── Enums/
│   ├── ContractAmendmentStatus.php
│   ├── ContractStatus.php
│   ├── FiscalReceiptStatus.php
│   ├── InvoiceStatus.php
│   ├── PaymentMethod.php          // virament_bancar | numerar
│   ├── ProformaStatus.php
│   ├── ReceiptStatus.php
│   ├── StockMovementType.php
│   └── WorkCompletionStatus.php
├── Filament/
│   ├── Pages/Dashboard.php
│   └── Resources/
│       ├── ClientResource.php
│       ├── CompanyResource.php
│       ├── ContractResource.php
│       ├── ContractAmendmentResource.php
│       ├── ContractAnnexResource.php
│       ├── ContractTemplateResource.php
│       ├── DecisionResource.php
│       ├── DecisionTemplateResource.php
│       ├── DocumentTemplateResource.php
│       ├── FiscalReceiptResource.php
│       ├── InvoiceResource.php
│       ├── NumberingRangeResource.php
│       ├── ProductResource.php
│       ├── ProformaResource.php
│       ├── ReceiptResource.php
│       ├── StockMovementResource.php
│       ├── VatRateResource.php
│       └── WorkCompletionResource.php
├── Http/Middleware/SetActiveCompany.php
├── Livewire/CompanySwitcher.php
├── Models/
│   ├── Company.php
│   ├── Client.php
│   ├── Contact.php
│   ├── Contract.php
│   ├── ContractAmendment.php
│   ├── ContractAnnex.php
│   ├── ContractTemplate.php
│   ├── Decision.php
│   ├── DecisionTemplate.php
│   ├── DocumentTemplate.php
│   ├── FiscalReceipt.php
│   ├── FiscalReceiptLine.php
│   ├── Invoice.php
│   ├── InvoiceLine.php
│   ├── NumberingRange.php
│   ├── Product.php
│   ├── Proforma.php
│   ├── ProformaLine.php
│   ├── Receipt.php
│   ├── StockMovement.php
│   ├── VatRate.php
│   ├── WorkCompletion.php
│   └── Scopes/CompanyScope.php
├── Services/
│   ├── AnafService.php
│   ├── ContractTemplateService.php
│   ├── DecisionService.php
│   ├── DocumentNumberService.php
│   ├── DocumentTemplateService.php
│   ├── FiscalReceiptService.php
│   ├── InvoiceService.php
│   ├── PdfService.php
│   ├── ProformaService.php
│   ├── ReceiptService.php
│   ├── StockService.php
│   └── WorkCompletionService.php
└── Jobs/
    ├── GenerateInvoicePdf.php
    ├── GenerateProformaPdf.php
    ├── GenerateReceiptPdf.php
    ├── PollEfacturaStatus.php
    └── SubmitEfactura.php
```

---

## Feature Development Tracking

Every feature prompt file (`.github/prompts/*.prompt.md`) contains a `## Development Log` section at the bottom.

### Rules
- **At the end of each development session**, append a new entry to the `## Development Log` table in the relevant prompt file.
- **Before any `git commit`**, update the relevant plans and prompt files so implementation tracking is accurate (including the proper `## Development Log` entry in the relevant `.github/prompts/*.prompt.md` file).
- Each entry must include: date, what was implemented, what is still pending, and any blockers.
- Use the acceptance criteria checkboxes (` - [x]` / ` - [ ]`) to reflect current completion state — update them as items are finished.
- When all acceptance criteria are checked, mark the feature as `✅ Complete` in the log.
- Do **not** delete previous log entries — the history must be preserved.

### Log Entry Format
```
| YYYY-MM-DD | Short description of what was done | Pending items | Blockers or notes |
```

---

## Do's and Don'ts

**DO:**
- Always scope queries by `company_id` via `CompanyScope`.
- Generate PDF invoices with the company logo stored in `Company.logo`.
- Use numeric invoice series per company (e.g., `NOD-2026-0001`, `PBM-2026-0001`).
- Show colored status badges in all tables.
- Use Filament notifications (toasts) in Romanian for all user-facing feedback.
- Validate Romanian CIF/CNP format where applicable.
- Always check `$company->hasModule('key')` before showing module-specific resources or navigation items.
- Generate a `Receipt` (chitanță) via the manual „Generare chitanță" button in `InvoiceResource`; show the button only when `status = platita`, `payment_method = numerar`, and no receipt exists yet.
- Keep `Proforma`, `Invoice`, and `Receipt` as **separate models and resources** — never merge them into a single model.
- When a `FiscalReceipt` is saved/posted, auto-create a `ConsumptionNote` for consumable lines.
- Number `WorkCompletion` documents sequentially per company, resetting each calendar year (no numbering range / decision required, simpler than invoices).

**DON'T:**
- Don't hardcode company names or IDs anywhere – always read from the session/model.
- Don't put PDF or ANAF logic directly in Filament Resource action closures.
- Don't skip `SoftDeletes` on any model.
- Don't use raw SQL queries – use Eloquent.
- Don't store money as `float` – always `decimal(15, 2)`.
- Don't run `php artisan`, `composer`, or `npm` commands directly on the host – always use `docker compose exec app`.
- Don't skip updating the `## Development Log` in the prompt file after completing a work session.
