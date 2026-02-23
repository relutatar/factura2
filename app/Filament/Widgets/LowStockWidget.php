<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort        = 4;
    protected int|string|array $columnSpan = 1;
    protected static ?string $heading  = 'Stoc redus';

    /**
     * Hide this widget when the active company has no products with stock monitoring.
     */
    public static function canView(): bool
    {
        return Product::query()
            ->where('company_id', session('active_company_id'))
            ->where('stock_minimum', '>', 0)
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('stock_minimum', '>', 0)
                    ->whereRaw('stock_quantity <= stock_minimum')
                    ->orderBy('stock_quantity')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Produs'),

                TextColumn::make('stock_quantity')
                    ->label('Stoc curent')
                    ->color('danger'),

                TextColumn::make('stock_minimum')
                    ->label('Minim'),

                TextColumn::make('unit')
                    ->label('UM'),
            ])
            ->paginated(false);
    }
}
