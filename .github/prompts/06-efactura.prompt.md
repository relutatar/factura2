# FACTURA2 – e-Factura (ANAF) Integration

## Context
Romanian law requires companies above the e-Factura threshold to submit invoices electronically to ANAF (National Agency for Public Revenue). Each company has its own digital certificate (`.pfx` file). The integration uses `pristavu/laravel-anaf` (or the latest compatible package for 2026).

## Prerequisites
- `Invoice` model with `efactura_id` and `efactura_status` columns must exist (see `05-invoicing.prompt.md`).
- `Company` model with `efactura_settings` JSON column must exist.
- Redis queue driver must be configured.

## Task
In the FACTURA2 project, implement the full e-Factura (ANAF) integration.

### 1. Company e-Factura Settings
Extend the company form in Filament to include an **"e-Factura"** tab with:
- `pfx_file` – file upload field (stores to `storage/app/efactura/{company_id}/cert.pfx`)
- `pfx_password` – password field (encrypted, stored in `efactura_settings->pfx_password`)
- `efactura_env` – Select: `test` (sandbox) or `productie` (live ANAF)

Store all e-Factura settings in the `efactura_settings` JSON column using `castJson`.

### 2. XML Generation (UBL 2.1)
Create `app/Services/AnafService.php` method:

```php
/**
 * Generate the UBL 2.1 XML for an invoice, conforming to Romanian e-Factura specifications (RO_CIUS).
 * Returns the XML string.
 */
public function generateXml(Invoice $invoice): string
```

XML requirements:
- Schema: `urn:oasis:names:specification:ubl:schema:xsd:Invoice-2`
- Romanian CIUS profile: `urn:cen.eu:en16931:2017#compliant#urn:efactura.mfinante.ro:CIUS-RO:1.0.1`
- Must include: supplier info (company), buyer info (client), payment means, tax totals, invoice lines.
- Amounts formatted to 2 decimal places.
- Dates in ISO 8601 format (`YYYY-MM-DD`).

### 3. e-Factura Upload Job
Create `app/Jobs/SubmitEfactura.php` (queued):

```php
/**
 * Upload the UBL XML to ANAF e-Factura API using the company's PFX certificate.
 * On success: updates invoice.efactura_id and invoice.efactura_status = 'in_prelucrare'.
 * On failure: logs error, sets efactura_status = 'eroare', notifies admin via Filament notification.
 */
public function handle(): void
```

Steps:
1. Call `AnafService::generateXml($invoice)`.
2. Sign and upload via the ANAF OAuth2 + REST API using the PFX certificate.
3. Store returned `index_incarcare` as `invoice.efactura_id`.
4. Update `invoice.efactura_status`.

### 4. e-Factura Polling Job
Create `app/Jobs/PollEfacturaStatus.php` (queued, runs every 10 minutes):

```php
/**
 * Polls ANAF e-Factura API for all invoices with efactura_status = 'in_prelucrare'.
 * Updates status to 'procesat', 'respins', or keeps polling.
 * Dispatches Filament database notification to the relevant company's users on status change.
 */
public function handle(): void
```

**Scheduler registration** in `routes/console.php`:
```php
Schedule::job(PollEfacturaStatus::class)->everyTenMinutes();
```

### 5. AnafService – Full Method List

```php
class AnafService
{
    /** Look up company data by CIF from ANAF public REST API. */
    public function lookupCif(string $cif): ?array

    /** Generate UBL 2.1 XML for the invoice. */
    public function generateXml(Invoice $invoice): string

    /** Upload XML to ANAF e-Factura and return the upload index. */
    public function uploadInvoice(Invoice $invoice, Company $company): string

    /** Poll ANAF for the status of a previously uploaded invoice. */
    public function pollStatus(string $efacturaId, Company $company): string
}
```

### 6. InvoiceResource – "Trimite la e-Factura" Action
Extend `InvoiceResource` with a **"Trimite la e-Factura"** action:

- Visible only for invoices of type `factura` and status `trimisa` or `platita`.
- Disabled if `efactura_id` is already set (already sent) – show "Deja trimisă" badge instead.
- On click: dispatches `SubmitEfactura` job, shows Filament toast notification: *"Factura a fost trimisă spre procesare la e-Factura."*
- Displays current `efactura_status` as a colored badge in the table:
  - `in_prelucrare` → yellow "În prelucrare"
  - `procesat` → green "Procesată"
  - `respins` → red "Respinsă" with error details tooltip
  - `eroare` → red "Eroare"

### 7. e-Factura Status Column & Notifications
- Add an `efactura_status` badge column to the InvoiceResource table.
- When a status changes via polling, dispatch a **Filament database notification** to all users of that company:
  - Success: *"Factura [număr] a fost procesată cu succes de ANAF."*
  - Rejected: *"Factura [număr] a fost respinsă de ANAF. Verificați mesajul de eroare."*

## Acceptance Criteria
- [ ] PFX certificate upload and secure storage work per company.
- [ ] UBL XML validates against the RO_CIUS schema.
- [ ] Upload job dispatches without blocking the UI.
- [ ] Polling job runs every 10 minutes and updates invoice status.
- [ ] Filament notifications are sent in **Romanian** on status changes.
- [ ] Sandbox and production environments are switchable per company.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
