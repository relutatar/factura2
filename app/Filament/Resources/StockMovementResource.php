<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationGroup  = 'Produse & Stocuri';
    protected static ?string $navigationLabel  = 'Mișcări stoc';
    protected static ?string $modelLabel       = 'Mișcare stoc';
    protected static ?string $pluralModelLabel = 'Mișcări stoc';
    protected static ?string $navigationIcon   = 'heroicon-o-arrow-path';

    /** Read-only resource – no form needed. */
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('moved_at')
                    ->label('Data')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('product.name')
                    ->label('Produs')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\StockMovementType ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof \App\Enums\StockMovementType ? $state->color() : 'gray'),

                TextColumn::make('quantity')
                    ->label('Cantitate')
                    ->sortable(),

                TextColumn::make('unit_price')
                    ->label('Preț')
                    ->money('RON')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notes')
                    ->label('Observații')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('invoice.id')
                    ->label('Factură')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tip mișcare')
                    ->options(\App\Enums\StockMovementType::class),
            ])
            ->defaultSort('moved_at', 'desc')
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }
}
