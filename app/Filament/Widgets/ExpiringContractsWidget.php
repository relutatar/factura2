<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ExpiringContractsWidget extends BaseWidget
{
    protected static ?int $sort        = 3;
    protected int|string|array $columnSpan = 1;
    protected static ?string $heading  = 'Contracte care expiră în 30 zile';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Contract::query()
                    ->where('status', ContractStatus::Activ)
                    ->whereBetween('end_date', [now(), now()->addDays(30)])
                    ->orderBy('end_date')
            )
            ->columns([
                TextColumn::make('contract_number')
                    ->label('Nr. contract'),

                TextColumn::make('client.name')
                    ->label('Client'),

                TextColumn::make('end_date')
                    ->label('Expiră la')
                    ->date('d.m.Y')
                    ->color('warning'),
            ])
            ->paginated(false);
    }
}
