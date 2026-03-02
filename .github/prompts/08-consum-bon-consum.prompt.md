# FACTURA2 - Bon de Consum & Decuplarea Vanzare-Consum

## Context
Aplicatia trebuie sa permita vanzarea de servicii fara a afisa consumabilele pe factura, dar cu scaderea corecta din stoc la cost de achizitie.

Fluxul tinta:
1. `NIR` (intrare stoc la cost de achizitie).
2. `Factura/Bon` (venit catre client, fara consumabile in linii).
3. `Bon de Consum` (iesire stoc + cheltuiala interna), legat de un context de business (contract, interventie, activitate zilnica).

Regula critica: evaluarea iesirii trebuie facuta cu FIFO, pe baza costului de achizitie.

---

## Prerequisites
- Modulele existente `Products & Stock` si `Invoicing` trebuie sa fie functionale.
- `StockMovement` exista deja si trebuie extins, nu duplicat.
- Multi-company (`company_id` + `CompanyScope`) ramane obligatoriu.

---

## Task

### Step 1 - Extinde tipurile de miscare de stoc

Actualizeaza `app/Enums/StockMovementType.php`:
- pastreaza: `intrare`, `iesire`, `ajustare`
- adauga:
  - `consum` (iesire prin Bon de Consum)
  - `transfer` (optional, pentru viitor)

UI labels in romana:
- `consum` -> `Consum`

---

### Step 2 - Introdu gestiuni (source warehouse)

Creeaza model + migration `warehouses`:
- `id`, `company_id`, `code`, `name`, `is_default`, timestamps, soft deletes
- unique: `company_id + code`

Extinde `products`:
- adauga `is_consumable` (bool, default true)
- optional: `item_kind` enum (`serviciu`, `consumabil`, `echipament`)

Extinde `stock_movements`:
- adauga `warehouse_id` (required)
- adauga `reference_type` + `reference_id` (polymorphic pentru legatura cu Bon Consum / Factura / NIR)
- adauga `document_type` + `document_number` (pentru trasabilitate rapida)
- adauga `unit_cost` (cost evaluat, separat de `unit_price` daca ai nevoie de backward compatibility)

---

### Step 3 - Creeaza Bon de Consum (documentul)

Genereaza:
- `consumption_notes` (header)
- `consumption_note_lines` (linii)

`consumption_notes` fields:
- `id`, `company_id`, `warehouse_id`
- `number` (secvential per companie)
- `issued_at` (date)
- `reason` (obligatoriu, justificare fiscala)
- `issued_by_name` (gestionar)
- `received_by_name` (solicitant/primitor)
- `source_account` (ex: 3028)
- `expense_account` (ex: 6028)
- `context_type` + `context_id` (contract/proiect/interventie/activitate)
- `status` (`draft`, `posted`, `cancelled`)
- `notes`
- timestamps + soft deletes

`consumption_note_lines` fields:
- `id`, `consumption_note_id`, `product_id`
- `uom` (snapshot unitate)
- `quantity`
- `unit_cost` (calculat FIFO la postare)
- `line_total_cost`
- `fifo_breakdown` (json optional, audit)
- timestamps

Constrain:
- `quantity > 0`
- doar produse `is_consumable = true`

---

### Step 4 - Introdu loturi FIFO

Pentru calcul robust FIFO, creeaza:
- `stock_layers`

Fields:
- `id`, `company_id`, `warehouse_id`, `product_id`
- `source_type`, `source_id` (NIR/intrare manuala)
- `received_at`
- `qty_in`
- `qty_remaining`
- `unit_cost`
- timestamps

Reguli:
- Intrarile de stoc creeaza `stock_layers`.
- Iesirile de tip `consum` aloca cantitati din cele mai vechi loturi cu `qty_remaining > 0`.
- Pentru fiecare alocare, scade `qty_remaining`.
- Daca stoc insuficient: blocare cu eroare explicita.

---

### Step 5 - Service layer (logica de business)

Creeaza servicii:
- `ConsumptionService`
- `FifoAllocatorService`
- `DocumentNumberService` (reuse daca exista deja in proiect)

Metode minime:
- `createDraft(array $data): ConsumptionNote`
- `post(ConsumptionNote $note): void`
- `cancel(ConsumptionNote $note): void`
- `allocate(Product $product, Warehouse $warehouse, float $qty): FifoAllocationResult`

Comportament la `post()`:
1. valideaza linii + stoc disponibil.
2. calculeaza FIFO pentru fiecare linie.
3. scrie `unit_cost` + `line_total_cost` pe linii.
4. creeaza `stock_movements` tip `consum` cu `reference_type/reference_id`.
5. update stoc agregat pe produs (compatibil cu logica actuala).
6. marcheaza documentul `posted`.

Atomicitate:
- `DB::transaction()` obligatoriu pentru postare/anulare.

---

### Step 6 - Integrare cu cazurile de business

#### Caz A - Dezinsectie
- adauga actiune `Consum materiale` din contextul interventiei/contractului.
- tehnicianul introduce cantitati reale.
- sistemul creeaza draft BC pre-legat de context.

#### Caz B - Paintball
1. `Eveniment mare`: BC global (consum total bile).
2. `Pachet Start`: defineste reteta consum (BOM) pentru articol de serviciu.
3. `Cadou/Fidelizare`: BC manual cu motiv marketing.

Pentru scenariul de reteta:
- creeaza `service_recipes` + `service_recipe_items`
- la actiune dedicata (`Genereaza BC din reteta`) prepopuleaza liniile BC.
- nu deducta automat la finalizare factura pana nu validezi functionalitatea in productie.

---

### Step 7 - UI Filament

Resurse noi:
- `ConsumptionNoteResource`
- `WarehouseResource`
- optional `ServiceRecipeResource`

In `ConsumptionNoteResource`:
- tab `Date document`
- tab `Linii consum`
- tab `Context`
- actiuni:
  - `Posteaza Bon`
  - `Anuleaza Bon`
  - `Descarca PDF`

Validari UI:
- justificare obligatorie
- gestionar + primitor obligatorii
- nu permite editare linii dupa `posted`

---

### Step 8 - PDF Bon de Consum (obligatoriu legal)

Creeaza view PDF dedicat, cu aceste campuri:
1. Date firma: denumire, CUI, sediu.
2. Titlu document: `BON DE CONSUM`, numar, data.
3. Gestiunea sursa.
4. Tabel:
   - denumire bun
   - UM
   - cantitate
   - pret unitar de achizitie
   - valoare
5. Justificare (explicatie) - obligatoriu.
6. Gestionar (eliberat de) + solicitant/primit.
7. Cont sursa + cont cheltuiala.

Pastreaza stilul A4 existent (margini si fara border extern), consistent cu celelalte documente.

---

### Step 9 - Raportare si profitabilitate

Adauga un query de analiza costuri:
- cost consum per:
  - contract
  - client
  - interval
  - tip context

Indicator minim:
- `profit brut operational = venit facturat - consumuri alocate`

---

### Step 10 - Testare minima obligatorie

Feature tests:
1. nu poti posta BC fara stoc suficient.
2. FIFO foloseste loturile in ordinea intrarii.
3. BC postat creeaza miscari tip `consum`.
4. anularea BC repune stocul corect (prin miscare inversa sau rollback controlat).
5. izolarea pe `company_id` este respectata.
6. PDF BC contine toate mentiunile obligatorii.

---

## Notes de design
- Nu afisa consumabilele pe factura clientului daca articolul vandut este serviciu.
- Nu folosi `unit_price` de factura pentru evaluare consum.
- Foloseste costurile din loturi FIFO.
- Leaga orice BC de un context de business pentru audit.
