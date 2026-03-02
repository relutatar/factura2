# FACTURA2 – Procese Verbale de Lucrări

## Context
Un proces verbal de lucrări (PV) este un document generat pe baza unui template, legat de un contract. Se emite pentru fiecare lucrare/intervenție prestată (de exemplu, o lucrare de dezinsecție trimestrială).

**Caracteristici cheie:**
- Generat din template cu placeholder-e (date contract, client, firmă, atribute dinamice ale lucrării).
- Numerotare secvențială simplă per firmă, resetată anual (PV-1/2026, PV-2/2026 etc.).
- Export PDF.
- Status: `draft` → `semnat` → `anulat`.
- Disponibil doar dacă firma are modulul `procese_verbale` activat.
- Vizibil ca Relation Manager în pagina de vizualizare a contractului.

---

## Prerequisites
- `Contract` model + `ContractResource` trebuie să existe (vezi `03-contracts.prompt.md`).
- `DocumentTemplate` model trebuie să existe (sau stub).
- `CompanyScope` activ, `PdfService` existent.
- Modulul `procese_verbale` trebuie să poată fi verificat via `$company->hasModule('procese_verbale')`.

---

## Task

### Step 1 – Enum `WorkCompletionStatus`

**`app/Enums/WorkCompletionStatus.php`**:
```php
<?php

namespace App\Enums;

enum WorkCompletionStatus: string
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

### Step 2 – Model și migrare

```bash
docker compose exec app php artisan make:model WorkCompletion -m
```

**`database/migrations/xxxx_create_work_completions_table.php`**:
```php
Schema::create('work_completions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
    $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
    // Numerotare secvențială per firmă, resetată anual
    $table->unsignedInteger('number');
    $table->unsignedSmallInteger('fiscal_year');
    $table->string('full_number')->nullable();     // ex: PV-1/2026
    $table->date('work_date');                     // data efectuării lucrării
    $table->date('signed_date')->nullable();        // data semnării PV
    $table->text('body');                           // conținut generat din template
    $table->text('content_snapshot')->nullable();   // înghețat la semnare
    $table->json('attributes')->nullable();         // atribute dinamice ale lucrării
    $table->enum('status', ['draft', 'semnat', 'anulat'])->default('draft');
    $table->string('pdf_path')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['company_id', 'number', 'fiscal_year']);
});
```

---

### Step 3 – Model Eloquent

**`app/Models/WorkCompletion.php`**:
```php
<?php

namespace App\Models;

use App\Enums\WorkCompletionStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCompletion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'contract_id', 'document_template_id',
        'number', 'fiscal_year', 'full_number',
        'work_date', 'signed_date', 'body', 'content_snapshot',
        'attributes', 'status', 'pdf_path', 'notes',
    ];

    protected $casts = [
        'status'      => WorkCompletionStatus::class,
        'attributes'  => 'array',
        'work_date'   => 'date',
        'signed_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model) {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
            if (empty($model->fiscal_year)) {
                $model->fiscal_year = now()->year;
            }
            if (empty($model->number)) {
                // Sequential per company + year, reset annually
                $model->number = static::withoutGlobalScopes()
                    ->where('company_id', $model->company_id)
                    ->where('fiscal_year', $model->fiscal_year)
                    ->lockForUpdate()
                    ->max('number') + 1;
            }
            if (empty($model->full_number)) {
                $model->full_number = "PV-{$model->number}/{$model->fiscal_year}";
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
}
```

---

### Step 4 – Serviciu `WorkCompletionService`

**`app/Services/WorkCompletionService.php`**:
```php
<?php

namespace App\Services;

use App\Enums\WorkCompletionStatus;
use App\Models\WorkCompletion;

class WorkCompletionService
{
    /**
     * Create a draft PV for a contract, optionally from a DocumentTemplate.
     */
    public function createDraft(int $contractId, array $data): WorkCompletion
    {
        $pv = WorkCompletion::create(array_merge($data, [
            'contract_id' => $contractId,
            'status'      => WorkCompletionStatus::Draft,
            'work_date'   => $data['work_date'] ?? now(),
        ]));

        if (isset($data['document_template_id']) && $data['document_template_id']) {
            $template = \App\Models\DocumentTemplate::find($data['document_template_id']);
            if ($template) {
                $rendered = app(DocumentTemplateService::class)->render($template, $this->buildContext($pv));
                $pv->update(['body' => $rendered]);
            }
        }

        return $pv;
    }

    /**
     * Sign (finalize) a WorkCompletion: freeze content_snapshot and update status.
     */
    public function sign(WorkCompletion $pv): void
    {
        $pv->update([
            'status'           => WorkCompletionStatus::Semnat,
            'content_snapshot' => $pv->body,
            'signed_date'      => $pv->signed_date ?? now(),
        ]);
    }

    /**
     * Build the template rendering context for a PV.
     */
    private function buildContext(WorkCompletion $pv): array
    {
        $pv->loadMissing(['contract.client', 'contract.company']);
        $contract = $pv->contract;

        return array_merge([
            'pv.number'        => $pv->full_number,
            'pv.work_date'     => $pv->work_date?->format('d.m.Y'),
            'contract.number'  => $contract->number,
            'client.name'      => $contract->client->name,
            'client.address'   => $contract->client->address,
            'company.name'     => $contract->company->name,
        ], collect($pv->attributes ?? [])->mapWithKeys(fn ($v, $k) => ["attr.{$k}" => $v])->all());
    }
}
```

---

### Step 5 – Resursă Filament `WorkCompletionResource`

```bash
docker compose exec app php artisan make:filament-resource WorkCompletion --generate
```

**Navigation properties**:
```php
protected static ?string $navigationGroup  = 'Contracte';
protected static ?string $navigationLabel  = 'Procese Verbale';
protected static ?string $modelLabel       = 'Proces Verbal';
protected static ?string $pluralModelLabel = 'Procese Verbale';
protected static ?string $navigationIcon   = 'heroicon-o-clipboard-document-check';
```

**Vizibilitate condiționată pe modul**:
```php
public static function canAccess(): bool
{
    $company = \App\Models\Company::find(session('active_company_id'));
    return $company?->hasModule('procese_verbale') ?? false;
}
```

**Form schema** (tabs: Date PV / Conținut / Atribute lucrare):
```php
Tabs::make()->tabs([
    Tabs\Tab::make('Date PV')->schema([
        Select::make('contract_id')
            ->label('Contract')
            ->relationship('contract', 'number')
            ->searchable()->preload()->required(),
        Select::make('document_template_id')
            ->label('Șablon PV')
            ->relationship('documentTemplate', 'name')
            ->searchable()->preload()->nullable(),
        DatePicker::make('work_date')
            ->label('Data lucrării')
            ->required()
            ->displayFormat('d.m.Y')
            ->default(now()),
        DatePicker::make('signed_date')
            ->label('Data semnării')
            ->displayFormat('d.m.Y')
            ->nullable(),
        Select::make('status')
            ->label('Status')
            ->options(WorkCompletionStatus::class)
            ->default('draft'),
        Textarea::make('notes')->label('Observații')->rows(2),
    ]),
    Tabs\Tab::make('Conținut')->schema([
        RichEditor::make('body')
            ->label('Conținut PV')
            ->columnSpanFull()
            ->toolbarButtons([
                'bold', 'italic', 'underline', 'strike',
                'link', 'orderedList', 'bulletList',
                'h2', 'h3', 'redo', 'undo',
            ]),
    ]),
    Tabs\Tab::make('Atribute lucrare')->schema([
        KeyValue::make('attributes')
            ->label('Atribute specifice lucrării')
            ->helperText('Ex: suprafata_tratata, tip_solutie, cantitate_solutie etc.')
            ->nullable(),
    ]),
])->columnSpanFull()
```

**Table columns**:
```php
->columns([
    TextColumn::make('full_number')->label('Nr. PV')->searchable()->sortable(),
    TextColumn::make('contract.number')->label('Contract')->searchable(),
    TextColumn::make('contract.client.name')->label('Client')->searchable(),
    TextColumn::make('work_date')->label('Data lucrării')->date('d.m.Y')->sortable(),
    TextColumn::make('status')->label('Status')->badge()
        ->formatStateUsing(fn ($state) => $state instanceof WorkCompletionStatus ? $state->label() : $state)
        ->color(fn ($state) => $state instanceof WorkCompletionStatus ? $state->color() : 'gray'),
])
->defaultSort('work_date', 'desc')
```

**Actions**:
```php
->actions([
    Tables\Actions\ViewAction::make()->label('Vezi'),
    Tables\Actions\EditAction::make()->label('Editează')
        ->visible(fn (WorkCompletion $record) => $record->status === WorkCompletionStatus::Draft),
    Action::make('semneaza')
        ->label('Marchează ca semnat')
        ->icon('heroicon-o-check-badge')
        ->requiresConfirmation()
        ->visible(fn (WorkCompletion $record) => $record->status === WorkCompletionStatus::Draft)
        ->action(function (WorkCompletion $record) {
            app(\App\Services\WorkCompletionService::class)->sign($record);
            Notification::make()->title('PV marcat ca semnat')->success()->send();
        }),
    Action::make('descarca_pdf')
        ->label('Descarcă PDF')
        ->icon('heroicon-o-arrow-down-tray')
        ->visible(fn (WorkCompletion $record) => ! empty($record->pdf_path))
        ->url(fn (WorkCompletion $record) => route('work-completions.pdf', $record))
        ->openUrlInNewTab(),
    Tables\Actions\DeleteAction::make()->label('Șterge'),
])
```

---

### Step 6 – Relation Manager în `ContractResource`

```bash
docker compose exec app php artisan make:filament-relation-manager ContractResource workCompletions full_number
```

Înregistrează în `ContractResource::getRelations()`:
```php
\App\Filament\Resources\ContractResource\RelationManagers\WorkCompletionsRelationManager::class,
```

---

### Step 7 – PDF template și PdfService

**`resources/views/pdf/work-completion.blade.php`**:
```blade
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 40px; }
        h1 { font-size: 14px; text-align: center; text-transform: uppercase; }
        .meta { margin-bottom: 20px; font-size: 11px; color: #555; }
        .content { margin-top: 20px; }
        .signatures { margin-top: 60px; }
        .signatures table { width: 100%; }
        .signatures td { width: 50%; vertical-align: top; }
    </style>
</head>
<body>
    @if($pv->contract->company->logo)
        <img src="{{ storage_path('app/public/' . $pv->contract->company->logo) }}" height="50" style="margin-bottom: 10px;">
    @endif

    <h1>PROCES VERBAL DE LUCRĂRI</h1>
    <p class="meta" style="text-align:center;">
        Nr. <strong>{{ $pv->full_number }}</strong> &nbsp;|&nbsp;
        Data lucrării: <strong>{{ $pv->work_date->format('d.m.Y') }}</strong>
    </p>

    <p>
        <strong>Prestator:</strong> {{ $pv->contract->company->name }},
        CIF: {{ $pv->contract->company->cif }}<br>
        <strong>Beneficiar:</strong> {{ $pv->contract->client->name }},
        CIF: {{ $pv->contract->client->cif ?? '—' }}<br>
        <strong>Contract:</strong> nr. {{ $pv->contract->number }} din {{ $pv->contract->signed_date?->format('d.m.Y') ?? $pv->contract->start_date->format('d.m.Y') }}
    </p>

    <div class="content">
        {!! $pv->content_snapshot ?? $pv->body !!}
    </div>

    <div class="signatures">
        <table>
            <tr>
                <td><strong>Prestator,</strong><br><br>Semnătură: _______________</td>
                <td><strong>Beneficiar,</strong><br><br>Semnătură: _______________</td>
            </tr>
        </table>
    </div>
</body>
</html>
```

Adaugă în `PdfService`:
```php
public function generateWorkCompletion(WorkCompletion $pv): string
{
    $pv->loadMissing(['contract.company', 'contract.client']);
    $path = storage_path("app/pv/{$pv->company_id}/{$pv->fiscal_year}");
    if (! is_dir($path)) { mkdir($path, 0755, true); }
    $filePath = "{$path}/{$pv->full_number}.pdf";
    Pdf::loadView('pdf.work-completion', ['pv' => $pv])->setPaper('a4')->save($filePath);
    $pv->withoutGlobalScopes()->where('id', $pv->id)->update(['pdf_path' => $filePath]);
    return $filePath;
}
```

Adaugă în `routes/web.php`:
```php
Route::get('/work-completions/{pv}/pdf', function (\App\Models\WorkCompletion $pv) {
    $path = app(\App\Services\PdfService::class)->generateWorkCompletion($pv);
    return response()->download($path, "pv-{$pv->full_number}.pdf");
})->name('work-completions.pdf')->middleware('auth');
```

---

### Step 8 – Acțiune contextuală din `ContractResource`

În `ContractResource`, adaugă acțiune în tabel:
```php
Action::make('adauga_pv')
    ->label('Adaugă Proces Verbal')
    ->icon('heroicon-o-clipboard-document-plus')
    ->visible(fn () => \App\Models\Company::find(session('active_company_id'))?->hasModule('procese_verbale'))
    ->url(fn (Contract $record) => \App\Filament\Resources\WorkCompletionResource::getUrl('create') . '?contract_id=' . $record->id),
```

---

## Acceptance Criteria
- [ ] `work_completions` tabel creat prin migrare.
- [ ] Numerotarea PV-urilor este secvențială per firmă și se resetează anual (PV-1/2026, PV-2/2026...).
- [ ] La creare, dacă e selectat un template, corpul PV-ului este pre-populat cu placeholderele rezolvate.
- [ ] La semnare, `content_snapshot` este înghețat.
- [ ] PDF-ul PV-ului poate fi generat și descărcat.
- [ ] Relation Manager vizibil în pagina de vizualizare a contractului.
- [ ] Modulul `procese_verbale` controlează vizibilitatea resursei.
- [ ] Toate etichetele și notificările sunt în **română**.

---

## Development Log

| Date | Implemented | Pending | Blockers / Notes |
|---|---|---|---|
| — | — | Everything | Not started |
