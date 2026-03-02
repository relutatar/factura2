# Implementation Plan - Bon de Consum + FIFO + Decuplare Vanzare/Consum

## 1. Scope
Implementam un modul de `Bon de Consum` care:
- scade stocul pentru materiale auxiliare fara a le afisa pe factura clientului;
- evalueaza iesirea la cost de achizitie prin FIFO;
- leaga fiecare iesire de un context de business;
- genereaza PDF valid fiscal.

Out of scope (faza curenta):
- contabilitate completa (note contabile automate in ERP extern);
- transfer multi-gestiune complex;
- retururi avansate pe lot cu recalcul istoric.

## 2. Decisions rafinate
1. Pastram separatia `venit` (factura) vs `cost` (bon consum).
2. FIFO se face pe loturi dedicate (`stock_layers`), nu doar din `stock_movements`.
3. `StockMovement` ramane jurnalul unificat de audit.
4. Bonul de consum este document explicit, cu stare `draft/posted/cancelled`.
5. Integrarea cu interventii/retete se face incremental, dupa nucleul BC + FIFO.

## 3. Milestones

### M1 - Data model & migrare (1 zi)
- adauga `warehouses`, `consumption_notes`, `consumption_note_lines`, `stock_layers`;
- extinde `stock_movements` cu campuri de referinta document;
- indexuri pentru performanta FIFO.

Deliverables:
- migrari + modele + relatii Eloquent.

### M2 - FIFO core engine (1-2 zile)
- implementare `FifoAllocatorService`;
- creare lot la intrari;
- alocare loturi la iesiri de consum;
- validare stoc insuficient.

Deliverables:
- servicii + unit tests FIFO.

### M3 - ConsumptionService + tranzactionalitate (1 zi)
- creare draft BC;
- postare BC (alocare FIFO, miscari stoc, costuri linii);
- anulare BC (strategie reversare).

Deliverables:
- service orchestration + feature tests.

### M4 - Filament UI (1-2 zile)
- `ConsumptionNoteResource` + `WarehouseResource`;
- form draft/postare, blocare editare dupa postare;
- actiuni contextuale (din contract/interventie).

Deliverables:
- UI operabil end-to-end.

### M5 - PDF legal Bon de Consum (0.5-1 zi)
- template PDF A4 cu toate campurile obligatorii;
- numerotare secventiala;
- verificare layout print.

Deliverables:
- endpoint/download PDF + test continut minim.

### M6 - Integrari cazuri business (1-2 zile)
- Dezinsectie: consum per interventie;
- Paintball:
  - consum global eveniment;
  - prepopulare BC din reteta pachet;
  - consum marketing/fidelizare.

Deliverables:
- fluxuri actionabile din UI.

## 4. Work breakdown (tehnic)
1. Database
- create: `warehouses`
- create: `consumption_notes`
- create: `consumption_note_lines`
- create: `stock_layers`
- alter: `stock_movements` (warehouse + referinte document)
- alter: `products` (`is_consumable`)

2. Domain
- Models: `Warehouse`, `ConsumptionNote`, `ConsumptionNoteLine`, `StockLayer`
- Enums: update `StockMovementType` cu `consum`

3. Services
- `FifoAllocatorService`
- `ConsumptionService`
- eventual `StockEntryService` pentru NIR/intrari standardizate

4. UI / Resources
- `ConsumptionNoteResource`
- `WarehouseResource`
- optional `ServiceRecipeResource`

5. Documents
- `resources/views/pdf/consumption-note.blade.php`
- ruta + controller/download action

6. Tests
- unit: allocator FIFO
- feature: post/cancel BC, multi-company isolation, pdf fields

## 5. Strategy pentru compatibilitate
1. Nu rupem fluxul existent de stoc/facturi.
2. `StockService::deductForInvoice()` ramane activ pentru linii de produs clasice.
3. Pentru servicii decuplate, folosim exclusiv BC.
4. Migrare graduala:
- etapa initiala: functionalitate noua in paralel;
- etapa ulterioara: mutare fluxuri business de pe iesire directa pe BC.

## 6. Riscuri si mitigari
1. Risc: concurenta la postare BC (dublu-consum).
- Mitigare: `DB transaction` + lock pe loturi candidate (`FOR UPDATE`).

2. Risc: performanta slaba FIFO pe volume mari.
- Mitigare: indexuri `(company_id, warehouse_id, product_id, qty_remaining, received_at)`.

3. Risc: inconsistente stoc agregat vs loturi.
- Mitigare: job periodic de reconciliere + test de integritate.

4. Risc: document PDF incomplet fiscal.
- Mitigare: checklist legal fix + test automat pentru campurile obligatorii.

## 7. Acceptance criteria
1. Se poate crea si posta Bon de Consum legat de contract/interventie.
2. Factura client ramane cu servicii, fara linii de consumabile.
3. Costul BC se calculeaza FIFO din costuri de achizitie.
4. Stocul se reduce corect si este auditat in `stock_movements`.
5. PDF BC contine toate mentiunile obligatorii.
6. Izolarea pe companie functioneaza corect.

## 8. Rollout propus
1. Deploy schema + servicii (fara activare UI globala).
2. Activare UI pentru o singura companie pilot.
3. Validare pe 1-2 saptamani de operare.
4. Activare completa + training scurt utilizatori.

## 9. Clarificari de business ramase (de inchis inainte de cod)
1. Strategia de anulare BC: reversare prin document nou sau editare controlata.
2. Lista oficiala de conturi implicite (`source_account`, `expense_account`) per companie.
3. Ce reprezinta exact `context_type` in MVP: contract, interventie, eveniment, zi de lucru.
4. Daca retetele (`service_recipes`) intra in MVP sau faza 2.
