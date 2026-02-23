<?php

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\VatRate;
use App\Services\InvoiceService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationGroup  = 'Facturi';
    protected static ?string $navigationLabel  = 'Facturi';
    protected static ?string $modelLabel       = 'Factură';
    protected static ?string $pluralModelLabel = 'Facturi';
    protected static ?string $navigationIcon   = 'heroicon-o-receipt-percent';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Factură')->tabs([

                Tab::make('Date generale')->schema([
                    Select::make('client_id')
                        ->label('Client')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('contract_id')
                        ->label('Contract (opțional)')
                        ->relationship('contract', 'number')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Select::make('type')
                        ->label('Tip document')
                        ->options(collect(InvoiceType::cases())->mapWithKeys(
                            fn (InvoiceType $t) => [$t->value => $t->label()]
                        ))
                        ->required()
                        ->default(InvoiceType::Factura->value),

                    Select::make('status')
                        ->label('Status')
                        ->options(collect(InvoiceStatus::cases())->mapWithKeys(
                            fn (InvoiceStatus $s) => [$s->value => $s->label()]
                        ))
                        ->default(InvoiceStatus::Draft->value)
                        ->required(),

                    TextInput::make('series')
                        ->label('Serie')
                        ->maxLength(20),

                    TextInput::make('number')
                        ->label('Număr')
                        ->numeric(),

                    Select::make('payment_method')
                        ->label('Modalitate plată')
                        ->options([
                            'ordin_plata' => 'Ordin de plată',
                            'numerar'     => 'Numerar',
                            'card'        => 'Card bancar',
                            'compensare'  => 'Compensare',
                        ])
                        ->default('ordin_plata')
                        ->required(),

                    TextInput::make('payment_reference')
                        ->label('Referință plată')
                        ->maxLength(100),

                    TextInput::make('currency')
                        ->label('Monedă')
                        ->default('RON')
                        ->maxLength(3),

                    DatePicker::make('issue_date')
                        ->label('Data emiterii')
                        ->required()
                        ->default(today())
                        ->displayFormat('d.m.Y'),

                    DatePicker::make('due_date')
                        ->label('Scadență')
                        ->displayFormat('d.m.Y'),

                    DatePicker::make('delivery_date')
                        ->label('Data livrării')
                        ->displayFormat('d.m.Y'),

                    Textarea::make('notes')
                        ->label('Observații')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),

                Tab::make('Linii factură')->schema([
                    Repeater::make('lines')
                        ->label('')
                        ->relationship()
                        ->schema([
                            Select::make('product_id')
                                ->label('Produs din catalog (opțional)')
                                ->options(fn () => Product::withoutGlobalScopes()->pluck('name', 'id'))
                                ->searchable()
                                ->nullable()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (! $state) {
                                        return;
                                    }
                                    $product = Product::withoutGlobalScopes()->find($state);
                                    if ($product) {
                                        $set('description', $product->name);
                                        $set('unit', $product->unit);
                                        $set('unit_price', $product->unit_price);
                                        $set('vat_rate_id', $product->vat_rate_id);
                                    }
                                })
                                ->columnSpan(2),

                            TextInput::make('description')
                                ->label('Produs / Serviciu')
                                ->required()
                                ->columnSpan(2),

                            TextInput::make('quantity')
                                ->label('Cantitate')
                                ->numeric()
                                ->default(1)
                                ->minValue(0.001),

                            TextInput::make('unit')
                                ->label('UM')
                                ->default('bucată'),

                            TextInput::make('unit_price')
                                ->label('Preț/UM')
                                ->numeric()
                                ->suffix('RON'),

                            Select::make('vat_rate_id')
                                ->label('Cotă TVA')
                                ->options(fn () => VatRate::selectOptions())
                                ->default(fn () => optional(VatRate::defaultRate())->id)
                                ->required(),
                        ])
                        ->columns(4)
                        ->addActionLabel('Adaugă linie')
                        ->reorderable('sort_order')
                        ->columnSpanFull(),
                ]),

                Tab::make('Totaluri')->schema([
                    Placeholder::make('subtotal_display')
                        ->label('Subtotal (fără TVA)')
                        ->content(fn (?Invoice $record) => $record
                            ? number_format((float) $record->subtotal, 2, ',', '.') . ' ' . ($record->currency ?? 'RON')
                            : '—'),

                    Placeholder::make('vat_total_display')
                        ->label('TVA')
                        ->content(fn (?Invoice $record) => $record
                            ? number_format((float) $record->vat_total, 2, ',', '.') . ' ' . ($record->currency ?? 'RON')
                            : '—'),

                    Placeholder::make('total_display')
                        ->label('TOTAL de plată')
                        ->content(fn (?Invoice $record) => $record
                            ? number_format((float) $record->total, 2, ',', '.') . ' ' . ($record->currency ?? 'RON')
                            : '—'),
                ])->columns(3),

            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_number')
                    ->label('Număr')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state) => $state instanceof InvoiceType ? $state->label() : $state)
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state) => $state instanceof InvoiceStatus ? $state->label() : $state)
                    ->color(fn (mixed $state) => $state instanceof InvoiceStatus ? $state->color() : 'gray'),

                TextColumn::make('issue_date')
                    ->label('Data emiterii')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Scadență')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (Invoice $record) => $record->isOverdue() ? 'danger' : null),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('RON')
                    ->sortable(),
            ])
            ->recordClasses(fn (Invoice $record) => $record->isOverdue() ? 'bg-red-50 dark:bg-red-950' : null)
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make()->label('Vezi'),

                EditAction::make()->label('Editează')
                    ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft),

                Action::make('finalizeaza')
                    ->label('Finalizează')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Finalizează factura')
                    ->modalDescription('Factura va fi marcată ca trimisă, PDF-ul va fi generat și stocul va fi dedus.')
                    ->modalSubmitActionLabel('Finalizează')
                    ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft)
                    ->action(function (Invoice $record) {
                        app(InvoiceService::class)->transition($record, InvoiceStatus::Trimisa);
                        Notification::make()->title('Factură finalizată')->success()->send();
                    }),

                Action::make('marcheaza_platita')
                    ->label('Marchează ca plătită')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Marchează factura ca plătită')
                    ->modalSubmitActionLabel('Marchează plătită')
                    ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Trimisa)
                    ->action(function (Invoice $record) {
                        app(InvoiceService::class)->transition($record, InvoiceStatus::Platita);
                        Notification::make()->title('Factură marcată ca plătită')->success()->send();
                    }),

                Action::make('anuleaza')
                    ->label('Anulează')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anulare factură')
                    ->modalDescription('Această acțiune nu poate fi anulată.')
                    ->modalSubmitActionLabel('Anulează factura')
                    ->visible(fn (Invoice $record) => $record->status !== InvoiceStatus::Anulata)
                    ->action(function (Invoice $record) {
                        app(InvoiceService::class)->transition($record, InvoiceStatus::Anulata);
                        Notification::make()->title('Factură anulată')->warning()->send();
                    }),

                Action::make('descarca_pdf')
                    ->label('Descarcă PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Invoice $record) => ! empty($record->pdf_path) && file_exists($record->pdf_path))
                    ->url(fn (Invoice $record) => route('invoices.pdf', $record))
                    ->openUrlInNewTab(),
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
            'index'  => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit'   => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
