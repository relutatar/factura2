# FACTURA2 – Products & Stock Module

## Context
The application tracks product/substance inventory for both companies:
- **NOD CONSULTING** – DDD chemicals and substances (e.g. Solfac, Detia gas, traps)
- **PAINTBALL MUREȘ** – Paintballs (ammunition), CO2 cartridges, protective gear, equipment

Stock is automatically deducted when an invoice is finalized.

## Prerequisites
- `Company` model and `CompanyScope` must exist (see `01-setup.prompt.md`).
- `Invoice` model must exist before wiring the stock deduction trigger (see `05-invoicing.prompt.md`).

## Task
In the FACTURA2 project, implement the complete Products & Stock module.

### 1. Product Model & Migration

**Migration columns:**
- `id`
- `company_id` (foreign key → companies)
- `code` (string – internal product code, unique per company)
- `name` (string)
- `description` (text, nullable)
- `unit` (string – e.g. `litru`, `kg`, `bucată`, `cutie`)
- `unit_price` (decimal 15,2)
- `vat_rate` (decimal 5,2 – default `19.00`)
- `stock_quantity` (decimal 15,3 – supports fractional units like liters)
- `stock_minimum` (decimal 15,3, default `0` – alert threshold)
- `category` (string, nullable – e.g. `substanță DDD`, `echipament`, `muniție`)
- `is_active` (boolean, default `true`)
- `timestamps` + `softDeletes`

**Model:**
- Register `CompanyScope` in `booted()`.
- `hasMany` StockMovements.
- `hasMany` InvoiceLines.
- Accessor `isLowStock(): bool` → returns `true` when `stock_quantity <= stock_minimum`.

### 2. StockMovement Model & Migration

**Migration columns:**
- `id`
- `company_id` (foreign key → companies)
- `product_id` (foreign key → products)
- `invoice_id` (foreign key → invoices, nullable – set when deducted by invoice)
- `type` (enum: `intrare`, `iesire`, `ajustare`)
- `quantity` (decimal 15,3 – positive for intrare, negative stored with sign for iesire)
- `unit_price` (decimal 15,2, nullable – purchase price for intrare)
- `notes` (string, nullable)
- `moved_at` (datetime – when the movement happened)
- `timestamps` + `softDeletes`

**Model:**
- Register `CompanyScope` in `booted()`.
- `belongsTo` Product, Invoice (nullable), Company.
- After every `created` event, update `products.stock_quantity` accordingly.

### 3. StockService
Create `app/Services/StockService.php`:

```php
/**
 * Record an incoming stock movement (purchase/receipt).
 */
public function recordEntry(Product $product, float $quantity, float $unitPrice, ?string $notes = null): StockMovement

/**
 * Deduct stock for all lines of a finalized invoice.
 * Called automatically when an Invoice transitions to status 'trimisa' or 'platita'.
 */
public function deductForInvoice(Invoice $invoice): void

/**
 * Check all products for the active company and return those below minimum stock.
 */
public function getLowStockProducts(): Collection
```

### 4. ProductResource (Filament)

**Form fields:**
- `code`, `name`, `description` (Textarea)
- `unit` (Select with common units: litru, kg, bucată, cutie, set, doză)
- `unit_price` (TextInput, numeric, suffix `RON`)
- `vat_rate` (TextInput, numeric, suffix `%`, default `19`)
- `stock_quantity` (TextInput, numeric – current stock)
- `stock_minimum` (TextInput, numeric – minimum stock threshold)
- `category` (TextInput with suggestions)
- `is_active` (Toggle)

**Table columns:**
- Code (sortable, searchable)
- Name (sortable, searchable)
- Category
- Stock quantity with unit (colored **red** if below minimum)
- Unit price (RON)
- Active badge

**Filters:**
- By `is_active`
- By `category`
- Stock alert filter (only low-stock items)

**Actions:**
- View, Edit, Delete (with confirmation, only if no invoice lines exist)
- **"Înregistrează intrare stoc"** – modal form to record a stock entry (quantity + unit price)

### 5. StockMovementResource (Filament)

**Read-only table** (no create/edit form, managed via Product action):
- Date, Product name, Type badge, Quantity, Invoice link (if applicable), Notes

**Filters:**
- By `type`
- By `product_id`
- Date range

## Acceptance Criteria
- [ ] Stock quantity updates automatically after `StockMovement` is created.
- [ ] Products below minimum threshold display a red badge in the table.
- [ ] Finalizing an invoice triggers `StockService::deductForInvoice()`.
- [ ] Stock movements table is read-only and linked to the relevant invoice when applicable.
- [ ] All labels and notifications are in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
