# FACTURA2 – Invoicing Module

## Context
Core invoicing module covering four document types: invoice (`factura`), proforma (`proforma`), receipt (`chitanta`), and delivery note (`aviz`). Each company has its own numeric series. Invoices can be generated manually or from a contract.

## Prerequisites
- `Client`, `Contract`, `Product` models must exist.
- `InvoiceType` and `InvoiceStatus` enums must be created.
- PDF service infrastructure must be in place.

## Task
In the FACTURA2 project, implement the complete Invoicing module.

### 1. Enums

**`app/Enums/InvoiceType.php`:**
```php
enum InvoiceType: string {
    case Factura  = 'factura';
    case Proforma = 'proforma';
    case Chitanta = 'chitanta';
    case Aviz     = 'aviz';

    public function label(): string { /* Romanian labels */ }
    public function prefix(): string { /* e.g. 'F', 'P', 'C', 'A' */ }
}
```

**`app/Enums/InvoiceStatus.php`:**
```php
enum InvoiceStatus: string {
    case Draft   = 'draft';
    case Trimisa = 'trimisa';
    case Platita = 'platita';
    case Anulata = 'anulata';

    public function label(): string { /* Romanian labels */ }
    public function color(): string { /* Filament badge colors */ }
}
```

### 2. Invoice Model & Migration

**Migration columns:**
- `id`
- `company_id` (foreign key → companies)
- `client_id` (foreign key → clients)
- `contract_id` (foreign key → contracts, nullable)
- `type` (enum: `factura`, `proforma`, `chitanta`, `aviz`)
- `status` (enum: `draft`, `trimisa`, `platita`, `anulata`)
- `series` (string – e.g. `NOD-2026`)
- `number` (integer – auto-incremented per company+series)
- `full_number` (string – computed: `NOD-2026-0001`, stored for display/search)
- `issue_date` (date)
- `due_date` (date, nullable)
- `delivery_date` (date, nullable)
- `subtotal` (decimal 15,2)
- `vat_total` (decimal 15,2)
- `total` (decimal 15,2)
- `currency` (string, default `RON`)
- `payment_method` (enum: `numerar`, `ordin_plata`, `card`, `compensare`)
- `payment_reference` (string, nullable – bank transaction reference)
- `paid_at` (datetime, nullable)
- `efactura_id` (string, nullable – ANAF upload ID)
- `efactura_status` (string, nullable)
- `pdf_path` (string, nullable)
- `notes` (text, nullable)
- `timestamps` + `softDeletes`

**Model:**
- Register `CompanyScope` in `booted()`.
- `belongsTo` Client, Contract (nullable), Company.
- `hasMany` InvoiceLines.
- Auto-generate `full_number` on `creating` using a sequence per `company_id + series`.
- On status change to `trimisa` or `platita`, dispatch `GenerateInvoicePdf` job and call `StockService::deductForInvoice()`.

### 3. InvoiceLine Model & Migration

**Migration columns:**
- `id`
- `invoice_id` (foreign key → invoices)
- `product_id` (foreign key → products, nullable – can be free-text line)
- `description` (string)
- `quantity` (decimal 15,3)
- `unit` (string)
- `unit_price` (decimal 15,2)
- `vat_rate` (decimal 5,2, default `19.00`)
- `vat_amount` (decimal 15,2 – computed)
- `line_total` (decimal 15,2 – quantity × unit_price, without VAT)
- `total_with_vat` (decimal 15,2)
- `sort_order` (integer, default `0`)
- `timestamps`

### 4. InvoiceService
Create `app/Services/InvoiceService.php`:

```php
/**
 * Generate the next invoice number for the given company and series.
 */
public function nextNumber(int $companyId, string $series): int

/**
 * Recalculate subtotal, vat_total, total from invoice lines.
 */
public function recalculateTotals(Invoice $invoice): void

/**
 * Transition invoice to a new status, trigger side effects (PDF, stock, e-Factura).
 */
public function transition(Invoice $invoice, InvoiceStatus $newStatus): void

/**
 * Create a draft invoice pre-filled from a contract.
 */
public function createFromContract(Contract $contract): Invoice
```

### 5. InvoiceResource (Filament)

**Form layout:**
- **Header section:** type (Select), client (searchable Select), contract (searchable Select, filtered by client), issue_date, due_date, payment_method
- **Lines section:** Repeater for InvoiceLines
  - product (searchable Select – auto-fills description, unit, unit_price, vat_rate)
  - description, quantity, unit, unit_price, vat_rate
  - computed line_total and total_with_vat (read-only, updated live with Alpine.js)
- **Footer section:** subtotal, vat_total, total (all read-only, computed), notes, currency

**Table columns:**
- Full number (sortable, searchable)
- Client name (searchable)
- Type badge
- Status badge (colored)
- Issue date (sortable)
- Due date (sortable, red if overdue)
- Total (RON formatted)
- e-Factura status (icon if sent)

**Filters:**
- By `type`
- By `status`
- By `client_id`
- Date range for `issue_date`
- Overdue toggle (due_date < today AND status != platita)

**Actions:**
- View, Edit (only in `draft` status), Delete (only in `draft` status)
- **"Finalizează"** – transitions from `draft` → `trimisa`, generates PDF
- **"Marchează ca plătită"** – transitions to `platita`, sets `paid_at`
- **"Anulează"** – transitions to `anulata` (with required reason note)
- **"Descarcă PDF"** – downloads the generated PDF
- **"Trimite la e-Factura"** – available only for `factura` type (see `06-efactura.prompt.md`)

### 6. PDF Generation

**`app/Jobs/GenerateInvoicePdf.php`** (queued job):
- Uses `spatie/laravel-pdf` to render `resources/views/pdf/invoice.blade.php`.
- Saves to `storage/app/invoices/{company_id}/{full_number}.pdf`.
- Updates `invoices.pdf_path` after generation.

**Blade view `resources/views/pdf/invoice.blade.php`:**
- Company logo, name, CIF, address, IBAN, bank.
- Invoice header: type label, full_number, issue_date, due_date.
- Client block: name, CIF/CNP, address.
- Lines table: description, qty, unit, unit_price, vat%, line_total.
- Totals: subtotal, VAT, **TOTAL** (bold).
- Payment info and notes footer.
- Must display amounts in Romanian format (`1.234,56 lei`).

## Acceptance Criteria
- [ ] Invoice numbering is sequential per company and never has gaps.
- [ ] Totals are always computed from lines, never entered manually.
- [ ] PDF is auto-generated when invoice is finalized.
- [ ] Status transitions enforce correct flow: `draft` → `trimisa` → `platita`; any → `anulata`.
- [ ] Overdue invoices are highlighted red in the table.
- [ ] All labels and notifications are in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
