# Plan de implementare – Module noi FACTURA2

**Data:** 2026-06-01  
**Scope:** Acte adiționale, Anexe, Procese verbale, Proformă, Chitanță, Bonuri fiscale, Module per firmă  
**Referință prompts:** 11, 12, 13, 14, 15, 16

---

## Dependențe

```
[16-company-modules]
       │
       ├──► [11-contract-addendums-annexes]   (necesită: Company.hasModule)
       ├──► [12-work-completions]             (necesită: Company.hasModule)
       ├──► [15-fiscal-receipts]              (necesită: Company.hasModule, ConsumptionService)
              │
              └──► [08-consum-bon-consum]     (prerequisit: ConsumptionNote, ConsumptionService)

[13-proforma-invoices]
       │
       └──► [09-document-numbering-ranges]   (prerequisit: DocumentNumberService + range 'proforma')
       └──► [05-invoicing]                   (prerequisit: Invoice model existent)

[14-receipts]
       │
       └──► [09-document-numbering-ranges]   (prerequisit: DocumentNumberService + range 'chitanta')
       └──► [05-invoicing]                   (prerequisit: Invoice model + InvoiceService::transition())
```

---

## Milestone 1 – Company Modules (prompt 16) ✅ PRIMUL

**Obiectiv:** Activarea/dezactivarea modulelor per firmă. Toate resursele cu `canAccess()` depind de acesta.

**Pași:**
1. Migrare: `add_modules_to_companies_table` — adaugă coloana `modules JSON`.
2. `CompanyModule` enum cu 5 valori.
3. `Company::hasModule()` method + cast `modules => 'array'`.
4. `CompanyResource` form: `CheckboxList` pentru module.
5. Seeder: NOD → 4 module, PAINTBALL → 2 module.

**Estimare:** 1–2h

---

## Milestone 2 – Proformă și Chitanță (prompts 13 + 14)

> Poate fi implementat în paralel cu Milestone 3.

### 2a – Proformă (prompt 13)
1. `ProformaStatus` enum.
2. Migrări: `proformas`, `proforma_lines`.
3. `Proforma` + `ProformaLine` models cu CompanyScope.
4. `ProformaService`: `recalculateTotals()`, `emit()`, `convertToInvoice()`, `createFromContract()`.
5. `GenerateProformaPdf` job + `PdfService::generateProforma()`.
6. `ProformaResource` cu acțiunile: emite, converteste, descarcă PDF, anulează.
7. PDF template `resources/views/pdf/proforma.blade.php`.
8. NumberingRange seed: `document_type = 'proforma'` pentru ambele firme.

### 2b – Chitanță (prompt 14)
1. `ReceiptStatus` enum.
2. Migrare: `receipts`.
3. `Receipt` model cu CompanyScope.
4. `ReceiptService`: `createForInvoice()`, `cancel()`.
5. `GenerateReceiptPdf` job + `PdfService::generateReceipt()`.
6. `ReceiptResource` (read-only).
7. PDF template `resources/views/pdf/receipt.blade.php`.
8. `InvoiceService::transition()` actualizat cu auto-creare chitanță.
9. `InvoiceResource` actualizat: coloană `receipt.full_number`.
10. NumberingRange seed: `document_type = 'chitanta'` pentru ambele firme.

**Estimare 2a+2b:** 4–6h

---

## Milestone 3 – Acte adiționale și Anexe (prompt 11)

> Poate fi implementat în paralel cu Milestone 2.

1. `ContractAmendmentStatus` enum.
2. Migrări: `contract_amendments`, `contract_annexes`.
3. `ContractAmendment` model: auto-numerotare per contract în `booted()`.
4. `ContractAnnex` model: helpers `isGenerated()`, `isFileAttachment()`.
5. `DocumentTemplate` model: dacă nu există deja, creare cu câmpurile: `company_id`, `context_type`, `body_template`.
6. `ContractAmendmentResource` + `ContractAmendmentRelationManager` în `ContractResource`.
7. `ContractAnnexResource` + `ContractAnnexRelationManager` în `ContractResource`.
8. `PdfService::generateContractAmendment()` + template blade.
9. Ambele resurse cu `canAccess()` → modul `acte_aditionale`.

**Estimare:** 3–4h

---

## Milestone 4 – Procese Verbale (prompt 12)

1. `WorkCompletionStatus` enum.
2. Migrare: `work_completions`.
3. `WorkCompletion` model: auto-numerotare secvențială per firmă per an.
4. `WorkCompletionService`: `createDraft()`, `sign()`, `buildContext()`.
5. `WorkCompletionResource` + `WorkCompletionRelationManager` în `ContractResource`.
6. `PdfService::generateWorkCompletion()` + template blade.
7. `canAccess()` → modul `procese_verbale`.

**Estimare:** 2–3h

---

## Milestone 5 – Bonuri Fiscale (prompt 15)

> Dependință: `ConsumptionService` din prompt 08 trebuie să fie deja implementat.

1. `FiscalReceiptStatus` enum.
2. Migrări: `fiscal_receipts`, `fiscal_receipt_lines`.
3. `FiscalReceipt` + `FiscalReceiptLine` models.
4. `FiscalReceiptService`: `register()` + `cancel()`.
5. `FiscalReceiptResource` cu acțiunile: înregistrează, anulează.
6. `canAccess()` → modul `bonuri_fiscale`.
7. Testare integrare cu `ConsumptionService` (stoc bile).

**Estimare:** 2–3h

---

## Ordine recomandată de implementare

```
1. Milestone 1  (Company Modules)       ← deblocheaza tot restul
2. Milestone 2a (Proformă)              ← independent
3. Milestone 2b (Chitanță)              ← după Proformă + InvoiceService
4. Milestone 3  (Acte adiționale)       ← independent
5. Milestone 4  (Procese verbale)       ← după Acte adiționale (DocumentTemplate)
6. Milestone 5  (Bonuri fiscale)        ← după ConsumptionService (prompt 08)
```

---

## Checklist global

### Company Modules
- [ ] Migrare `modules` JSON pe `companies`
- [ ] `CompanyModule` enum
- [ ] `Company::hasModule()` method
- [ ] `CompanyResource` cu CheckboxList
- [ ] Seeder actualizat

### Proformă
- [ ] `ProformaStatus` enum
- [ ] Migrări `proformas` + `proforma_lines`
- [ ] `Proforma` + `ProformaLine` models
- [ ] `ProformaService` (emit, convertToInvoice, createFromContract)
- [ ] `GenerateProformaPdf` job
- [ ] `ProformaResource` + acțiuni
- [ ] PDF template proformă
- [ ] NumberingRange seed `proforma`

### Chitanță
- [ ] `ReceiptStatus` enum
- [ ] Migrare `receipts`
- [ ] `Receipt` model
- [ ] `ReceiptService` (createForInvoice, cancel)
- [ ] `GenerateReceiptPdf` job
- [ ] `ReceiptResource` (read-only)
- [ ] PDF template chitanță
- [ ] `InvoiceService` auto-creare chitanță
- [ ] NumberingRange seed `chitanta`

### Acte adiționale și Anexe
- [ ] `ContractAmendmentStatus` enum
- [ ] Migrări `contract_amendments` + `contract_annexes`
- [ ] `ContractAmendment` model (auto-nr per contract)
- [ ] `ContractAnnex` model (dual: fișier / generat)
- [ ] `DocumentTemplate` model (dacă lipsește)
- [ ] `ContractAmendmentResource` + RelationManager
- [ ] `ContractAnnexResource` + RelationManager
- [ ] PDF template act adițional
- [ ] `canAccess()` pe modul `acte_aditionale`

### Procese verbale
- [ ] `WorkCompletionStatus` enum
- [ ] Migrare `work_completions`
- [ ] `WorkCompletion` model (auto-nr per firmă/an)
- [ ] `WorkCompletionService`
- [ ] `WorkCompletionResource` + RelationManager
- [ ] PDF template PV
- [ ] `canAccess()` pe modul `procese_verbale`

### Bonuri fiscale
- [ ] `FiscalReceiptStatus` enum
- [ ] Migrări `fiscal_receipts` + `fiscal_receipt_lines`
- [ ] `FiscalReceipt` + `FiscalReceiptLine` models
- [ ] `FiscalReceiptService` (register, cancel)
- [ ] `FiscalReceiptResource` + acțiuni
- [ ] Integrare `ConsumptionService`
- [ ] `canAccess()` pe modul `bonuri_fiscale`

---

## Development Log

| Date | Milestone | Implemented | Pending | Blockers |
|---|---|---|---|---|
| — | — | — | Everything | Not started |
