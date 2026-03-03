# FACTURA2 – Acte Adiționale și Anexe la Contracte

## Context
Un contract poate fi completat cu:
- **Acte adiționale** (`ContractAmendment`) – documente distincte cu corp de text (bazat pe un template), dată de semnare, numerotare proprie (`Act adițional nr. X la contractul Y/AAAA`) și export PDF. Pot modifica valoarea, data de expirare sau alți parametri custom ai contractului.
- **Anexe** (`ContractAnnex`) – pot fi fie fișiere atașate (upload PDF/Word/imagine), fie documente generate din template (similar cu actele adiționale, dar fără numerotare proprie).

Ambele tipuri sunt opționale per contract și sunt vizibile în pagina de vizualizare a contractului.

Actele adiționale sunt disponibile doar dacă firma are modulul `acte_aditionale` activat.

---

## Prerequisites
- `Contract` model și `ContractResource` trebuie să existe (vezi `03-contracts.prompt.md`).
- `DocumentTemplate` model trebuie să existe (sau să fie creat stub) pentru generarea documentelor din template.
- `CompanyScope` activ.
- `PdfService` existent (vezi `05-invoicing.prompt.md`).

---

## Task

### Step 1 – Creare enum `ContractAmendmentStatus`

**`app/Enums/ContractAmendmentStatus.php`**:
```php
<?php

namespace App\Enums;

enum ContractAmendmentStatus: string
{
    case Draft  = 'draft';
    case Semnat = 'semnat';
    case Anulat = 'anulat';

    public function label(): string
    {
        return match($this) {
            self::Draft  => 'Ciornă',
            self::Semnat => 'Semnat',
            self::Anulat => 'Anulat',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft  => 'gray',
            self::Semnat => 'success',
            self::Anulat => 'danger',
        };
    }
}
```

---

### Step 2 – Modele și migrări

```bash
docker compose exec app php artisan make:model ContractAmendment -m
docker compose exec app php artisan make:model ContractAnnex -m
```

**`database/migrations/xxxx_create_contract_amendments_table.php`**:
```php
Schema::create('contract_amendments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
    $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
    $table->unsignedInteger('amendment_number'); // nr. ordine per contract
    $table->date('signed_date')->nullable();
    $table->text('body');                         // conținut final (poate veni din template)
    $table->text('content_snapshot')->nullable(); // versiune înghețată la semnare
    $table->json('attributes')->nullable();       // atribute custom (valoare nouă, dată expirare nouă etc.)
    $table->enum('status', ['draft', 'semnat', 'anulat'])->default('draft');
    $table->string('pdf_path')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['contract_id', 'amendment_number']);
});
```

**`database/migrations/xxxx_create_contract_annexes_table.php`**:
```php
Schema::create('contract_annexes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
    $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
    $table->string('title');
    $table->string('annex_code')->nullable();     // ex: Anexa 1, Anexa A
    // Pentru anexe generate din template:
    $table->text('body')->nullable();
    $table->text('content_snapshot')->nullable();
    $table->json('attributes')->nullable();
    // Pentru fișiere atașate:
    $table->string('file_path')->nullable();
    $table->string('file_original_name')->nullable();
    $table->string('file_mime_type')->nullable();
    $table->string('pdf_path')->nullable();       // pentru anexe generate
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

---

### Step 3 – Modele Eloquent

**`app/Models/ContractAmendment.php`**:
```php
<?php

namespace App\Models;

use App\Enums\ContractAmendmentStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractAmendment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'contract_id', 'document_template_id',
        'amendment_number', 'signed_date', 'body', 'content_snapshot',
        'attributes', 'status', 'pdf_path', 'notes',
    ];

    protected $casts = [
        'status'      => ContractAmendmentStatus::class,
        'attributes'  => 'array',
        'signed_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model) {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
            // Auto-increment amendment_number per contract
            if (empty($model->amendment_number)) {
                $model->amendment_number = static::withoutGlobalScopes()
                    ->where('contract_id', $model->contract_id)
                    ->max('amendment_number') + 1;
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withoutGlobalScopes();
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class)->withoutGlobalScopes();
    }

    /**
     * Returns a label like: "Act adițional nr. 1 la contractul 100/2026"
     */
    public function getFullLabelAttribute(): string
    {
        $contract = $this->contract;
        return "Act adițional nr. {$this->amendment_number} la contractul {$contract->number}";
    }
}
```

**`app/Models/ContractAnnex.php`**:
```php
<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractAnnex extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'contract_id', 'document_template_id',
        'title', 'annex_code', 'body', 'content_snapshot',
        'attributes', 'file_path', 'file_original_name',
        'file_mime_type', 'pdf_path', 'notes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model) {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
        });
    }

    /** Returns true if this annex is a generated document (not a file upload) */
    public function isGenerated(): bool
    {
        return ! empty($this->document_template_id) || ! empty($this->body);
    }

    /** Returns true if this annex is an uploaded file */
    public function isFileAttachment(): bool
    {
        return ! empty($this->file_path);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withoutGlobalScopes();
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class)->withoutGlobalScopes();
    }
}
```

---

### Step 4 – Serviciu `DocumentTemplateService` (extins / creat)

Asigură-te că `app/Services/DocumentTemplateService.php` are metodele:

```php
/**
 * Render a document template body, replacing placeholders with the provided context data.
 * Placeholders format: {{key}} or {{attr.key}}
 */
public function render(DocumentTemplate $template, array $context): string;

/**
 * Get a list of available placeholder names for a given template.
 */
public function getPlaceholders(DocumentTemplate $template): array;
```

---

### Step 5 – Resurse Filament

#### `ContractAmendmentResource`

```bash
docker compose exec app php artisan make:filament-resource ContractAmendment --generate
```

**Navigation properties**:
```php
protected static ?string $navigationGroup  = 'Contracte';
protected static ?string $navigationLabel  = 'Acte Adiționale';
protected static ?string $modelLabel       = 'Act Adițional';
protected static ?string $pluralModelLabel = 'Acte Adiționale';
protected static ?string $navigationIcon   = 'heroicon-o-document-plus';
```

**Vizibilitate condiționată pe modul `acte_aditionale`**:
```php
public static function canAccess(): bool
{
    $company = \App\Models\Company::find(session('active_company_id'));
    return $company?->hasModule('acte_aditionale') ?? false;
}
```

**Form schema** (tabs: Date document / Conținut / Atribute modificate):
```php
Tabs::make()->tabs([
    Tabs\Tab::make('Date document')->schema([
        Select::make('contract_id')
            ->label('Contract')
            ->relationship('contract', 'number')
            ->searchable()->preload()->required(),
        Select::make('document_template_id')
            ->label('Șablon document')
            ->relationship('documentTemplate', 'name')
            ->searchable()->preload()->nullable(),
        TextInput::make('amendment_number')
            ->label('Număr act adițional')
            ->numeric()->required(),
        DatePicker::make('signed_date')
            ->label('Data semnării')
            ->displayFormat('d.m.Y'),
        Select::make('status')
            ->label('Status')
            ->options(ContractAmendmentStatus::class)
            ->default('draft'),
        Textarea::make('notes')->label('Observații')->rows(2),
    ]),
    Tabs\Tab::make('Conținut')->schema([
        RichEditor::make('body')
            ->label('Text act adițional')
            ->columnSpanFull()
            ->toolbarButtons([
                'bold', 'italic', 'underline', 'strike',
                'link', 'orderedList', 'bulletList',
                'h2', 'h3', 'redo', 'undo',
            ]),
    ]),
    Tabs\Tab::make('Atribute modificate')->schema([
        KeyValue::make('attributes')
            ->label('Atribute modificate prin actul adițional')
            ->helperText('Ex: valoare_noua, data_expirare_noua etc.')
            ->nullable(),
    ]),
])->columnSpanFull()
```

**Table columns**:
```php
->columns([
    TextColumn::make('amendment_number')->label('Nr. AA')->sortable(),
    TextColumn::make('contract.number')->label('Contract')->searchable(),
    TextColumn::make('contract.client.name')->label('Client')->searchable(),
    TextColumn::make('signed_date')->label('Semnat la')->date('d.m.Y')->sortable(),
    TextColumn::make('status')->label('Status')->badge()
        ->formatStateUsing(fn ($state) => $state instanceof ContractAmendmentStatus ? $state->label() : $state)
        ->color(fn ($state) => $state instanceof ContractAmendmentStatus ? $state->color() : 'gray'),
])
->defaultSort('amendment_number', 'desc')
```

**Actions**:
```php
->actions([
    Tables\Actions\ViewAction::make()->label('Vezi'),
    Tables\Actions\EditAction::make()->label('Editează')
        ->visible(fn (ContractAmendment $record) => $record->status === ContractAmendmentStatus::Draft),
    Action::make('semneaza')
        ->label('Marchează ca semnat')
        ->icon('heroicon-o-check-badge')
        ->requiresConfirmation()
        ->visible(fn (ContractAmendment $record) => $record->status === ContractAmendmentStatus::Draft)
        ->action(function (ContractAmendment $record) {
            $record->update([
                'status'           => ContractAmendmentStatus::Semnat,
                'content_snapshot' => $record->body,
                'signed_date'      => $record->signed_date ?? now(),
            ]);
            Notification::make()->title('Act adițional marcat ca semnat')->success()->send();
        }),
    Action::make('descarca_pdf')
        ->label('Descarcă PDF')
        ->icon('heroicon-o-arrow-down-tray')
        ->visible(fn (ContractAmendment $record) => ! empty($record->pdf_path))
        ->url(fn (ContractAmendment $record) => route('contract-amendments.pdf', $record))
        ->openUrlInNewTab(),
    Tables\Actions\DeleteAction::make()->label('Șterge'),
])
```

#### `ContractAnnexResource`

```bash
docker compose exec app php artisan make:filament-resource ContractAnnex --generate
```

**Navigation properties**:
```php
protected static ?string $navigationGroup  = 'Contracte';
protected static ?string $navigationLabel  = 'Anexe';
protected static ?string $modelLabel       = 'Anexă';
protected static ?string $pluralModelLabel = 'Anexe';
protected static ?string $navigationIcon   = 'heroicon-o-paper-clip';
```

**Form schema** (tabs: Date / Conținut sau fișier):
```php
Tabs::make()->tabs([
    Tabs\Tab::make('Date generale')->schema([
        Select::make('contract_id')
            ->label('Contract')
            ->relationship('contract', 'number')
            ->searchable()->preload()->required(),
        TextInput::make('title')->label('Titlu anexă')->required(),
        TextInput::make('annex_code')->label('Cod (ex: Anexa 1, Anexa A)'),
        Textarea::make('notes')->label('Observații')->rows(2),
    ]),
    Tabs\Tab::make('Conținut generat')->schema([
        Select::make('document_template_id')
            ->label('Șablon document')
            ->relationship('documentTemplate', 'name')
            ->searchable()->preload()->nullable(),
        RichEditor::make('body')
            ->label('Conținut anexă generate')
            ->columnSpanFull()
            ->helperText('Completați dacă anexa este un document generat (nu fișier atașat).')
            ->nullable(),
    ]),
    Tabs\Tab::make('Fișier atașat')->schema([
        FileUpload::make('file_path')
            ->label('Fișier anexă')
            ->directory('annexes')
            ->storeFileNamesIn('file_original_name')
            ->acceptedFileTypes(['application/pdf', 'image/*', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
            ->visibility('private')
            ->nullable()
            ->helperText('Încărcați fișierul dacă anexa nu este generată din template.'),
    ]),
])->columnSpanFull()
```

---

### Step 6 – RelationManager în `ContractResource`

Adaugă `ContractAmendmentsRelationManager` și `ContractAnnexesRelationManager` ca tab-uri în vizualizarea contractului:

```bash
docker compose exec app php artisan make:filament-relation-manager ContractResource amendments amendment_number
docker compose exec app php artisan make:filament-relation-manager ContractResource annexes title
```

Înregistrează-le în `ContractResource::getRelations()`:
```php
public static function getRelations(): array
{
    return [
        \App\Filament\Resources\ContractResource\RelationManagers\AmendmentsRelationManager::class,
        \App\Filament\Resources\ContractResource\RelationManagers\AnnexesRelationManager::class,
    ];
}
```

---

### Step 7 – PDF templates

**`resources/views/pdf/contract-amendment.blade.php`**:
```blade
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 40px; }
        h1 { font-size: 14px; text-align: center; text-transform: uppercase; }
        .subtitle { text-align: center; font-size: 11px; color: #555; margin-bottom: 20px; }
        .signatures { margin-top: 60px; }
        .signatures table { width: 100%; }
        .signatures td { width: 50%; vertical-align: top; }
    </style>
</head>
<body>
    <h1>ACT ADIȚIONAL NR. {{ $amendment->amendment_number }}</h1>
    <p class="subtitle">
        la Contractul nr. {{ $amendment->contract->number }}
        din {{ $amendment->contract->signed_date?->format('d.m.Y') ?? $amendment->contract->start_date->format('d.m.Y') }}
    </p>
    <p>Datat: {{ $amendment->signed_date?->format('d.m.Y') ?? '____.____.________' }}</p>

    <div>{!! $amendment->content_snapshot ?? $amendment->body !!}</div>

    <div class="signatures">
        <table>
            <tr>
                <td><strong>Prestator:</strong><br>{{ $amendment->contract->company->name }}<br><br>Semnătură: _______________</td>
                <td><strong>Beneficiar:</strong><br>{{ $amendment->contract->client->name }}<br><br>Semnătură: _______________</td>
            </tr>
        </table>
    </div>
</body>
</html>
```

Adaugă metodă în `PdfService`:
```php
public function generateContractAmendment(ContractAmendment $amendment): string
{
    $amendment->loadMissing(['contract.company', 'contract.client']);
    $path = storage_path("app/amendments/{$amendment->id}.pdf");
    Pdf::loadView('pdf.contract-amendment', compact('amendment'))->setPaper('a4')->save($path);
    $amendment->withoutGlobalScopes()->where('id', $amendment->id)->update(['pdf_path' => $path]);
    return $path;
}
```

---

### Step 8 – Rute PDF

În `routes/web.php` adaugă:
```php
Route::get('/contract-amendments/{amendment}/pdf', function (\App\Models\ContractAmendment $amendment) {
    $path = app(\App\Services\PdfService::class)->generateContractAmendment($amendment);
    return response()->download($path, "act-aditional-{$amendment->amendment_number}.pdf");
})->name('contract-amendments.pdf')->middleware('auth');
```

---

## Acceptance Criteria
- [x] `contract_amendments` și `contract_annexes` tabele create prin migrare.
- [x] `ContractAmendment` are numerotare automată per contract (nr. 1, 2, 3...).
- [x] Statusul actului adițional poate fi: Draft → Semnat / Anulat.
- [x] La semnare, `content_snapshot` este înghețat (conținut nu mai poate fi editat).
- [x] PDF-ul actului adițional se poate genera și descărca.
- [x] Anexa poate fi ori fișier atașat ori document generat din template (sau ambele).
- [x] Relation managers vizibile în pagina de vizualizare a contractului.
- [x] Modulul `acte_aditionale` controlează vizibilitatea `ContractAmendmentResource`.
- [x] Toate etichetele și notificările sunt în **română**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
| 2026-03-03 | Implemented module 11 end-to-end: `ContractAmendmentStatus` enum; migrations/tables for `document_templates`, `contract_amendments`, `contract_annexes`; Eloquent models with CompanyScope; `DocumentTemplateService`; Filament resources for amendments and annexes (Romanian labels/actions), contract relation managers for Amendments/Annexes, `ContractResource` view page integration, amendment status transitions with snapshot freeze on signing, amendment PDF blade + `PdfService::generateContractAmendment()`, PDF route for amendment download | Dedicated automated tests for amendments/annexes flows | Keeps DocumentTemplate as minimal stub model/service required by this prompt |
