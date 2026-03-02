<?php

namespace App\Filament\Resources;

use App\Enums\BillingCycle;
use App\Enums\ProformaStatus;
use App\Filament\Resources\ProformaResource\Pages;
use App\Models\Contract;
use App\Models\Proforma;
use App\Models\Product;
use App\Models\VatRate;
use App\Services\ProformaService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
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
use Illuminate\Support\HtmlString;

class ProformaResource extends Resource
{
    protected static ?string $model = Proforma::class;

    protected static ?string $navigationGroup  = 'Facturi';
    protected static ?string $navigationLabel  = 'Proforme';
    protected static ?string $modelLabel       = 'Proformă';
    protected static ?string $pluralModelLabel = 'Proforme';
    protected static ?string $navigationIcon   = 'heroicon-o-document-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Proformă')->tabs([

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
                        ->nullable()
                        ->live(),

                    Select::make('status')
                        ->label('Status')
                        ->options(collect(ProformaStatus::cases())->mapWithKeys(
                            fn (ProformaStatus $s) => [$s->value => $s->label()]
                        ))
                        ->default(ProformaStatus::Draft->value)
                        ->required()
                        ->disabled(fn (?Proforma $record) => $record !== null),

                    DatePicker::make('issue_date')
                        ->label('Data emiterii')
                        ->required()
                        ->default(today())
                        ->displayFormat('d.m.Y'),

                    DatePicker::make('valid_until')
                        ->label('Valabilă până la')
                        ->displayFormat('d.m.Y'),

                    Textarea::make('notes')
                        ->label('Observații')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),

                Tab::make('Linii proformă')->schema([
                    Repeater::make('lines')
                        ->label('')
                        ->relationship()
                        ->schema([
                            ToggleButtons::make('line_mode')
                                ->label('Tip linie')
                                ->options([
                                    'serviciu' => 'Serviciu',
                                    'produs'   => 'Produs din catalog',
                                ])
                                ->icons([
                                    'serviciu' => 'heroicon-o-wrench-screwdriver',
                                    'produs'   => 'heroicon-o-cube',
                                ])
                                ->colors([
                                    'serviciu' => 'info',
                                    'produs'   => 'success',
                                ])
                                ->inline()
                                ->default('serviciu')
                                ->live()
                                ->dehydrated(false)
                                ->afterStateHydrated(function ($component, Get $get) {
                                    if ($get('product_id')) {
                                        $component->state('produs');
                                    } else {
                                        $component->state('serviciu');
                                    }
                                })
                                ->hiddenOn('view')
                                ->columnSpanFull(),

                            Placeholder::make('line_mode_label')
                                ->label('Tip linie')
                                ->content(fn (Get $get) => match ($get('line_mode')) {
                                    'produs'   => 'Produs din catalog',
                                    default    => 'Serviciu',
                                })
                                ->visibleOn('view')
                                ->columnSpanFull(),

                            Select::make('product_id')
                                ->label('Produs din catalog')
                                ->options(fn () => Product::withoutGlobalScopes()->pluck('name', 'id'))
                                ->searchable()
                                ->nullable()
                                ->live()
                                ->visible(fn (Get $get) => $get('line_mode') === 'produs')
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (! $state) {
                                        return;
                                    }
                                    $product = Product::withoutGlobalScopes()->find($state);
                                    if ($product) {
                                        $set('description', $product->name);
                                        $set('unit',         $product->unit ?? 'bucată');
                                        $set('unit_display', $product->unit ?? 'bucată');
                                        $set('unit_price',   $product->unit_price);
                                        $set('vat_rate_id',  $product->vat_rate_id);
                                    }
                                })
                                ->columnSpan(4),

                            TextInput::make('description')
                                ->label('Descriere')
                                ->required()
                                ->columnSpan(4),

                            TextInput::make('quantity')
                                ->label('Cantitate')
                                ->numeric()
                                ->default(1)
                                ->minValue(0.001),

                            Select::make('unit')
                                ->label('Perioadă / UM')
                                ->options(collect(BillingCycle::cases())
                                    ->mapWithKeys(fn (BillingCycle $c) => [$c->value => $c->label()])
                                    ->all()
                                )
                                ->default(BillingCycle::Unic->value)
                                ->searchable()
                                ->required()
                                ->visible(fn (Get $get) => $get('line_mode') !== 'produs'),

                            TextInput::make('unit_display')
                                ->label('UM (din produs)')
                                ->disabled()
                                ->dehydrated(false)
                                ->afterStateHydrated(fn ($component, Get $get) => $component->state($get('unit') ?: 'bucată'))
                                ->visible(fn (Get $get) => $get('line_mode') === 'produs'),

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
                    Placeholder::make('lines_summary')
                        ->label('Linii proformă')
                        ->content(function (?Proforma $record): HtmlString {
                            if (! $record || $record->lines->isEmpty()) {
                                return new HtmlString('<p class="text-sm text-gray-400">Nicio linie salvată.</p>');
                            }

                            $rows = $record->lines->map(function ($line): string {
                                $qty          = (float) $line->quantity;
                                $qtyFormatted = $qty == (int) $qty
                                    ? (string) (int) $qty
                                    : number_format($qty, 2, ',', '.');
                                $unitPrice    = number_format((float) $line->unit_price, 2, ',', '.');
                                $lineTotal    = number_format((float) $line->line_total, 2, ',', '.');
                                $vatAmount    = number_format((float) $line->vat_amount, 2, ',', '.');
                                $totalWithVat = number_format((float) $line->total_with_vat, 2, ',', '.');
                                $vatLabel     = $line->vatRate ? $line->vatRate->value . '%' : '—';

                                return "<tr class=\"border-b border-gray-100 dark:border-gray-700\">
                                    <td class=\"py-2 pr-4 text-sm\">{$line->description}</td>
                                    <td class=\"py-2 pr-4 text-sm text-center\">{$qtyFormatted}</td>
                                    <td class=\"py-2 pr-4 text-sm text-center\">{$line->unit}</td>
                                    <td class=\"py-2 pr-4 text-sm text-right\">{$unitPrice} RON</td>
                                    <td class=\"py-2 pr-4 text-sm text-center\">{$vatLabel}</td>
                                    <td class=\"py-2 pr-4 text-sm text-right\">{$vatAmount} RON</td>
                                    <td class=\"py-2 text-sm text-right font-medium\">{$totalWithVat} RON</td>
                                </tr>";
                            })->implode('');

                            return new HtmlString("
                                <div class=\"overflow-x-auto\">
                                <table class=\"w-full text-left\">
                                    <thead>
                                        <tr class=\"border-b-2 border-gray-200 dark:border-gray-600\">
                                            <th class=\"py-2 pr-4 text-xs font-semibold text-gray-500 uppercase\">Descriere</th>
                                            <th class=\"py-2 pr-4 text-xs font-semibold text-gray-500 uppercase text-center\">Cantitate</th>
                                            <th class=\"py-2 pr-4 text-xs font-semibold text-gray-500 uppercase text-center\">UM</th>
                                            <th class=\"py-2 pr-4 text-xs font-semibold text-gray-500 uppercase text-right\">Preț/UM</th>
                                            <th class=\"py-2 pr-4 text-xs font-semibold text-gray-500 uppercase text-center\">TVA</th>
                                            <th class=\"py-2 pr-4 text-xs font-semibold text-gray-500 uppercase text-right\">Val. TVA</th>
                                            <th class=\"py-2 text-xs font-semibold text-gray-500 uppercase text-right\">Total cu TVA</th>
                                        </tr>
                                    </thead>
                                    <tbody>{$rows}</tbody>
                                </table>
                                </div>
                            ");
                        })
                        ->columnSpanFull(),

                    Placeholder::make('subtotal_display')
                        ->label('Subtotal (fără TVA)')
                        ->content(fn (?Proforma $record) => $record
                            ? number_format((float) $record->subtotal, 2, ',', '.') . ' RON'
                            : '—'),

                    Placeholder::make('vat_total_display')
                        ->label('TVA')
                        ->content(fn (?Proforma $record) => $record
                            ? number_format((float) $record->vat_total, 2, ',', '.') . ' RON'
                            : '—'),

                    Placeholder::make('total_display')
                        ->label('TOTAL')
                        ->content(fn (?Proforma $record) => $record
                            ? number_format((float) $record->total, 2, ',', '.') . ' RON'
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
                    ->sortable()
                    ->placeholder('Ciornă'),

                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state) => $state instanceof ProformaStatus ? $state->label() : $state)
                    ->color(fn (mixed $state) => $state instanceof ProformaStatus ? $state->color() : 'gray'),

                TextColumn::make('issue_date')
                    ->label('Data emiterii')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Valabilă până la')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('RON')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make()->label('Vezi'),

                EditAction::make()->label('Editează')
                    ->visible(fn (Proforma $record) => $record->isEditable()),

                Action::make('emite')
                    ->label('Emite')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Emite proformă')
                    ->modalDescription('Proforma va fi numerotată și marcată ca trimisă. PDF-ul va fi generat automat.')
                    ->modalSubmitActionLabel('Emite')
                    ->visible(fn (Proforma $record) => $record->status === ProformaStatus::Draft)
                    ->action(function (Proforma $record) {
                        app(ProformaService::class)->emit($record);
                        Notification::make()->title('Proformă emisă')->success()->send();
                    }),

                Action::make('converteste_factura')
                    ->label('Convertește în factură')
                    ->icon('heroicon-o-document-plus')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Convertește proforma în factură fiscală')
                    ->modalDescription('Se va crea o factură fiscală ciornă pe baza acestei profarme. Proforma va fi marcată ca Convertită.')
                    ->modalSubmitActionLabel('Convertește')
                    ->visible(fn (Proforma $record) => $record->status === ProformaStatus::Trimisa)
                    ->action(function (Proforma $record) {
                        $invoice = app(ProformaService::class)->convertToInvoice($record);
                        Notification::make()->title('Factură creată din proformă')->success()->send();

                        return redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                    }),

                Action::make('descarca_pdf')
                    ->label('Descarcă PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Proforma $record) => ! empty($record->pdf_path) && file_exists($record->pdf_path))
                    ->url(fn (Proforma $record) => route('proformas.pdf', $record))
                    ->openUrlInNewTab(),

                Action::make('anuleaza')
                    ->label('Anulează')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anulare proformă')
                    ->modalDescription('Această acțiune nu poate fi anulată.')
                    ->modalSubmitActionLabel('Anulează proforma')
                    ->visible(fn (Proforma $record) => $record->status !== ProformaStatus::Anulata)
                    ->action(function (Proforma $record) {
                        $record->update(['status' => ProformaStatus::Anulata]);
                        Notification::make()->title('Proformă anulată')->warning()->send();
                    }),
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
            'index'  => Pages\ListProformas::route('/'),
            'create' => Pages\CreateProforma::route('/create'),
            'edit'   => Pages\EditProforma::route('/{record}/edit'),
        ];
    }
}
