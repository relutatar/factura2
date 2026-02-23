<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyTypeResource\Pages;
use App\Models\CompanyType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CompanyTypeResource extends Resource
{
    protected static ?string $model = CompanyType::class;

    protected static ?string $navigationGroup = 'Configurare';
    protected static ?string $navigationLabel = 'Tipuri Companie';
    protected static ?string $modelLabel = 'Tip Companie';
    protected static ?string $pluralModelLabel = 'Tipuri Companie';
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 10;

    // ─── Form ───────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Denumire')
                ->required()
                ->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, Set $set) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug($state));
                    }
                })
                ->columnSpan(1),

            TextInput::make('slug')
                ->label('Slug (identificator unic)')
                ->required()
                ->unique(CompanyType::class, 'slug', ignoreRecord: true)
                ->maxLength(50)
                ->helperText('Generat automat din denumire. Nu modificați dacă există companii asociate.')
                ->columnSpan(1),

            Textarea::make('description')
                ->label('Descriere')
                ->rows(3)
                ->columnSpanFull(),

            Select::make('color')
                ->label('Culoare badge')
                ->options([
                    'gray'    => 'Gri',
                    'success' => 'Verde (Success)',
                    'warning' => 'Portocaliu (Warning)',
                    'danger'  => 'Roșu (Danger)',
                    'info'    => 'Albastru (Info)',
                    'primary' => 'Primar',
                ])
                ->default('gray')
                ->required()
                ->columnSpan(1),

            TextInput::make('sort_order')
                ->label('Ordine afișare')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->columnSpan(1),

            Toggle::make('is_active')
                ->label('Activ')
                ->default(true)
                ->columnSpanFull(),
        ])->columns(2);
    }

    // ─── Table ──────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Denumire')
                    ->badge()
                    ->color(fn (CompanyType $record) => $record->color)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->copyable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descriere')
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('companies_count')
                    ->label('Companii')
                    ->counts('companies')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make()
                    ->label('Editează'),
                DeleteAction::make()
                    ->label('Șterge')
                    ->before(function (CompanyType $record, DeleteAction $action) {
                        if ($record->companies()->count() > 0) {
                            Notification::make()
                                ->title('Nu se poate șterge')
                                ->body('Există companii asociate acestui tip.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->reorderable('sort_order');
    }

    // ─── Pages ─────────────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompanyTypes::route('/'),
            'create' => Pages\CreateCompanyType::route('/create'),
            'edit'   => Pages\EditCompanyType::route('/{record}/edit'),
        ];
    }
}
