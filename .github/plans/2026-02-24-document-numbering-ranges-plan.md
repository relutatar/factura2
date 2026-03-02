# Implementation Plan - Document Numbering Ranges (Distinct from Decizii Administrative)

## 1. Scope
Implement a dedicated numbering-range module that enforces:
- uniqueness
- sequential continuity
- chronology
- per-company and optional per-work-point isolation

Documents in scope:
- factura
- chitanta
- aviz
- proforma (optional by policy)

Out of scope:
- administrative decision templates/content
- decision numbering sequence management
- external ERP sync
- ANAF transport logic

## 2. Boundaries
- `Decizii Administrative` manages legal decisions, templates, and decision numbering.
- `Document Numbering Ranges` consumes `decision_id` only as traceability link.
- Invoicing consumes `DocumentNumberService` only.

## 3. Milestones

### M1 - Schema and domain (0.5-1 day)
- ensure `numbering_ranges` exists with FK to administrative decision (`decision_id`)
- add/verify immutable numbering fields on emitted docs
- add indexes and unique constraints

Deliverable:
- migrations + model + relations

### M2 - Reservation service (1 day)
- implement `DocumentNumberService`
- transaction + row lock for safe concurrency
- range exhaustion handling
- chronology validation

Deliverable:
- deterministic number reservation API

### M3 - Emission integration (0.5-1 day)
- wire invoice/chitanta/aviz emission to reservation service
- keep numbering immutable after emission

Deliverable:
- no direct number increment in resource/UI

### M4 - Admin UI (0.5-1 day)
- `NumberingRangeResource`
- validations against overlap and unsafe edits

Deliverable:
- operations can manage ranges safely

### M5 - Audit + tests (1 day)
- audit events for range lifecycle and reservations
- feature/unit tests for concurrency, chronology, boundaries

Deliverable:
- regression safety net

## 4. Technical breakdown
1. Database
- create/adjust: `numbering_ranges`
- alter docs: immutable numbering metadata

2. Services
- `DocumentNumberService`
- optional `NumberFormatService`

3. UI
- `NumberingRangeResource`

4. Tests
- concurrent reservation
- chronology checks
- range limits
- immutable numbering on emitted documents

## 5. Critical rules to enforce in code
1. Unique: no duplicate `series + number` in same scope/year/type.
2. Sequential: reserve only next available number in active range.
3. Chronology: prevent lower date on higher number in same scope.
4. Immutability: once emitted, numbering fields cannot be changed.
5. Exhaustion safety: hard stop when range is consumed.

## 6. Risks and mitigation
1. Race conditions under concurrent emission.
- Mitigation: DB transaction + `FOR UPDATE`.

2. Legacy flows still incrementing numbers directly.
- Mitigation: centralize all issuance in `DocumentNumberService`.

3. Backdated edits break chronology.
- Mitigation: block date/number edits after emission or require cancellation + reissue.

4. Missing active range configuration.
- Mitigation: pre-flight checks in UI + explicit runtime errors.

## 7. Acceptance criteria
1. Admin can define active ranges linked to a decision.
2. Emitted documents consume numbers in strict order.
3. Concurrent emissions never duplicate numbers.
4. Exhausted ranges block new emissions with actionable error.
5. Chronology violations are blocked.
6. Numbering is immutable after emission.

## 8. Rollout sequence
1. Deploy schema + service without switching all flows.
2. Migrate invoice flow to `DocumentNumberService`.
3. Enable other document types (chitanta/aviz).
4. Enable chronology strict mode after data cleanup.
