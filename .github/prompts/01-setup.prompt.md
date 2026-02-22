# FACTURA2 – Project Setup & Company Scaffolding

## Context
Setting up the base Laravel 11/12 + FilamentPHP 3 project with multi-company support for **NOD CONSULTING** and **PAINTBALL MUREȘ**.

## Task
In the FACTURA2 project, implement the full base setup:

### 1. Docker Environment
Create the full Docker development environment **before** any Laravel setup.

**`Dockerfile`** (project root, based on `php:8.3-fpm`):
- Install PHP extensions: `pdo_mysql`, `gd`, `zip`, `intl`, `bcmath`, `pcntl`, `exif`, `opcache`
- Install `composer` globally
- Set `WORKDIR /var/www/html`

**`docker-compose.yml`** with the following services:

| Service | Image | Notes |
|---|---|---|
| `app` | custom Dockerfile | Laravel app + php-fpm |
| `nginx` | `nginx:alpine` | Port 80, proxies to `app:9000` |
| `mysql` | `mysql:8.0` | Port 3306, named volume `mysql_data` |
| `queue` | same as `app` | Runs `php artisan queue:work --sleep=3 --tries=3` |
| `scheduler` | same as `app` | Runs `php artisan schedule:work` |

**`docker/nginx/default.conf`** – nginx site config pointing to `/var/www/html/public`.

**`.env`** values required for Docker (set these in `.env.example` too):
```
APP_URL=http://localhost
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=factura2
DB_USERNAME=factura2
DB_PASSWORD=secret
QUEUE_CONNECTION=database
SESSION_DRIVER=file
CACHE_STORE=file
```

**`Makefile`** with shortcuts:
```makefile
up:       docker compose up -d
down:     docker compose down
migrate:  docker compose exec app php artisan migrate
seed:     docker compose exec app php artisan db:seed
fresh:    docker compose exec app php artisan migrate:fresh --seed
pint:     docker compose exec app ./vendor/bin/pint
test:     docker compose exec app php artisan test
tinker:   docker compose exec app php artisan tinker
shell:    docker compose exec app bash
```

> All subsequent `php artisan` and `composer` commands in this project must be run via `docker compose exec app`.

### 2. Composer Dependencies
Install and configure the following packages (run inside the `app` container):
- `filament/filament:^3.2`
- `spatie/laravel-permission`
- `spatie/laravel-pdf`
- `barryvdh/laravel-dompdf` (fallback PDF driver)
- `pristavu/laravel-anaf` (or the latest 2026-compatible e-Factura package)

### 3. Filament Panel
- Create the Filament admin panel with ID `admin` and route prefix `/admin`.
- Use standard email + password login.
- Register the panel in `app/Providers/Filament/AdminPanelProvider.php`.

### 4. Company Model, Migration & Seeder
Create a `Company` model with:

**Migration columns:**
- `id` (primary key)
- `name` (string)
- `cif` (string, unique – Romanian fiscal code)
- `reg_com` (string, nullable – trade register number)
- `address` (text)
- `iban` (string, nullable)
- `bank` (string, nullable)
- `logo` (string, nullable – path to logo file)
- `invoice_prefix` (string – e.g. `NOD`, `PBM`)
- `efactura_settings` (json, nullable – stores PFX path + password)
- `timestamps` + `softDeletes`

**CompanySeeder** (idempotent with `updateOrCreate`):
```php
// Seed exactly two companies with real data:

// 1. NOD CONSULTING SRL
//    CUI:        27864858
//    Reg. Com.:  J2010000868267
//    Județ:      Mureș
//    Adresă:     Str. Vișeului 6, Ap. 1, Tg. Mureș, 540091
//    Prefix:     NOD

// 2. PAINTBALL MUREȘ SRL
//    CUI:        36408451
//    Reg. Com.:  J26/1106/2016
//    Județ:      Mureș
//    Adresă:     Sat Ivănești 75, 547368
//    Prefix:     PBM
```

### 5. CompanyScope (Global Scope)
Create `app/Models/Scopes/CompanyScope.php`:
- Applies `WHERE company_id = session('active_company_id')` to all queries.
- Must be registered via `booted()` on every company-scoped model.

### 6. SetActiveCompany Middleware
Create `app/Http/Middleware/SetActiveCompany.php`:
- On every request, if `session('active_company_id')` is not set, default to the first company the authenticated user has access to.
- Register it in the `web` middleware group.

### 7. Company Switcher (Livewire)
Create `app/Livewire/CompanySwitcher.php` + Blade view:
- Displays the currently active company name in the Filament top bar.
- A dropdown lists all accessible companies.
- On selection, updates `session('active_company_id')` and redirects to the dashboard.
- Register as a Filament render hook: `PanelsRenderHook::TOPBAR_START`.

## Acceptance Criteria
- [ ] `docker compose up -d` starts all 5 services with no errors.
- [ ] `docker compose exec app php artisan queue:table && php artisan migrate` creates the `jobs` table.
- [ ] `docker compose exec app php artisan migrate --seed` runs without errors.
- [ ] Two companies exist in the `companies` table with the correct real data.
- [ ] Switching companies in the header correctly scopes all subsequent queries.
- [ ] PDF and ANAF packages are registered in `config/`.
- [ ] `Makefile` shortcuts (`up`, `migrate`, `seed`, `pint`, `test`) all work.
- [ ] All user-visible text is in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
