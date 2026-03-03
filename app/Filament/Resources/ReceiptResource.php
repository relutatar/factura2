<?php

namespace App\Filament\Resources;

use App\Enums\ReceiptStatus;
use App\Filament\Resources\ReceiptResource\Pages;
use App\Models\Receipt;
use App\Services\ReceiptService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;

    protected static ?string $navigationGroup = 'Facturi';
    protected static ?string $navigationLabel = 'Chitanțe';
    protected static ?string $modelLabel = 'Chitanță';
    protected static ?string $pluralModelLabel = 'Chitanțe';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('full_number')->label('Număr')->disabled(),
            TextInput::make('invoice.full_number')->label('Factură')->disabled(),
            DatePicker::make('issue_date')->label('Data emiterii')->displayFormat('d.m.Y')->disabled(),
            TextInput::make('amount')->label('Sumă')->disabled(),
            TextInput::make('currency')->label('Monedă')->disabled(),
            TextInput::make('status')->label('Status')->disabled(),
            TextInput::make('received_by')->label('Încasat de')->disabled(),
            Textarea::make('notes')->label('Observații')->disabled()->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_number')
                    ->label('Număr')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice.full_number')
                    ->label('Factură aferentă')
                    ->searchable(),

                TextColumn::make('invoice.client.name')
                    ->label('Client')
                    ->searchable(),

                TextColumn::make('issue_date')
                    ->label('Data emiterii')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Sumă')
                    ->money('RON'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ReceiptStatus ? $state->label() : (string) $state)
                    ->color(fn (mixed $state): string => $state instanceof ReceiptStatus ? $state->color() : 'gray'),
            ])
            ->defaultSort('issue_date', 'desc')
            ->actions([
                ViewAction::make()->label('Vezi'),

                Action::make('descarca_pdf')
                    ->label('Descarcă PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Receipt $record): bool => ! empty($record->pdf_path) && file_exists($record->pdf_path))
                    ->url(fn (Receipt $record): string => route('receipts.pdf', $record))
                    ->openUrlInNewTab(),

                Action::make('anuleaza')
                    ->label('Anulează')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Receipt $record): bool => $record->status === ReceiptStatus::Emisa)
                    ->action(function (Receipt $record): void {
                        try {
                            app(ReceiptService::class)->cancel($record);

                            Notification::make()
                                ->title('Chitanță anulată')
                                ->warning()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Eroare la anulare')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('full_number')->label('Număr'),
            TextEntry::make('invoice.full_number')->label('Factură aferentă'),
            TextEntry::make('invoice.client.name')->label('Client'),
            TextEntry::make('issue_date')->label('Data emiterii')->date('d.m.Y'),
            TextEntry::make('amount')->label('Sumă')->money('RON'),
            TextEntry::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (mixed $state): string => $state instanceof ReceiptStatus ? $state->label() : (string) $state)
                ->color(fn (mixed $state): string => $state instanceof ReceiptStatus ? $state->color() : 'gray'),
            TextEntry::make('received_by')->label('Încasat de')->placeholder('—'),
            TextEntry::make('notes')->label('Observații')->placeholder('—')->columnSpanFull(),
        ])->columns(2);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceipts::route('/'),
            'view' => Pages\ViewReceipt::route('/{record}'),
        ];
    }
}
