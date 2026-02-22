# GitHub Copilot Instructions – FACTURA2

## Project Overview

**FACTURA2** is an internal invoicing and business management application built for two companies operating under the same system:

- **NOD CONSULTING** – DDD (pest control) services company
- **PAINTBALL MUREȘ** – Paintball leisure/events company

The application handles clients, contacts, contracts, products, stock movements, invoicing (including proforma, receipts, delivery notes), and Romanian e-Factura (ANAF) electronic invoice submission.

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
- Contract types: `mentenanta_ddd`, `eveniment_paintball`.
- Invoice types: `factura`, `proforma`, `chitanta`, `aviz`.
- Invoice status: `draft`, `trimisa`, `platita`, `anulata`.

---

## Key Models & Relationships

```
Company
  └── hasMany: Client, Contract, Product, Invoice

Client
  ├── hasMany: Contact
  ├── hasMany: Contract
  └── hasMany: Invoice

Contract
  ├── belongsTo: Client
  └── hasMany: Invoice  (can generate invoice)

Invoice
  ├── belongsTo: Client
  ├── belongsTo: Contract (nullable)
  └── hasMany: InvoiceLine

InvoiceLine
  ├── belongsTo: Invoice
  └── belongsTo: Product

Product
  └── hasMany: StockMovement

StockMovement
  ├── belongsTo: Product
  └── belongsTo: Invoice (nullable – stock deducted on invoice finalize)
```

---

## Romanian Locale Requirements

- Locale: `ro` / `ro_RO`.
- Date format: `d.m.Y` in views, `Y-m-d` in database.
- Currency: **RON (lei)**, format `1.234,56 lei`.
- VAT rate default: **19%** (can be overridden per invoice line).
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
│   ├── ContractType.php
│   ├── InvoiceType.php
│   └── InvoiceStatus.php
├── Filament/
│   ├── Pages/Dashboard.php
│   └── Resources/
│       ├── ClientResource.php
│       ├── ContractResource.php
│       ├── ProductResource.php
│       ├── StockMovementResource.php
│       └── InvoiceResource.php
├── Http/Middleware/SetActiveCompany.php
├── Livewire/CompanySwitcher.php
├── Models/
│   ├── Company.php
│   ├── Client.php
│   ├── Contact.php
│   ├── Contract.php
│   ├── Product.php
│   ├── StockMovement.php
│   ├── Invoice.php
│   ├── InvoiceLine.php
│   └── Scopes/CompanyScope.php
├── Services/
│   ├── AnafService.php
│   ├── InvoiceService.php
│   ├── PdfService.php
│   └── StockService.php
└── Jobs/
    ├── GenerateInvoicePdf.php
    └── PollEfacturaStatus.php
```

---

## Feature Development Tracking

Every feature prompt file (`.github/prompts/*.prompt.md`) contains a `## Development Log` section at the bottom.

### Rules
- **At the end of each development session**, append a new entry to the `## Development Log` table in the relevant prompt file.
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

**DON'T:**
- Don't hardcode company names or IDs anywhere – always read from the session/model.
- Don't put PDF or ANAF logic directly in Filament Resource action closures.
- Don't skip `SoftDeletes` on any model.
- Don't use raw SQL queries – use Eloquent.
- Don't store money as `float` – always `decimal(15, 2)`.
- Don't run `php artisan`, `composer`, or `npm` commands directly on the host – always use `docker compose exec app`.
- Don't skip updating the `## Development Log` in the prompt file after completing a work session.
