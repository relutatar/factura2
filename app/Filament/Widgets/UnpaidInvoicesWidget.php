<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UnpaidInvoicesWidget extends BaseWidget
{
    protected static ?int $sort        = 2;
    protected int|string|array $columnSpan = 2;
    protected static ?string $heading  = 'Facturi neîncasate';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where('status', InvoiceStatus::Trimisa)
                    ->latest('due_date')
            )
            ->columns([
                TextColumn::make('full_number')
                    ->label('Număr')
                    ->searchable(),

                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable(),

                TextColumn::make('issue_date')
                    ->label('Data emitere')
                    ->date('d.m.Y'),

                TextColumn::make('due_date')
                    ->label('Scadență')
                    ->date('d.m.Y')
                    ->color(fn (Invoice $record) => $record->due_date?->isPast() ? 'danger' : 'warning'),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('RON'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Deschide')
                    ->url(fn (Invoice $record) => \App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-o-arrow-top-right-on-square'),
            ])
            ->paginated(false)
            ->defaultSort('due_date', 'asc');
    }
}
