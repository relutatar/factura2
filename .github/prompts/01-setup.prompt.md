# FACTURA2 – Project Setup & Company Scaffolding

## Context
This is the **first step** of the FACTURA2 project. It establishes the Docker environment, installs Laravel + FilamentPHP 3, and seeds the two companies.
Do not skip any step. Do not assume anything is already installed. Follow the order exactly.

## Prerequisites
- Docker and Docker Compose installed on the host machine.
- The project folder is `/var/www/html` inside the container, mapped to the repo root on the host.

---

## Task

### Step 1 – Create `Dockerfile` (project root)

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev libicu-dev \
    zip unzip && \
    docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN if [ -f composer.json ]; then composer install --no-interaction --prefer-dist --optimize-autoloader; fi

RUN chown -R www-data:www-data storage bootstrap/cache
```

### Step 2 – Create `docker-compose.yml` (project root)

```yaml
services:
  app:
    build: .
    container_name: factura2_app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    depends_on:
      - mysql
    networks:
      - factura2

  nginx:
    image: nginx:alpine
    container_name: factura2_nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
    networks:
      - factura2

  mysql:
    image: mysql:8.0
    container_name: factura2_mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: factura2
      MYSQL_USER: factura2
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: rootsecret
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - factura2

  queue:
    build: .
    container_name: factura2_queue
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    depends_on:
      - mysql
    networks:
      - factura2

  scheduler:
    build: .
    container_name: factura2_scheduler
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
    command: php artisan schedule:work
    depends_on:
      - mysql
    networks:
      - factura2

networks:
  factura2:
    driver: bridge

volumes:
  mysql_data:
```

### Step 3 – Create `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### Step 4 – Create `Makefile` (project root)

```makefile
up:
docker compose up -d

down:
docker compose down

build:
docker compose build --no-cache

shell:
docker compose exec app bash

migrate:
docker compose exec app php artisan migrate

seed:
docker compose exec app php artisan db:seed

fresh:
docker compose exec app php artisan migrate:fresh --seed

pint:
docker compose exec app ./vendor/bin/pint

test:
docker compose exec app php artisan test

tinker:
docker compose exec app php artisan tinker

logs:
docker compose logs -f
```

### Step 5 – Bootstrap Laravel inside the container

Run these commands **once** on the host to create the Laravel project:
```bash
docker compose up -d mysql
docker compose run --rm app composer create-project laravel/laravel . "^11" --prefer-dist
docker compose up -d
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
```

Set the following values in `.env` (and mirror them in `.env.example` without secrets):
```
APP_NAME=FACTURA2
APP_URL=http://localhost
APP_LOCALE=ro

DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=factura2
DB_USERNAME=factura2
DB_PASSWORD=secret

QUEUE_CONNECTION=database
SESSION_DRIVER=file
CACHE_STORE=file
```

### Step 6 – Install Composer packages

```bash
docker compose exec app composer require \
  filament/filament:"^3.2" \
  spatie/laravel-permission \
  spatie/laravel-pdf \
  barryvdh/laravel-dompdf

# Scaffold the Filament admin panel (enter "admin" when prompted for panel ID)
docker compose exec app php artisan filament:install --panels
```

### Step 7 – Create `Company` model, migration, and seeder

```bash
docker compose exec app php artisan make:model Company -m
docker compose exec app php artisan make:seeder CompanySeeder
```

**Migration** – add columns inside `up()`:
```php
Schema::create('companies', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('cif')->unique();
    $table->string('reg_com')->nullable();
    $table->text('address')->nullable();
    $table->string('city')->nullable();
    $table->string('county')->nullable();
    $table->string('iban')->nullable();
    $table->string('bank')->nullable();
    $table->string('logo')->nullable();
    $table->string('invoice_prefix', 10);
    $table->json('efactura_settings')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

**`app/Models/Company.php`**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'cif', 'reg_com', 'address', 'city', 'county',
        'iban', 'bank', 'logo', 'invoice_prefix', 'efactura_settings',
    ];

    protected $casts = [
        'efactura_settings' => 'array',
    ];
}
```

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
            ['cif' => '27864858'],
            [
                'name'           => 'NOD CONSULTING SRL',
                'reg_com'        => 'J2010000868267',
                'address'        => 'Str. Vișeului 6, Ap. 1',
                'city'           => 'Târgu Mureș',
                'county'         => 'Mureș',
                'invoice_prefix' => 'NOD',
            ]
        );

        Company::updateOrCreate(
            ['cif' => '36408451'],
            [
                'name'           => 'PAINTBALL MUREȘ SRL',
                'reg_com'        => 'J26/1106/2016',
                'address'        => 'Sat Ivănești 75',
                'city'           => 'Ivănești',
                'county'         => 'Mureș',
                'invoice_prefix' => 'PBM',
            ]
        );
    }
}
```

Add `CompanySeeder` to `DatabaseSeeder.php`:
```php
$this->call([CompanySeeder::class]);
```

### Step 8 – Create `CompanyScope`

Create `app/Models/Scopes/CompanyScope.php`:
```php
<?php

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

Every company-scoped model must register this scope AND auto-assign `company_id` on create:
```php
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

### Step 9 – Create `SetActiveCompany` middleware

```bash
docker compose exec app php artisan make:middleware SetActiveCompany
```

**`app/Http/Middleware/SetActiveCompany.php`**:
```php
<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;

class SetActiveCompany
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (auth()->check() && ! session()->has('active_company_id')) {
            $first = Company::first();
            if ($first) {
                session(['active_company_id' => $first->id]);
            }
        }

        return $next($request);
    }
}
```

Register it in `bootstrap/app.php` inside `withMiddleware`:
```php
$middleware->web(append: [
    \App\Http\Middleware\SetActiveCompany::class,
]);
```

### Step 10 – Create Company Switcher Livewire component

```bash
docker compose exec app php artisan make:livewire CompanySwitcher
```

**`app/Livewire/CompanySwitcher.php`**:
```php
<?php

namespace App\Livewire;

use App\Models\Company;
use Livewire\Component;

class CompanySwitcher extends Component
{
    public function switchTo(int $companyId): void
    {
        session(['active_company_id' => $companyId]);
        $this->redirect(request()->header('Referer') ?? '/admin');
    }

    public function render()
    {
        return view('livewire.company-switcher', [
            'companies'     => Company::all(),
            'activeCompany' => Company::find(session('active_company_id')),
        ]);
    }
}
```

**`resources/views/livewire/company-switcher.blade.php`**:
```blade
<div class="flex items-center gap-2 px-2">
    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
        {{ $activeCompany?->name ?? 'Selectează firma' }}
    </span>
    <x-filament::dropdown>
        <x-slot name="trigger">
            <x-filament::icon-button icon="heroicon-o-building-office-2" />
        </x-slot>
        @foreach($companies as $company)
            <x-filament::dropdown.item wire:click="switchTo({{ $company->id }})">
                {{ $company->name }}
            </x-filament::dropdown.item>
        @endforeach
    </x-filament::dropdown>
</div>
```

Register in **`app/Providers/Filament/AdminPanelProvider.php`** inside the `panel()` chain:
```php
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

->renderHook(
    PanelsRenderHook::TOPBAR_START,
    fn () => Blade::render("@livewire('company-switcher')")
)
```

### Step 11 – Create queue jobs table and run all migrations

```bash
docker compose exec app php artisan queue:table
docker compose exec app php artisan migrate --seed
```

---

## Acceptance Criteria
- [ ] `docker compose up -d` starts all 5 services (app, nginx, mysql, queue, scheduler) with no errors.
- [ ] `docker compose exec app php artisan migrate --seed` completes without errors.
- [ ] Two companies exist in `companies` table: NOD CONSULTING SRL (CUI 27864858) and PAINTBALL MUREȘ SRL (CUI 36408451).
- [ ] `http://localhost/admin` shows the Filament login page.
- [ ] After login, the Company Switcher dropdown is visible in the top bar.
- [ ] Switching companies updates `session('active_company_id')`.
- [ ] `make fresh` (migrate:fresh --seed) works from the host.
- [ ] All user-visible text is in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
