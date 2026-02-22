# FACTURA2 – Contracts Module

## Context
Contracts link clients to recurring services. Each company has a different contract type with different fields. NOD CONSULTING uses DDD maintenance contracts; PAINTBALL MUREȘ uses event/session contracts.

## Prerequisites
- `Client` model must exist (see `02-clients-contacts.prompt.md`).
- `ContractType` enum must be available.

## Task
In the FACTURA2 project, implement the complete Contracts module.

### 1. ContractType Enum
Create `app/Enums/ContractType.php`:
```php
enum ContractType: string {
    case MentenantaDDD       = 'mentenanta_ddd';
    case EvenimentPaintball  = 'eveniment_paintball';

    public function label(): string {
        return match($this) {
            self::MentenantaDDD      => 'Mentenanță DDD',
            self::EvenimentPaintball => 'Eveniment Paintball',
        };
    }
}
```

### 2. Contract Model & Migration

**Migration columns:**
- `id`
- `company_id` (foreign key → companies)
- `client_id` (foreign key → clients)
- `type` (enum: `mentenanta_ddd`, `eveniment_paintball`)
- `number` (string – contract number, unique per company)
- `title` (string)
- `start_date` (date)
- `end_date` (date, nullable)
- `value` (decimal 15,2 – total contract value)
- `currency` (string, default `RON`)
- `billing_cycle` (enum: `lunar`, `trimestrial`, `anual`, `unic`)
- `status` (enum: `activ`, `suspendat`, `expirat`, `reziliat`)
- `ddd_locations` (json, nullable – for NOD: list of locations/objectives with address and treatment type)
- `ddd_frequency` (string, nullable – for NOD: tratament lunar, bilunar etc.)
- `paintball_sessions` (integer, nullable – for Paintball: number of sessions included)
- `paintball_players` (integer, nullable – for Paintball: number of players per session)
- `notes` (text, nullable)
- `timestamps` + `softDeletes`

**Model:**
- Register `CompanyScope` in `booted()`.
- Cast `type` → `ContractType`, `status` → `ContractStatus`, `billing_cycle` → `BillingCycle`.
- `belongsTo` Client, Company.
- `hasMany` Invoices.

### 3. Additional Enums
Create `app/Enums/ContractStatus.php` and `app/Enums/BillingCycle.php` with the values defined above and Romanian labels.

### 4. ContractResource (Filament)

**Form layout – use Tabs:**
- **Tab "General":** number, client (searchable select), type, title, start_date, end_date, value, currency, billing_cycle, status, notes
- **Tab "DDD (NOD)":** visible only when `type === mentenanta_ddd`
  - `ddd_frequency` (Select with common options)
  - `ddd_locations` (repeater: location name, address, treatment type)
- **Tab "Paintball":** visible only when `type === eveniment_paintball`
  - `paintball_sessions`, `paintball_players`

**Table columns:**
- Contract number (sortable, searchable)
- Client name (searchable)
- Type badge (colored: DDD=blue, Paintball=orange)
- Status badge (colored: activ=green, expirat=red, suspendat=yellow)
- Start / End date
- Value (RON formatted)

**Filters:**
- By `type`
- By `status`
- Date range for `end_date` (to catch expiring contracts)

**Actions:**
- View, Edit, Delete (with confirmation)
- **"Generează Factură"** – creates a new Invoice pre-filled from this contract and redirects to InvoiceResource edit page
- **"Trimite notificare expirare"** – sends email reminder (stub, no actual email sending required initially)

### 5. PDF Template
Create a Blade view `resources/views/pdf/contract.blade.php`:
- Company header with logo, name, CIF, address.
- Contract details block (number, client, type, dates, value).
- DDD or Paintball specific section depending on type.
- Signature block.

**PdfService** method `generateContract(Contract $contract): string` – returns the PDF path in `storage/app/contracts/`.

## Acceptance Criteria
- [ ] Contract form shows/hides DDD or Paintball tab based on type selection.
- [ ] "Generează Factură" action creates a pre-filled draft Invoice.
- [ ] Contracts expiring within 30 days are highlighted in the table (custom row color).
- [ ] PDF can be downloaded from the View action.
- [ ] All labels and notifications are in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
