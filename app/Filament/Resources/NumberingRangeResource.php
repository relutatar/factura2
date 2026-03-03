<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NumberingRangeResource\Pages;
use App\Models\Decision;
use App\Models\Invoice;
use App\Models\NumberingRange;
use App\Models\Proforma;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Get;

class NumberingRangeResource extends Resource
{
    protected static ?string $model = NumberingRange::class;

    protected static ?string $navigationGroup = 'Configurare';
    protected static ?string $navigationLabel = 'Plaje numerotare';
    protected static ?string $modelLabel = 'Plajă numerotare';
    protected static ?string $pluralModelLabel = 'Plaje numerotare';
    protected static ?string $navigationIcon = 'heroicon-o-hashtag';
    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('decision_id')
                ->label('Decizie administrativă')
                ->options(fn (): array => Decision::query()
                    ->orderByDesc('decision_date')
                    ->get()
                    ->mapWithKeys(fn (Decision $decision): array => [
                        $decision->id => ($decision->number ? 'Decizia ' . $decision->number : 'Decizie draft')
                            . ' / ' . ($decision->decision_date?->format('d.m.Y') ?? 'fără dată'),
                    ])
                    ->all()
                )
                ->searchable()
                ->preload()
                ->nullable(),

            Select::make('document_type')
                ->label('Tip document')
                ->options([
                    'factura' => 'Factură',
                    'chitanta' => 'Chitanță',
                    'aviz' => 'Aviz',
                    'proforma' => 'Proformă',
                ])
                ->required()
                ->native(false),

            TextInput::make('fiscal_year')
                ->label('An fiscal')
                ->numeric()
                ->default((int) now()->format('Y'))
                ->minValue(2000)
                ->maxValue(2100)
                ->required(),

            TextInput::make('series')
                ->label('Serie')
                ->required()
                ->maxLength(20)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) => $set('series', strtoupper(trim((string) $state))))
                ->rule(function (?NumberingRange $record, Get $get) {
                    return function (string $attribute, mixed $value, \Closure $fail) use ($record, $get): void {
                        $scopeQuery = NumberingRange::withoutGlobalScopes()
                            ->where('company_id', (int) session('active_company_id'))
                            ->where('document_type', (string) $get('document_type'))
                            ->where('fiscal_year', (int) $get('fiscal_year'))
                            ->where('series', strtoupper(trim((string) $value)));

                        $workPointCode = trim((string) ($get('work_point_code') ?? ''));

                        if ($workPointCode === '') {
                            $scopeQuery->whereNull('work_point_code');
                        } else {
                            $scopeQuery->where('work_point_code', $workPointCode);
                        }

                        if ($record) {
                            $scopeQuery->whereKeyNot($record->getKey());
                        }

                        if ($scopeQuery->exists()) {
                            $fail('Există deja o plajă pentru această combinație: tip document, serie, an fiscal și punct de lucru.');
                        }
                    };
                }),

            TextInput::make('work_point_code')
                ->label('Cod punct de lucru (opțional)')
                ->maxLength(20)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set) => $set('work_point_code', trim((string) $state) ?: null)),

            TextInput::make('start_number')
                ->label('Număr început')
                ->numeric()
                ->required()
                ->minValue(1)
                ->live(),

            TextInput::make('end_number')
                ->label('Număr final')
                ->numeric()
                ->required()
                ->minValue(1)
                ->rule(function (Get $get) {
                    return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        $startNumber = (int) ($get('start_number') ?: 0);
                        $endNumber = (int) ($value ?: 0);
                        $nextNumber = (int) ($get('next_number') ?: 0);

                        if ($endNumber < $startNumber) {
                            $fail('Numărul final trebuie să fie mai mare sau egal cu numărul de început.');
                        }

                        if ($nextNumber > ($endNumber + 1)) {
                            $fail('Numărul final nu poate fi sub next_number - 1.');
                        }
                    };
                })
                ->live(),

            TextInput::make('next_number')
                ->label('Următorul număr')
                ->numeric()
                ->required()
                ->minValue(1)
                ->default(fn (Get $get): int => (int) ($get('start_number') ?: 1))
                ->rule(function (Get $get) {
                    return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        $startNumber = (int) ($get('start_number') ?: 0);
                        $endNumber = (int) ($get('end_number') ?: 0);
                        $nextNumber = (int) ($value ?: 0);

                        if ($nextNumber < $startNumber || $nextNumber > ($endNumber + 1)) {
                            $fail('Următorul număr trebuie să fie în intervalul [start_number, end_number + 1].');
                        }
                    };
                }),

            Toggle::make('is_active')
                ->label('Activă')
                ->default(true)
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_type')
                    ->label('Tip document')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'factura' => 'Factură',
                        'chitanta' => 'Chitanță',
                        'aviz' => 'Aviz',
                        'proforma' => 'Proformă',
                        default => $state,
                    })
                    ->color('info')
                    ->sortable(),

                TextColumn::make('fiscal_year')
                    ->label('An')
                    ->sortable(),

                TextColumn::make('series')
                    ->label('Serie')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('work_point_code')
                    ->label('Punct lucru')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : '—')
                    ->toggleable(),

                TextColumn::make('range')
                    ->label('Interval')
                    ->getStateUsing(fn (NumberingRange $record): string => $record->start_number . ' - ' . $record->end_number),

                TextColumn::make('next_number')
                    ->label('Următorul')
                    ->sortable(),

                TextColumn::make('status_virtual')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (NumberingRange $record): string {
                        if (! $record->is_active) {
                            return 'Inactivă';
                        }

                        if ((int) $record->next_number > (int) $record->end_number) {
                            return 'Epuizată';
                        }

                        return 'Activă';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Activă' => 'success',
                        'Epuizată' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make()->label('Editează'),
                DeleteAction::make()
                    ->label('Șterge')
                    ->before(function (NumberingRange $record, DeleteAction $action): void {
                        if (self::isRangeUsed($record)) {
                            Notification::make()
                                ->title('Plaja nu poate fi ștearsă')
                                ->body('Există documente emise care folosesc această plajă.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNumberingRanges::route('/'),
            'create' => Pages\CreateNumberingRange::route('/create'),
            'edit' => Pages\EditNumberingRange::route('/{record}/edit'),
        ];
    }

    private static function isRangeUsed(NumberingRange $range): bool
    {
        $baseFilter = fn ($query) => $query
            ->where('company_id', $range->company_id)
            ->where('series', $range->series)
            ->when(
                blank($range->work_point_code),
                fn ($builder) => $builder->whereNull('work_point_code'),
                fn ($builder) => $builder->where('work_point_code', $range->work_point_code)
            )
            ->whereBetween('number', [(int) $range->start_number, (int) $range->end_number]);

        if ($range->document_type === 'factura') {
            return Invoice::withoutGlobalScopes()
                ->where('numbering_range_id', $range->id)
                ->orWhere(function ($query) use ($baseFilter) {
                    $baseFilter($query);
                })
                ->exists();
        }

        if ($range->document_type === 'proforma') {
            return Proforma::withoutGlobalScopes()
                ->where('numbering_range_id', $range->id)
                ->orWhere(function ($query) use ($baseFilter) {
                    $baseFilter($query);
                })
                ->exists();
        }

        return false;
    }
}
