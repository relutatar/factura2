# FACTURA2 - Decizii Administrative

## Context
Many Romanian SME controls require written internal administrative decisions.
This module introduces a dedicated section named `Decizii Administrative` with:
- common decision lifecycle and numbering (shared across all decision types)
- predefined decision templates (sabloane)
- template-specific custom attributes

Decision numbers are sequential per company and increment regardless of decision type.

---

## In scope (initial predefined templates)
1. decizie_numerotare
2. decizie_inventar
3. decizie_decontare
4. decizie_norma_combustibil
5. decizie_casare

---

## Common attributes (all decisions)

### Core metadata
- `company_id`
- `decision_template_id`
- `number` (global sequence per company, independent of template type)
- `decision_date`
- `title` (default from template, editable)
- `status` (`draft`, `issued`, `cancelled`, `archived`)
- `notes` nullable

### Legal signatory snapshot
- `legal_representative_name` (prefilled from company, editable per decision before issue)
- `legal_representative_role` (prefilled from company, default `Administrator`)

### Rendering/persistence
- `custom_attributes` JSON
- `content_snapshot` (frozen rendered content at issue/finalize)
- timestamps, soft deletes

Constraints:
- unique (`company_id`, `number`)
- immutable legal content and numbering fields after `issued`

---

## Template model (sabloane)
Create `decision_templates`:
- `id`
- `company_id` nullable (null = system predefined template, company_id set = company override/custom)
- `code` unique per scope (e.g. `decizie_numerotare`)
- `name`
- `category` default `Decizii Administrative`
- `body_template` (Blade-like placeholders)
- `custom_fields_schema` JSON (field definitions)
- `is_active`
- timestamps, soft deletes

Create `decisions`:
- fields from Common attributes section

---

## Company prerequisites
Ensure `companies` has:
- `legal_representative_name`
- `legal_representative_role` (default `Administrator`)

These values prefill each decision but remain a per-decision snapshot once issued.

---

## Predefined templates and custom attributes

### 1) `decizie_numerotare`
Body includes legal text (Art. 1-4) and links to numbering ranges.
Custom attributes:
- `fiscal_year` (number)
- `responsible_emission_person` (string)
- `mentions_cancelled_visible` (boolean, default true)

Structured linked data (not only JSON):
- `numbering_ranges` rows linked to decision (`document_type`, `series`, `start_number`, `end_number`, `next_number`, `work_point_code`, `is_active`)

### 2) `decizie_inventar`
Custom attributes:
- `commission_president` (string)
- `commission_members` (array of strings)
- `inventory_start_date` (date)
- `inventory_end_date` (date)

### 3) `decizie_decontare`
Custom attributes:
- `daily_advance_limit_ron` (decimal)
- `authorized_persons` (array of strings)
- `justification_term_working_days` (integer)

### 4) `decizie_norma_combustibil`
Custom attributes:
- `vehicle_make` (string)
- `vehicle_registration_number` (string)
- `consumption_l_per_100km` (decimal)
- `norm_basis` (enum: `carte_tehnica`, `teste_proprii`, `alta_baza`)
- `norm_basis_notes` (string, optional)

### 5) `decizie_casare`
Custom attributes:
- `disposal_committee_members` (array of strings)
- `assets` (array of objects):
  - `name`
  - `serial_number` nullable
  - `reason`
- `recycling_center_name` (string, optional)

---

## Services
Implement `DecisionService`:
- `nextDecisionNumber(Company $company): int` (transaction + lock)
- `issueDecision(Decision $decision): void`
- `renderDecisionContent(Decision $decision): string`

Rules:
- number allocated on issue/finalize, not on early draft create
- after issue: `number`, `decision_date`, signatory snapshot, `content_snapshot`, and key legal attributes become immutable

---

## Filament admin UI
Navigation group: `Decizii Administrative`

Resources:
- `DecisionResource`
- `DecisionTemplateResource`

UX:
- type/template selector
- dynamic form generated from `custom_fields_schema`
- Romanian labels
- issue action with guardrails (required legal fields)
- printable PDF download for issued decisions

---

## Audit trail
Track at minimum:
- template created/updated/disabled
- decision created/edited/issued/cancelled
- numbering reservation for decisions
- actor, company, decision_id, timestamp, result

---

## Tests (mandatory)

Feature tests:
1. decision number increments globally across different template types.
2. draft decisions can be edited; issued decisions are immutable for legal fields.
3. decision issue fails when required template attributes are missing.
4. dynamic custom attributes are validated per template schema.
5. PDF rendering includes expected legal content and signatory data.

Unit tests:
1. decision content renderer for each predefined template.
2. decision number allocator boundary/concurrency behavior.

---

## Notes
- Keep `Decizii Administrative` distinct from `Document Numbering Ranges`.
- `Document Numbering Ranges` consumes the numbering decision only as legal/source reference.
- `Subsemnatul` can remain hardcoded in the numbering decision template body.
