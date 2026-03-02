# Implementation Plan - Decizii Administrative

## 1. Scope
Build a standalone `Decizii Administrative` module with:
- shared decision entity and lifecycle
- global per-company numbering for all decision types
- template engine with dynamic custom attributes
- predefined templates for legal/operational decisions

## 2. Core requirements
1. Common decision fields across all decision types.
2. Number sequence increments globally per company (not per type).
3. Predefined templates + schema-based custom fields.
4. Snapshot and immutability after issuing.

## 3. Milestones

### M1 - Schema foundation (1 day)
- add company legal representative fields
- create `decision_templates`
- create `decisions`

Deliverable:
- migrations + models + relations

### M2 - Decision numbering and lifecycle (1 day)
- implement `DecisionService`
- transactional sequence allocation on issue
- enforce immutability post-issue

Deliverable:
- deterministic decision issue flow

### M3 - Predefined templates + schema validation (1 day)
- seed 5 predefined templates
- implement runtime custom-attributes validation from schema
- render engine for template placeholders

Deliverable:
- decision types usable without hardcoding form fields

### M4 - UI and PDF (1 day)
- `DecisionResource`
- `DecisionTemplateResource`
- dynamic custom fields UI
- issue/download actions

Deliverable:
- production-ready admin workflow

### M5 - Integration boundaries (0.5 day)
- link numbering decisions with `numbering_ranges` via `decision_id`
- keep numbering-range logic in its own module

Deliverable:
- clear separation between modules

### M6 - Tests and hardening (1 day)
- feature + unit tests for numbering, validation, immutability, rendering

Deliverable:
- regression-safe module

## 4. Common vs custom attributes

### Common (in `decisions`)
- company/template linkage
- number/date/title/status
- legal representative snapshot
- notes
- rendered content snapshot
- custom attributes JSON

### Custom (template-driven)
- `decizie_numerotare`: fiscal year, responsible person, range policy flags
- `decizie_inventar`: commission + period
- `decizie_decontare`: limit, authorized persons, settlement term
- `decizie_norma_combustibil`: vehicle + consumption norm + basis
- `decizie_casare`: committee + asset list + disposal destination

## 5. Risks and mitigation
1. Over-flexible schemas reduce legal consistency.
- Mitigation: seed locked predefined templates and restrict edits for system templates.

2. Race conditions on decision numbering.
- Mitigation: transaction + row lock/counter table strategy.

3. Divergence between rendered PDF and stored data.
- Mitigation: persist `content_snapshot` at issue time and render PDF from snapshot.

## 6. Acceptance criteria
1. Section appears as `Decizii Administrative`.
2. All five predefined templates exist and are usable.
3. Decision numbers increment globally across template types.
4. Issued decisions are immutable for legal metadata/content.
5. Numbering ranges can reference numbering decisions without coupling modules.

## 7. Rollout
1. Deploy schema + template seeds.
2. Enable decision creation/editing as draft.
3. Enable issue/PDF actions.
4. Connect numbering-range decision reference.
