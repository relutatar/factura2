# FACTURA2 – Clients & Contacts Module

## Context
This module manages the client base for both companies. Clients are company-scoped and can have multiple contacts. The ANAF public API is used to auto-fill company data when a CIF is entered.

## Prerequisites
- `Company` model and `CompanyScope` must already exist (see `01-setup.prompt.md`).

## Task
In the FACTURA2 project, implement the complete Clients & Contacts module.

### 1. Client Model & Migration

**Migration columns:**
- `id`
- `company_id` (foreign key → companies)
- `type` (enum: `persoana_juridica`, `persoana_fizica`)
- `name` (string)
- `cif` (string, nullable – fiscal code for companies)
- `cnp` (string, nullable – personal ID for individuals)
- `reg_com` (string, nullable)
- `address` (text)
- `city` (string)
- `county` (string)
- `country` (string, default `România`)
- `email` (string, nullable)
- `phone` (string, nullable)
- `iban` (string, nullable)
- `bank` (string, nullable)
- `notes` (text, nullable)
- `timestamps` + `softDeletes`

**Model:**
- Register `CompanyScope` in `booted()`.
- Cast `type` to `ClientType` enum.
- `hasMany` Contacts, Contracts, Invoices.

### 2. Contact Model & Migration

**Migration columns:**
- `id`
- `client_id` (foreign key → clients)
- `name` (string)
- `role` (string, nullable – e.g. "Administrator", "Contabil")
- `email` (string, nullable)
- `phone` (string, nullable)
- `timestamps` + `softDeletes`

### 3. Enums
Create `app/Enums/ClientType.php`:
```php
enum ClientType: string {
    case PersoanăJuridică = 'persoana_juridica';
    case PersoanăFizică   = 'persoana_fizica';
}
```

### 4. AnafService – CIF Lookup
Create (or extend) `app/Services/AnafService.php`:

**Method:** `lookupCif(string $cif): ?array`
- Calls the ANAF public REST API: `https://webservicesp.anaf.ro/PlatitorTvaRest/api/v8/ws/tva`
- Returns an array with `denumire`, `adresa`, `cod_postal`, `nrRegCom`, `iban` or `null` on failure.
- Cache result for 24 hours using Laravel Cache.

### 5. ClientResource (Filament)

**Form fields:**
- `type` → Select (Persoană Juridică / Persoană Fizică), triggers conditional display
- `cif` → TextInput with a **"Caută ANAF"** action button that calls `AnafService::lookupCif()` and auto-fills: `name`, `reg_com`, `address`, `city`, `county`
- `cnp` → TextInput (visible only for Persoană Fizică)
- All other fields as TextInput/Textarea
- `contacts` → repeater embedded in a **"Contacte"** tab

**Table columns:**
- Name (searchable, sortable)
- CIF/CNP (searchable)
- City
- Phone
- Type badge (colored)
- Created at (sortable)

**Filters:**
- By `type`
- By `city`

**Actions:**
- View, Edit, Delete (with confirmation)
- "Adaugă contact rapid" table row action

## Acceptance Criteria
- [ ] CIF auto-complete populates all fields from ANAF API.
- [ ] `company_id` is always set from session (never manually entered).
- [ ] Contacts are manageable from within the Client form (Tabs layout).
- [ ] Table search works on `name`, `cif`, `cnp` simultaneously.
- [ ] All labels and notifications are in **Romanian**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
