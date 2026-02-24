<?php

namespace App\Filament\Resources;

use App\Enums\ClientType;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use App\Services\AnafService;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;
    protected static ?string $navigationGroup = 'Clienți';
    protected static ?string $navigationLabel = 'Clienți';
    protected static ?string $modelLabel = 'Client';
    protected static ?string $pluralModelLabel = 'Clienți';
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Client')
                ->tabs([
                    Tabs\Tab::make('Date generale')
                        ->schema([
                            Forms\Components\Select::make('type')
                                ->label('Tip client')
                                ->options([
                                    ClientType::PersoanaJuridica->value => 'Persoană Juridică',
                                    ClientType::PersoanaFizica->value   => 'Persoană Fizică',
                                ])
                                ->required()
                                ->live(),

                            Forms\Components\TextInput::make('name')
                                ->label('Nume')
                                ->required(),

                            Forms\Components\TextInput::make('cif')
                                ->label('CIF')
                                ->live()
                                ->visible(fn (Forms\Get $get): bool => (
                                    ($get('type') instanceof ClientType ? $get('type')->value : $get('type'))
                                    === ClientType::PersoanaJuridica->value
                                ))
                                ->suffixAction(
                                    Forms\Components\Actions\Action::make('anaf_lookup')
                                        ->label('Caută în ANAF')
                                        ->icon('heroicon-o-magnifying-glass')
                                        ->action(function (Forms\Get $get, Forms\Set $set): void {
                                            $cif = $get('cif');
                                            if (! $cif) {
                                                Notification::make()
                                                    ->title('Introduceți un CIF înainte de căutare')
                                                    ->warning()
                                                    ->send();
                                                return;
                                            }

                                            $result = app(AnafService::class)->lookupCif($cif);

                                            if (! $result) {
                                                Notification::make()
                                                    ->title('CIF-ul nu a fost găsit în ANAF')
                                                    ->danger()
                                                    ->send();
                                                return;
                                            }

                                            $set('name', $result['denumire'] ?? '');
                                            $set('reg_com', $result['nrRegCom'] ?? '');
                                            $set('phone', $result['telefon'] ?? '');
                                            $set('address', $result['adresa'] ?? '');
                                            $set('city', $result['localitate'] ?? '');
                                            $set('county', $result['judet'] ?? '');

                                            Notification::make()
                                                ->title('Date preluate din ANAF cu succes')
                                                ->success()
                                                ->send();
                                        })
                                ),

                            Forms\Components\TextInput::make('cnp')
                                ->label('CNP')
                                ->visible(fn (Forms\Get $get): bool => (
                                    ($get('type') instanceof ClientType ? $get('type')->value : $get('type'))
                                    === ClientType::PersoanaFizica->value
                                )),

                            Forms\Components\TextInput::make('reg_com')
                                ->label('Reg. Com.')
                                ->visible(fn (Forms\Get $get): bool => (
                                    ($get('type') instanceof ClientType ? $get('type')->value : $get('type'))
                                    === ClientType::PersoanaJuridica->value
                                )),

                            Forms\Components\TextInput::make('address')
                                ->label('Adresă'),

                            Forms\Components\TextInput::make('city')
                                ->label('Oraș'),

                            Forms\Components\TextInput::make('county')
                                ->label('Județ'),

                            Forms\Components\TextInput::make('phone')
                                ->label('Telefon'),

                            Forms\Components\TextInput::make('email')
                                ->label('Email'),

                            Forms\Components\Textarea::make('notes')
                                ->label('Notițe')
                                ->rows(3)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Tabs\Tab::make('Contracte')
                        ->schema([
                            Placeholder::make('contracts_preview')
                                ->label('')
                                ->content(fn (?Client $record): HtmlString => self::renderContractsPreview($record))
                                ->columnSpanFull(),
                        ]),

                    Tabs\Tab::make('Facturi')
                        ->schema([
                            Placeholder::make('invoices_preview')
                                ->label('')
                                ->content(fn (?Client $record): HtmlString => self::renderInvoicesPreview($record))
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->color(fn (mixed $state) => match($state instanceof \App\Enums\ClientType ? $state->value : $state) {
                        'persoana_juridica' => 'success',
                        'persoana_fizica'   => 'info',
                        default             => 'gray',
                    })
                    ->formatStateUsing(fn (mixed $state) => match($state instanceof \App\Enums\ClientType ? $state->value : $state) {
                        'persoana_juridica' => 'Persoană Juridică',
                        'persoana_fizica'   => 'Persoană Fizică',
                        default             => (string) $state,
                    }),
                Tables\Columns\TextColumn::make('cif')
                    ->label('CIF')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cnp')
                    ->label('CNP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city')
                    ->label('Oraș')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                // Add filters as needed
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Vezi'),
                Tables\Actions\EditAction::make()->label('Editează'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContactsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    protected static function renderContractsPreview(?Client $record): HtmlString
    {
        if (! $record?->exists) {
            return new HtmlString('<p class="text-sm text-gray-500">Salvați clientul pentru a vedea contractele.</p>');
        }

        $contracts = $record->contracts()
            ->with('template:id,name')
            ->latest('start_date')
            ->get(['number', 'contract_template_id', 'status', 'start_date', 'end_date', 'value', 'currency']);

        if ($contracts->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500">Nu există contracte asociate acestui client.</p>');
        }

        $rows = $contracts->map(function ($contract): string {
            $number = e($contract->number ?? '—');
            $template = e($contract->template?->name ?? '—');
            $status = $contract->status instanceof ContractStatus
                ? $contract->status->label()
                : (string) $contract->status;
            $startDate = $contract->start_date?->format('d.m.Y') ?? '—';
            $endDate = $contract->end_date?->format('d.m.Y') ?? '—';
            $value = number_format((float) $contract->value, 2, ',', '.');
            $currency = e($contract->currency ?? 'RON');

            return "<tr class=\"border-b border-gray-100 dark:border-gray-700\">
                <td class=\"py-2 pr-4 text-sm\">{$number}</td>
                <td class=\"py-2 pr-4 text-sm\">{$template}</td>
                <td class=\"py-2 pr-4 text-sm\">{$status}</td>
                <td class=\"py-2 pr-4 text-sm\">{$startDate}</td>
                <td class=\"py-2 pr-4 text-sm\">{$endDate}</td>
                <td class=\"py-2 text-sm text-right\">{$value} {$currency}</td>
            </tr>";
        })->implode('');

        return new HtmlString("
            <div class=\"overflow-x-auto\">
                <table class=\"w-full text-left\">
                    <thead>
                        <tr class=\"border-b-2 border-gray-200 dark:border-gray-600\">
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Nr. contract</th>
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Șablon contract</th>
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Status</th>
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Data început</th>
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Data sfârșit</th>
                            <th class=\"py-2 text-xs font-semibold uppercase text-gray-500 text-right\">Valoare</th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        ");
    }

    protected static function renderInvoicesPreview(?Client $record): HtmlString
    {
        if (! $record?->exists) {
            return new HtmlString('<p class="text-sm text-gray-500">Salvați clientul pentru a vedea facturile.</p>');
        }

        $invoices = $record->invoices()
            ->latest('issue_date')
            ->get(['id', 'full_number', 'type', 'status', 'issue_date', 'due_date', 'total', 'currency']);

        if ($invoices->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500">Nu există facturi asociate acestui client.</p>');
        }

        $rows = $invoices->map(function ($invoice): string {
            $number = e($invoice->full_number ?: ('#' . $invoice->id));
            $type = $invoice->type instanceof InvoiceType
                ? $invoice->type->label()
                : (string) $invoice->type;
            $status = $invoice->status instanceof InvoiceStatus
                ? $invoice->status->label()
                : (string) $invoice->status;
            $issueDate = $invoice->issue_date?->format('d.m.Y') ?? '—';
            $dueDate = $invoice->due_date?->format('d.m.Y') ?? '—';
            $total = number_format((float) $invoice->total, 2, ',', '.');
            $currency = e($invoice->currency ?? 'RON');

            return "<tr class=\"border-b border-gray-100 dark:border-gray-700\">
                <td class=\"py-2 pr-4 text-sm\">{$number}</td>
                <td class=\"py-2 pr-4 text-sm\">{$type}</td>
                <td class=\"py-2 pr-4 text-sm\">{$status}</td>
                <td class=\"py-2 pr-4 text-sm\">{$issueDate}</td>
                <td class=\"py-2 pr-4 text-sm\">{$dueDate}</td>
                <td class=\"py-2 text-sm text-right\">{$total} {$currency}</td>
            </tr>";
        })->implode('');

        return new HtmlString("
            <div class=\"overflow-x-auto\">
                <table class=\"w-full text-left\">
                    <thead>
                        <tr class=\"border-b-2 border-gray-200 dark:border-gray-600\">
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Număr</th>
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Tip</th>
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Status</th>
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Data emiterii</th>
                            <th class=\"py-2 pr-4 text-xs font-semibold uppercase text-gray-500\">Scadență</th>
                            <th class=\"py-2 text-xs font-semibold uppercase text-gray-500 text-right\">Total</th>
                        </tr>
                    </thead>
                    <tbody>{$rows}</tbody>
                </table>
            </div>
        ");
    }
}
