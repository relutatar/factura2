# FACTURA2 – Dashboard & Widgets

## Context
The dashboard is the landing page after login. It provides a real-time overview of key business metrics for the **active company** (selected via Company Switcher). All data is company-scoped automatically via `CompanyScope`.

## Prerequisites
- All previous modules must be implemented (Clients, Contracts, Products, Invoices).
- `CompanyScope` must be active on all models.

## Task
In the FACTURA2 project, implement the Filament dashboard with company-scoped overview widgets.

### 1. Dashboard Page
Replace the default Filament dashboard (`app/Filament/Pages/Dashboard.php`) with a custom one that:
- Uses a 3-column grid layout.
- Renders all widgets defined below.
- Displays the active company name as a page heading or sub-heading.

### 2. StatsOverview Widget
Create `app/Filament/Widgets/InvoiceStatsWidget.php` using `FilamentStatsOverview`:

**Stats (all scoped to active company, current year):**
- **Facturi emise** – total count of invoices with type `factura` this month
- **Total facturat** – sum of `total` for finalized invoices this month (formatted as `1.234,56 lei`)
- **Facturi neplătite** – count of invoices with status `trimisa` and `due_date < today`
- **Valoare restantă** – sum of `total` for overdue invoices (red trend indicator if > 0)

### 3. Unpaid Invoices Widget
Create `app/Filament/Widgets/UnpaidInvoicesWidget.php` using `FilamentTable`:

**Columns:**
- Full number (link to InvoiceResource edit)
- Client name
- Issue date
- Due date (red if overdue)
- Total (RON)
- Status badge

**Query:** invoices with `status = trimisa`, ordered by `due_date ASC`, limited to 10 rows.

**Footer action:** "Vezi toate facturile neîncasate" → links to InvoiceResource with status filter pre-applied.

### 4. Expiring Contracts Widget
Create `app/Filament/Widgets/ExpiringContractsWidget.php` using `FilamentTable`:

**Columns:**
- Contract number (link to ContractResource)
- Client name
- Type badge (DDD / Paintball)
- End date (highlighted orange if within 30 days, red if already expired)
- Status badge

**Query:** contracts expiring within 60 days (`end_date BETWEEN today AND today+60days`) or already expired, ordered by `end_date ASC`, limited to 10 rows.

**Footer action:** "Gestionează contractele" → links to ContractResource.

### 5. Low Stock Alert Widget
Create `app/Filament/Widgets/LowStockWidget.php` using `FilamentTable`:

**Columns:**
- Product code + name
- Category
- Current stock (bold red if below minimum)
- Minimum stock threshold
- Unit
- "Înregistrează intrare" quick action button

**Query:** products where `stock_quantity <= stock_minimum AND is_active = true`, ordered by stock level (most critical first).

**Visible only when:** the active company has products (i.e., hide for Paintball if they don't track stock, etc. – use `canView()` logic).

### 6. Monthly Revenue Chart Widget
Create `app/Filament/Widgets/RevenueChartWidget.php` using `FilamentCharts` (line chart):

**Chart data:**
- X axis: last 12 months (label format: `Ian 2026`, `Feb 2026`, etc.)
- Y axis: sum of `total` for finalized invoices (status `trimisa` + `platita`) per month
- Currency label: RON

**Implementation note:** use Filament's built-in chart widget with `type = 'line'`. Cache the query result for 1 hour.

### 7. Widget Layout & Ordering
Register widgets in the correct dashboard column spans:

```
Row 1: [InvoiceStatsWidget – full width, 4 stats]
Row 2: [UnpaidInvoicesWidget – 2/3 width] [LowStockWidget – 1/3 width]
Row 3: [RevenueChartWidget – 2/3 width] [ExpiringContractsWidget – 1/3 width]
```

Use Filament's `getColumns()` and `columnSpan` on each widget to achieve this layout.

### 8. Navigation Groups & Icons
Ensure all Filament Resources are organized into navigation groups with Romanian labels and Heroicons:

| Resource | Group | Icon |
|---|---|---|
| ClientResource | Clienți | `heroicon-o-users` |
| ContractResource | Contracte | `heroicon-o-document-text` |
| ProductResource | Produse & Stocuri | `heroicon-o-cube` |
| StockMovementResource | Produse & Stocuri | `heroicon-o-arrow-path` |
| InvoiceResource | Facturi | `heroicon-o-receipt-percent` |
| Dashboard | (no group) | `heroicon-o-home` |

## Acceptance Criteria
- [ ] All widgets display only data for the active company.
- [ ] Switching company in the header refreshes all widget data.
- [ ] Overdue items are visually highlighted (red/orange) in all relevant widgets.
- [ ] Revenue chart shows the last 12 months with correct totals.
- [ ] Low stock widget shows a quick action to record stock entry inline.
- [ ] Navigation is grouped and labeled in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
