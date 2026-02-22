# FACTURA2 – Dashboard Widgets

## Context
The Filament admin dashboard provides a real-time overview of the most important business KPIs: revenue totals, unpaid invoices, contracts expiring soon, and low-stock products. Each widget is scoped to the active company via `CompanyScope` / `session('active_company_id')`.

## Prerequisites
- All previous modules (01–06) must be complete.
- `Invoice`, `Contract`, `Product`, `InvoiceLine` models must exist.
- `InvoiceStatus`, `ContractStatus` enums must be available.

---

## Task

### Step 1 – Create custom Dashboard page

```bash
docker compose exec app php artisan make:filament-page Dashboard
```

**`app/Filament/Pages/Dashboard.php`**:
```php
<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ExpiringContractsWidget;
use App\Filament\Widgets\InvoiceStatsWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\UnpaidInvoicesWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon  = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Panou principal';
    protected static ?string $title           = 'Panou principal';
    protected static ?int    $navigationSort  = -2;

    public function getWidgets(): array
    {
        return [
            InvoiceStatsWidget::class,
            UnpaidInvoicesWidget::class,
            ExpiringContractsWidget::class,
            LowStockWidget::class,
            RevenueChartWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 3; // 3-column grid
    }
}
```

Register in `AdminPanelProvider`:
```php
->pages([
    \App\Filament\Pages\Dashboard::class,
])
```

---

### Step 2 – `InvoiceStatsWidget` (4 KPI cards)

```bash
docker compose exec app php artisan make:filament-widget InvoiceStatsWidget --stats-overview
```

**`app/Filament/Widgets/InvoiceStatsWidget.php`**:
```php
<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoiceStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $companyId = session('active_company_id');

        $totalRevenue = Invoice::where('company_id', $companyId)
            ->where('status', InvoiceStatus::Platita)
            ->sum('total');

        $pendingCount = Invoice::where('company_id', $companyId)
            ->where('status', InvoiceStatus::Trimisa)
            ->count();

        $pendingAmount = Invoice::where('company_id', $companyId)
            ->where('status', InvoiceStatus::Trimisa)
            ->sum('total');

        $overdueCount = Invoice::where('company_id', $companyId)
            ->where('status', InvoiceStatus::Trimisa)
            ->where('due_date', '<', now())
            ->count();

        return [
            Stat::make('Venituri încasate', number_format($totalRevenue, 2, ',', '.') . ' RON')
                ->description('Din facturi plătite')
                ->descriptionIcon('heroicon-s-banknotes')
                ->color('success'),

            Stat::make('Facturi neîncasate', $pendingCount)
                ->description(number_format($pendingAmount, 2, ',', '.') . ' RON în așteptare')
                ->descriptionIcon('heroicon-s-clock')
                ->color('warning'),

            Stat::make('Facturi restante', $overdueCount)
                ->description('Scadență depășită')
                ->descriptionIcon('heroicon-s-exclamation-circle')
                ->color($overdueCount > 0 ? 'danger' : 'gray'),

            Stat::make('Total emise (lună)', Invoice::where('company_id', $companyId)
                    ->whereMonth('issue_date', now()->month)
                    ->whereYear('issue_date', now()->year)
                    ->count())
                ->description('Facturi emise luna aceasta')
                ->descriptionIcon('heroicon-s-document-text')
                ->color('info'),
        ];
    }
}
```

---

### Step 3 – `UnpaidInvoicesWidget` (table of pending invoices)

```bash
docker compose exec app php artisan make:filament-widget UnpaidInvoicesWidget --table
```

**`app/Filament/Widgets/UnpaidInvoicesWidget.php`**:
```php
<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UnpaidInvoicesWidget extends BaseWidget
{
    protected static ?int $sort          = 2;
    protected int | string $columnSpan   = 2; // span 2 of the 3 columns

    protected static ?string $heading    = 'Facturi neîncasate';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where('status', InvoiceStatus::Trimisa)
                    ->latest('due_date')
            )
            ->columns([
                TextColumn::make('full_number')
                    ->label('Număr')
                    ->searchable(),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable(),
                TextColumn::make('issue_date')
                    ->label('Data emitere')
                    ->date('d.m.Y'),
                TextColumn::make('due_date')
                    ->label('Scadență')
                    ->date('d.m.Y')
                    ->color(fn (Invoice $record) => $record->due_date?->isPast() ? 'danger' : 'warning'),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('RON'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Deschide')
                    ->url(fn (Invoice $record) => \App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-o-arrow-top-right-on-square'),
            ])
            ->paginated(false)
            ->defaultSort('due_date', 'asc');
    }
}
```

---

### Step 4 – `ExpiringContractsWidget`

```bash
docker compose exec app php artisan make:filament-widget ExpiringContractsWidget --table
```

**`app/Filament/Widgets/ExpiringContractsWidget.php`**:
```php
<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ExpiringContractsWidget extends BaseWidget
{
    protected static ?int $sort         = 3;
    protected int | string $columnSpan  = 1;
    protected static ?string $heading   = 'Contracte care expiră în 30 zile';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Contract::query()
                    ->where('status', ContractStatus::Activ)
                    ->whereBetween('end_date', [now(), now()->addDays(30)])
                    ->orderBy('end_date')
            )
            ->columns([
                TextColumn::make('contract_number')
                    ->label('Nr. contract'),
                TextColumn::make('client.name')
                    ->label('Client'),
                TextColumn::make('end_date')
                    ->label('Expiră la')
                    ->date('d.m.Y')
                    ->color('warning'),
            ])
            ->paginated(false);
    }
}
```

---

### Step 5 – `LowStockWidget`

```bash
docker compose exec app php artisan make:filament-widget LowStockWidget --table
```

**`app/Filament/Widgets/LowStockWidget.php`**:
```php
<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort         = 4;
    protected int | string $columnSpan  = 1;
    protected static ?string $heading   = 'Stoc redus';

    /**
     * Hide this widget for companies that don't manage stock (e.g. pure service companies).
     */
    public static function canView(): bool
    {
        return Product::query()
            ->where('company_id', session('active_company_id'))
            ->where('manages_stock', true)
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('manages_stock', true)
                    ->whereRaw('stock_quantity <= minimum_stock_quantity')
                    ->orderBy('stock_quantity')
            )
            ->columns([
                TextColumn::make('name')->label('Produs'),
                TextColumn::make('stock_quantity')
                    ->label('Stoc curent')
                    ->color('danger'),
                TextColumn::make('minimum_stock_quantity')
                    ->label('Minim'),
                TextColumn::make('unit')->label('UM'),
            ])
            ->paginated(false);
    }
}
```

---

### Step 6 – `RevenueChartWidget` (12-month line chart)

```bash
docker compose exec app php artisan make:filament-widget RevenueChartWidget --chart
```

**`app/Filament/Widgets/RevenueChartWidget.php`**:
```php
<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChartWidget extends ChartWidget
{
    protected static ?int $sort        = 5;
    protected int | string $columnSpan = 'full'; // full width
    protected static ?string $heading  = 'Venituri lunare (ultimele 12 luni)';
    protected static string $color     = 'success';

    protected function getData(): array
    {
        $companyId = session('active_company_id');
        $months    = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i));

        $revenues = $months->map(fn (Carbon $month) => Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', InvoiceStatus::Platita)
            ->whereYear('paid_at', $month->year)
            ->whereMonth('paid_at', $month->month)
            ->sum('total')
        );

        return [
            'datasets' => [
                [
                    'label'           => 'Venituri (RON)',
                    'data'            => $revenues->values()->all(),
                    'fill'            => false,
                    'borderColor'     => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension'         => 0.3,
                ],
            ],
            'labels' => $months->map(fn (Carbon $m) => $m->locale('ro')->isoFormat('MMM YYYY'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

---

### Step 7 – Register widgets in `AdminPanelProvider`

In `app/Providers/Filament/AdminPanelProvider.php`, inside the `panel()` method, add:
```php
->widgets([
    \App\Filament\Widgets\InvoiceStatsWidget::class,
    \App\Filament\Widgets\UnpaidInvoicesWidget::class,
    \App\Filament\Widgets\ExpiringContractsWidget::class,
    \App\Filament\Widgets\LowStockWidget::class,
    \App\Filament\Widgets\RevenueChartWidget::class,
])
->pages([
    \App\Filament\Pages\Dashboard::class,
])
```

---

## Acceptance Criteria
- [ ] Dashboard page loads at `/admin` without errors.
- [ ] `InvoiceStatsWidget` shows correct totals scoped to active company.
- [ ] `UnpaidInvoicesWidget` lists only `status = trimisa` invoices, sorted by due date.
- [ ] Overdue invoices show the due date in red.
- [ ] `ExpiringContractsWidget` shows active contracts expiring within 30 days.
- [ ] `LowStockWidget` is hidden when the active company has no stock-managed products.
- [ ] `RevenueChartWidget` renders a 12-month line chart of paid invoice totals.
- [ ] Switching companies (via `CompanySwitcher`) immediately re-scopes all widget data.
- [ ] All labels and headings are in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
