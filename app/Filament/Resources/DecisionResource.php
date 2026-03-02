<?php

namespace App\Filament\Resources;

use App\Enums\DecisionStatus;
use App\Filament\Resources\DecisionResource\Pages;
use App\Models\Company;
use App\Models\Decision;
use App\Models\DecisionTemplate;
use App\Services\DecisionService;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DecisionResource extends Resource
{
    protected static ?string $model = Decision::class;

    protected static ?string $navigationGroup = 'Decizii Administrative';
    protected static ?string $navigationLabel = 'Decizii';
    protected static ?string $modelLabel = 'Decizie';
    protected static ?string $pluralModelLabel = 'Decizii';
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Decizie')->tabs([
                Tabs\Tab::make('General')->schema([
                    Select::make('decision_template_id')
                        ->label('Tip decizie (șablon)')
                        ->options(fn (): array => DecisionTemplate::query()
                            ->where('is_active', true)
                            ->where(function ($query): void {
                                $query->whereNull('company_id')
                                    ->orWhere('company_id', session('active_company_id'));
                            })
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if (! $state) {
                                return;
                            }

                            $title = app(DecisionService::class)->preloadTitleFromTemplate((int) $state);
                            if ($title) {
                                $set('title', $title);
                            }
                        }),

                    TextInput::make('title')
                        ->label('Titlu')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('legal_representative_name')
                        ->label('Reprezentant legal')
                        ->required()
                        ->default(function (): string {
                            $company = Company::withoutGlobalScopes()->find(session('active_company_id'));

                            return (string) ($company?->administrator ?? '');
                        }),

                    Textarea::make('notes')
                        ->label('Observații')
                        ->rows(3)
                        ->columnSpanFull(),

                    Placeholder::make('decision_number_placeholder')
                        ->label('Număr decizie')
                        ->content(fn (?Decision $record): string => $record?->number ? (string) $record->number : 'Se alocă la emitere'),

                    DatePicker::make('decision_date')
                        ->label('Data deciziei')
                        ->default(today())
                        ->required()
                        ->disabled(fn (?Decision $record): bool => $record?->status === DecisionStatus::Issued)
                        ->native(false)
                        ->suffixIcon('heroicon-m-calendar')
                        ->displayFormat('d.m.Y')
                        ->dehydrated(),

                    Select::make('status')
                        ->label('Status')
                        ->options(collect(DecisionStatus::cases())->mapWithKeys(
                            fn (DecisionStatus $status): array => [$status->value => $status->label()]
                        ))
                        ->default(DecisionStatus::Draft->value)
                        ->disabled()
                        ->dehydrated(),
                ])->columns(2),

                Tabs\Tab::make('Atribute personalizate')
                    ->schema(fn (Get $get): array => self::dynamicAttributesSchema($get('decision_template_id'))),
            ])->columnSpanFull(),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    private static function dynamicAttributesSchema(mixed $templateId): array
    {
        $templateId = is_numeric($templateId) ? (int) $templateId : null;

        if (! $templateId) {
            return [
                Placeholder::make('decision_custom_attrs_hint')
                    ->label('Atribute personalizate')
                    ->content('Selectați mai întâi un șablon de decizie pentru a afișa câmpurile dinamice.'),
            ];
        }

        $template = DecisionTemplate::query()->select(['id', 'custom_fields_schema'])->find($templateId);
        $schema = is_array($template?->custom_fields_schema) ? $template->custom_fields_schema : [];

        if ($schema === []) {
            return [
                Placeholder::make('decision_custom_attrs_empty')
                    ->label('Atribute personalizate')
                    ->content('Acest șablon nu are câmpuri personalizate definite.'),
            ];
        }

        $components = collect($schema)
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->map(fn (array $field): ?Component => self::buildDynamicFieldComponent($field))
            ->filter()
            ->values()
            ->all();

        return $components !== [] ? $components : [
            Placeholder::make('decision_custom_attrs_invalid')
                ->label('Atribute personalizate')
                ->content('Schema câmpurilor personalizate nu este validă.'),
        ];
    }

    private static function buildDynamicFieldComponent(array $field): ?Component
    {
        $key = (string) Str::of((string) ($field['key'] ?? ''))
            ->trim()
            ->replace('-', '_')
            ->replace(' ', '_')
            ->replaceMatches('/[^a-zA-Z0-9_]/', '');

        if ($key === '') {
            return null;
        }

        $statePath = 'custom_attributes.' . $key;
        $label = (string) ($field['label'] ?? $key);
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
                ->numeric()
                ->required($required),

            'date' => DatePicker::make($statePath)
                ->label($label)
                ->required($required)
                ->native(false)
                        ->suffixIcon('heroicon-m-calendar')
                        ->displayFormat('d.m.Y'),

            'select' => Select::make($statePath)
                ->label($label)
                ->required($required)
                ->options(self::normalizeSelectOptions($field['options'] ?? []))
                ->searchable(),

            'toggle' => Toggle::make($statePath)
                ->label($label)
                ->required($required),

            'array_strings' => TagsInput::make($statePath)
                ->label($label)
                ->required($required)
                ->columnSpanFull(),

            'assets' => Repeater::make($statePath)
                ->label($label)
                ->required($required)
                ->schema([
                    TextInput::make('name')->label('Denumire activ')->required(),
                    TextInput::make('serial_number')->label('Serie (opțional)'),
                    TextInput::make('reason')->label('Motiv')->required(),
                ])
                ->columns(3)
                ->columnSpanFull(),

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

        if (array_keys($options) !== range(0, count($options) - 1)) {
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Nr.')
                    ->formatStateUsing(fn ($state) => $state ?: '—')
                    ->sortable(),

                TextColumn::make('template.name')
                    ->label('Tip')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof DecisionStatus ? $state->label() : (string) $state)
                    ->color(fn (mixed $state): string => $state instanceof DecisionStatus ? $state->color() : 'gray'),

                TextColumn::make('decision_date')
                    ->label('Data')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label('Vezi'),
                Tables\Actions\EditAction::make()
                    ->label('Editează')
                    ->visible(fn (Decision $record): bool => $record->status !== DecisionStatus::Issued),

                Action::make('issue')
                    ->label('Emite')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Decision $record): bool => $record->status === DecisionStatus::Draft)
                    ->action(function (Decision $record): void {
                        try {
                            app(DecisionService::class)->issueDecision($record);

                            Notification::make()
                                ->title('Decizie emisă cu succes')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Emiterea a eșuat')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('archive')
                    ->label('Arhivează')
                    ->icon('heroicon-o-archive-box')
                    ->visible(fn (Decision $record): bool => $record->status === DecisionStatus::Issued)
                    ->action(fn (Decision $record): bool => $record->update(['status' => DecisionStatus::Archived])),

                Action::make('cancel')
                    ->label('Anulează')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Decision $record): bool => $record->status !== DecisionStatus::Cancelled)
                    ->action(fn (Decision $record): bool => $record->update(['status' => DecisionStatus::Cancelled])),

                Action::make('download_pdf')
                    ->label('Descarcă PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Decision $record): bool => $record->status === DecisionStatus::Issued)
                    ->url(fn (Decision $record): string => route('decisions.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if ($user?->hasRole('superadmin')) {
            return parent::getEloquentQuery()->withoutGlobalScopes();
        }

        $accessibleIds = $user?->companies()->pluck('companies.id')->all() ?? [];

        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->whereIn('company_id', $accessibleIds);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDecisions::route('/'),
            'create' => Pages\CreateDecision::route('/create'),
            'edit' => Pages\EditDecision::route('/{record}/edit'),
        ];
    }
}
