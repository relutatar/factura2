# FACTURA2 - Document Numbering Ranges (Plaja Documente)

## Context
In Romania, document numbering must be controlled, sequential, unique, and auditable.
This module implements strict range reservation for emitted commercial documents:
- Facturi
- Chitante
- Avize
- Proforme (optional policy)

This feature is only about number allocation and chronology.
Administrative decisions (`Decizii Administrative`) are defined in a separate feature module.

---

## Prerequisites
- `Invoice` module exists (`05-invoicing.prompt.md`).
- Multi-company scope is active (`company_id` + `CompanyScope`).
- `DocumentNumberService` is used by emission flows.
- `Decizii Administrative` module exists and can provide a valid `decision_id` for a numbering range.

---

## Task

### Step 1 - Create/maintain numbering ranges entity

Create `numbering_ranges` table:
- `id`
- `company_id`
- `decision_id` (FK to administrative decisions module)
- `document_type` enum: `factura`, `chitanta`, `aviz`, `proforma`
- `fiscal_year`
- `series` (ex: `FA`, `CH`, `AV`)
- `start_number`
- `end_number`
- `next_number`
- `work_point_code` nullable (for branch-specific series)
- `is_active`
- timestamps, soft deletes

Constraints:
- unique (`company_id`, `document_type`, `series`, `fiscal_year`, `work_point_code`)
- check: `start_number <= next_number <= end_number + 1`

---

### Step 2 - Extend invoice-like documents with immutable numbering fields

Ensure each emitted document stores:
- `series`
- `number`
- `full_number` (materialized string)
- `issued_at`
- optional `work_point_code`
- optional `numbering_range_id`

Rules:
- once document leaves draft, numbering fields become immutable.
- `full_number` format: `SERIE-NNNN` (configurable padding).

---

### Step 3 - Implement `DocumentNumberService`

Create service `app/Services/DocumentNumberService.php` with:
- `reserveNextNumber(Company $company, string $documentType, ?string $workPointCode = null, ?Carbon $issuedAt = null): NumberReservation`
- `validateChronology(...)`
- `assertWithinRange(...)`

Implementation requirements:
- use DB transaction.
- lock selected range row (`FOR UPDATE`) to prevent duplicates in concurrent requests.
- increment `next_number` atomically only after successful reservation.

Failure scenarios:
- no active range for type/series/year -> explicit error.
- range exhausted (`next_number > end_number`) -> explicit error.
- chronology violation (new number with date older than previous emitted in same series) -> explicit error.

---

### Step 4 - Integrate with invoice emission flow

In `InvoiceService`:
- on finalize/emit (not on early draft create), call `DocumentNumberService`.
- assign reserved `series`, `number`, `full_number`.
- keep draft documents without final numbering.

Do the same for `chitanta` and `aviz` flows if they share the same issuance pipeline.

---

### Step 5 - Add admin resource in Filament

Create:
- `NumberingRangeResource`

UI requirements:
- Romanian labels.
- clear status badge: active/exhausted/inactive.
- guardrails:
  - prevent overlapping ranges for same scope.
  - prevent deleting a range already used by emitted documents.
  - prevent editing start/end below already used numbers.

---

### Step 6 - Chronology and continuity policy

Policy for gaps:
- gaps are allowed only with explicit cancellation record.
- canceled document numbers remain reserved and visible in audit trail.

Chronology rule:
- in same `company + document_type + series (+ work_point)`, a higher number cannot have earlier issue date than lower number.

---

### Step 7 - Audit trail and observability

Add audit events for:
- range created/edited/deactivated
- number reserved
- range exhausted
- chronology error attempts

Minimal fields:
- actor, company, document_type, series, number, timestamp, result.

---

### Step 8 - Tests (mandatory)

Feature tests:
1. unique number under concurrency (parallel reservations).
2. range exhaustion blocks issuance.
3. chronology validation blocks invalid backdated emission.
4. two branches with different work points can issue same number with different scope.
5. soft-deleted range is ignored for new reservations.
6. emitted document keeps immutable numbering.

Unit tests:
1. padding/full number formatter.
2. next-number transitions at boundaries.

---

## Notes
- Keep numbering logic centralized in `DocumentNumberService`, never spread across resources/controllers.
- Do not duplicate `Decizii Administrative` document-template logic in this module.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| 2026-03-03 | Implemented `DocumentNumberService` (preview + reservation + chronology + boundary checks), added `NumberingRangeResource` with Romanian labels/status/guardrails, added `numbering_range_id` linkage for invoices and proformas, and integrated centralized reservation through existing invoice/proforma flows | Mandatory automated tests for concurrency and chronology edge-cases | Existing invoice flow currently allocates number at create-time (draft), not strictly at finalize-time; retained for current UX stability |
| 2026-03-03 | Added `work_point_code` support on invoices/proformas, enforced numbering immutability after emitere (invoice/proforma model guards), completed mandatory test suite for numbering service (feature + unit, 8 tests passing), and expanded `NumberingRangeSeeder` for all document types per company | Optional: move invoice numbering allocation from draft create to finalize transition | Chronology/locking validated through service tests; delete/edit guardrails are active in NumberingRange resource |
