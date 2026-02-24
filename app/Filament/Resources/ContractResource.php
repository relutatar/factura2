<?php

namespace App\Filament\Resources;

use App\Enums\ContractStatus;
use App\Filament\Resources\ContractResource\Pages;
use App\Filament\Resources\InvoiceResource;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Services\InvoiceService;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationGroup  = 'Contracte';
    protected static ?string $navigationLabel  = 'Contracte';
    protected static ?string $modelLabel       = 'Contract';
    protected static ?string $pluralModelLabel = 'Contracte';
    protected static ?string $navigationIcon   = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make()->tabs([
                Tabs\Tab::make('General')->schema([
                    TextInput::make('number')
                        ->label('Număr contract')
                        ->default(fn (): string => self::suggestNextContractNumber())
                        ->required(),
                    Select::make('client_id')
                        ->label('Client')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->default(fn (): ?int => request()->integer('client_id') ?: null)
                        ->required(),
                    Select::make('contract_template_id')
                        ->label('Tip contract (Șablon)')
                        ->relationship(
                            name: 'template',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('is_default', 'desc')->orderBy('name')
                        )
                        ->preload()
                        ->searchable()
                        ->live()
                        ->default(fn () => ContractTemplate::defaultTemplate()?->id)
                        ->required()
                        ->helperText('Template-ul va fi folosit la generarea PDF-ului de contract.'),
                    DatePicker::make('signed_date')
                        ->label('Data contract (din)')
                        ->required()
                        ->default(today())
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('start_date')
                        ->label('Data început')
                        ->required()
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('end_date')
                        ->label('Data sfârșit')
                        ->displayFormat('d.m.Y'),
                    TextInput::make('value')
                        ->label('Valoare')
                        ->numeric()
                        ->suffix('RON'),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'activ'     => 'Activ',
                            'suspendat' => 'Suspendat',
                            'expirat'   => 'Expirat',
                            'reziliat'  => 'Reziliat',
                        ])
                        ->default('activ'),
                    Textarea::make('notes')
                        ->label('Observații')
                        ->rows(2),
                ]),

                Tabs\Tab::make('Atribute suplimentare')
                    ->schema(fn (Get $get): array => self::additionalAttributesSchema($get('contract_template_id'))),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    private static function additionalAttributesSchema(mixed $templateId): array
    {
        $templateId = is_numeric($templateId) ? (int) $templateId : null;

        if (! $templateId) {
            return [
                Placeholder::make('additional_attributes_hint_select_template')
                    ->label('Atribute personalizate')
                    ->content('Selectați mai întâi un șablon contract pentru a configura atributele suplimentare.'),
            ];
        }

        $template = ContractTemplate::query()
            ->select(['id', 'custom_fields'])
            ->find($templateId);

        $customFields = collect($template?->custom_fields ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->values();

        if ($customFields->isEmpty()) {
            return [
                Placeholder::make('additional_attributes_hint_empty_template')
                    ->label('Atribute personalizate')
                    ->content('Acest șablon nu are atribute suplimentare definite.'),
            ];
        }

        $components = $customFields
            ->map(fn (array $field): ?Component => self::buildAdditionalAttributeField($field))
            ->filter()
            ->values()
            ->all();

        return $components !== [] ? $components : [
            Placeholder::make('additional_attributes_hint_invalid_template')
                ->label('Atribute personalizate')
                ->content('Atributele definite pe șablon nu au o configurație validă.'),
        ];
    }

    private static function buildAdditionalAttributeField(array $field): ?Component
    {
        $key = (string) Str::of((string) ($field['key'] ?? ''))
            ->trim()
            ->replace('-', '_')
            ->replace(' ', '_')
            ->replaceMatches('/[^a-zA-Z0-9_]/', '');

        if ($key === '') {
            return null;
        }

        $statePath = 'additional_attributes.' . $key;
        $label = filled($field['label'] ?? null)
            ? (string) $field['label']
            : (string) Str::of($key)->replace('_', ' ')->title();
        $required = (bool) ($field['required'] ?? false);
        $fieldType = (string) ($field['field_type'] ?? 'text');

        return match ($fieldType) {
            'textarea' => Textarea::make($statePath)
                ->label($label)
                ->required($required)
                ->rows(3)
                ->columnSpanFull(),

            'number' => TextInput::make($statePath)
                ->label($label)
                ->required($required)
                ->numeric(),

            'date' => DatePicker::make($statePath)
                ->label($label)
                ->required($required)
                ->displayFormat('d.m.Y'),

            'select' => Select::make($statePath)
                ->label($label)
                ->required($required)
                ->options(self::normalizeSelectOptions($field['options'] ?? []))
                ->searchable()
                ->native(false),

            'toggle' => Toggle::make($statePath)
                ->label($label)
                ->required($required),

            default => TextInput::make($statePath)
                ->label($label)
                ->required($required),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeSelectOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        if (self::isAssociative($options)) {
            return collect($options)
                ->filter(fn (mixed $label, mixed $value): bool => filled($value) && filled($label))
                ->mapWithKeys(fn (mixed $label, mixed $value): array => [(string) $value => (string) $label])
                ->all();
        }

        return collect($options)
            ->filter(fn (mixed $value): bool => filled($value))
            ->mapWithKeys(fn (mixed $value): array => [(string) $value => (string) $value])
            ->all();
    }

    private static function isAssociative(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private static function suggestNextContractNumber(): string
    {
        $lastNumber = (string) Contract::query()
            ->latest('id')
            ->value('number');

        if ($lastNumber === '') {
            return '1';
        }

        if (preg_match('/^(.*?)(\d+)$/', $lastNumber, $matches) !== 1) {
            return '1';
        }

        $prefix = $matches[1];
        $number = $matches[2];
        $nextNumber = (string) (((int) $number) + 1);

        return $prefix . str_pad($nextNumber, strlen($number), '0', STR_PAD_LEFT);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Nr. contract')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('template.name')
                    ->label('Tip contract')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state) => $state instanceof ContractStatus ? $state->label() : $state)
                    ->color(fn (mixed $state) => $state instanceof ContractStatus ? $state->color() : 'gray'),
                TextColumn::make('start_date')
                    ->label('Data început')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('end_date')
                    ->label('Data sfârșit')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (mixed $state) => $state && $state->isPast()
                        ? 'danger'
                        : ($state && $state->diffInDays(now()) <= 30 ? 'warning' : null)
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('value')
                    ->label('Valoare')
                    ->money('RON')
                    ->sortable(),
            ])
            ->recordClasses(fn (Contract $record) =>
                $record->end_date
                    && $record->end_date->diffInDays(now(), false) >= 0
                    && $record->end_date->diffInDays(now(), false) <= 30
                    ? 'bg-yellow-50 dark:bg-yellow-950'
                    : null
            )
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Vezi'),
                Tables\Actions\EditAction::make()->label('Editează'),
                Action::make('genereaza_factura')
                    ->label('Generează Factură')
                    ->icon('heroicon-o-document-plus')
                    ->requiresConfirmation()
                    ->modalHeading('Generează factură din contract')
                    ->modalDescription('Se va crea o factură draft pe baza acestui contract.')
                    ->modalSubmitActionLabel('Generează')
                    ->action(function (Contract $record) {
                        $invoice = app(InvoiceService::class)->createFromContract($record);
                        Notification::make()->title('Factură creată cu succes')->success()->send();
                        return redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                    }),
                Action::make('descarca_pdf_contract')
                    ->label('Descarcă PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Contract $record) => route('contracts.pdf', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()->label('Șterge'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit'   => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
