<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\VatRate;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationGroup  = 'Produse & Stocuri';
    protected static ?string $navigationLabel  = 'Produse';
    protected static ?string $modelLabel       = 'Produs';
    protected static ?string $pluralModelLabel = 'Produse';
    protected static ?string $navigationIcon   = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')
                ->label('Cod produs')
                ->required()
                ->maxLength(50),

            TextInput::make('name')
                ->label('Denumire')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label('Descriere')
                ->rows(2)
                ->columnSpanFull(),

            Select::make('unit')
                ->label('UM')
                ->options([
                    'bucată' => 'Bucată',
                    'litru'  => 'Litru',
                    'kg'     => 'Kg',
                    'cutie'  => 'Cutie',
                    'set'    => 'Set',
                    'doză'   => 'Doză',
                ])
                ->default('bucată')
                ->required(),

            TextInput::make('unit_price')
                ->label('Preț unitar')
                ->numeric()
                ->suffix('RON')
                ->required(),

            Select::make('vat_rate_id')
                ->label('Cotă TVA')
                ->options(fn () => VatRate::selectOptions())
                ->default(fn () => optional(VatRate::defaultRate())->id)
                ->required()
                ->preload(),

            TextInput::make('stock_quantity')
                ->label('Stoc curent')
                ->numeric()
                ->default(0),

            TextInput::make('stock_minimum')
                ->label('Stoc minim')
                ->numeric()
                ->default(0),

            TextInput::make('category')
                ->label('Categorie')
                ->maxLength(100),

            Toggle::make('is_active')
                ->label('Activ')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Cod')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Denumire')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Categorie')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('stock_quantity')
                    ->label('Stoc')
                    ->formatStateUsing(fn ($state, Product $record) => "{$state} {$record->unit}")
                    ->color(fn (Product $record) => $record->isLowStock() ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('stock_minimum')
                    ->label('Stoc minim')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('unit_price')
                    ->label('Preț')
                    ->money('RON')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean(),
            ])
            ->recordClasses(fn (Product $record) => $record->isLowStock() ? 'bg-red-50 dark:bg-red-950' : null)
            ->defaultSort('name')
            ->actions([
                Action::make('intrare_stoc')
                    ->label('Înregistrează intrare')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        TextInput::make('quantity')
                            ->label('Cantitate')
                            ->numeric()
                            ->required()
                            ->minValue(0.001),
                        TextInput::make('unit_price')
                            ->label('Preț achiziție')
                            ->numeric()
                            ->suffix('RON'),
                        TextInput::make('notes')
                            ->label('Observații'),
                    ])
                    ->action(function (Product $record, array $data) {
                        app(\App\Services\StockService::class)->recordEntry(
                            $record,
                            (float) $data['quantity'],
                            (float) ($data['unit_price'] ?? 0),
                            $data['notes'] ?? null
                        );
                        Notification::make()->title('Intrare stoc înregistrată')->success()->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
