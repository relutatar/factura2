<?php

namespace App\Filament\Resources;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Filament\Resources\ContractResource\Pages;
use App\Filament\Resources\InvoiceResource;
use App\Models\Contract;
use App\Services\InvoiceService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationGroup  = 'Contracte';
    protected static ?string $navigationLabel  = 'Contracte';
    protected static ?string $modelLabel       = 'Contract';
    protected static ?string $pluralModelLabel = 'Contracte';
    protected static ?string $navigationIcon   = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make()->tabs([
                Tabs\Tab::make('General')->schema([
                    TextInput::make('number')
                        ->label('Număr contract')
                        ->required(),
                    Select::make('client_id')
                        ->label('Client')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->required(),
                    Select::make('type')
                        ->label('Tip contract')
                        ->options([
                            'mentenanta_ddd'      => 'Mentenanță DDD',
                            'eveniment_paintball' => 'Eveniment Paintball',
                        ])
                        ->required()
                        ->live(),
                    TextInput::make('title')
                        ->label('Titlu contract')
                        ->required(),
                    DatePicker::make('start_date')
                        ->label('Data început')
                        ->required()
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('end_date')
                        ->label('Data sfârșit')
                        ->displayFormat('d.m.Y'),
                    TextInput::make('value')
                        ->label('Valoare')
                        ->numeric()
                        ->suffix('RON'),
                    Select::make('billing_cycle')
                        ->label('Ciclu facturare')
                        ->options([
                            'lunar'       => 'Lunar',
                            'trimestrial' => 'Trimestrial',
                            'anual'       => 'Anual',
                            'unic'        => 'Unic',
                        ]),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'activ'     => 'Activ',
                            'suspendat' => 'Suspendat',
                            'expirat'   => 'Expirat',
                            'reziliat'  => 'Reziliat',
                        ])
                        ->default('activ'),
                    Textarea::make('notes')
                        ->label('Observații')
                        ->rows(2),
                ]),

                Tabs\Tab::make('DDD (NOD)')
                    ->visible(fn (Get $get) => $get('type') === 'mentenanta_ddd')
                    ->schema([
                        Select::make('ddd_frequency')
                            ->label('Frecvență tratament')
                            ->options([
                                'lunar'       => 'Lunar',
                                'bilunar'     => 'La 2 luni',
                                'trimestrial' => 'Trimestrial',
                                'semestrial'  => 'Semestrial',
                                'anual'       => 'Anual',
                            ]),
                        Repeater::make('ddd_locations')
                            ->label('Locații tratate')
                            ->schema([
                                TextInput::make('name')->label('Denumire locație')->required(),
                                TextInput::make('address')->label('Adresă'),
                                TextInput::make('treatment_type')->label('Tip tratament'),
                            ])
                            ->columns(3),
                    ]),

                Tabs\Tab::make('Paintball')
                    ->visible(fn (Get $get) => $get('type') === 'eveniment_paintball')
                    ->schema([
                        TextInput::make('paintball_sessions')
                            ->label('Număr ședințe')
                            ->numeric(),
                        TextInput::make('paintball_players')
                            ->label('Jucători per ședință')
                            ->numeric(),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Nr. contract')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state) => $state instanceof ContractType ? $state->label() : $state)
                    ->color(fn (mixed $state) => $state instanceof ContractType ? $state->color() : 'gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state) => $state instanceof ContractStatus ? $state->label() : $state)
                    ->color(fn (mixed $state) => $state instanceof ContractStatus ? $state->color() : 'gray'),
                TextColumn::make('start_date')
                    ->label('Data început')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Data sfârșit')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (mixed $state) => $state && $state->isPast()
                        ? 'danger'
                        : ($state && $state->diffInDays(now()) <= 30 ? 'warning' : null)
                    ),
                TextColumn::make('value')
                    ->label('Valoare')
                    ->money('RON')
                    ->sortable(),
            ])
            ->recordClasses(fn (Contract $record) =>
                $record->end_date
                    && $record->end_date->diffInDays(now(), false) >= 0
                    && $record->end_date->diffInDays(now(), false) <= 30
                    ? 'bg-yellow-50 dark:bg-yellow-950'
                    : null
            )
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Vezi'),
                Tables\Actions\EditAction::make()->label('Editează'),
                Action::make('genereaza_factura')
                    ->label('Generează Factură')
                    ->icon('heroicon-o-document-plus')
                    ->requiresConfirmation()
                    ->modalHeading('Generează factură din contract')
                    ->modalDescription('Se va crea o factură draft pe baza acestui contract.')
                    ->modalSubmitActionLabel('Generează')
                    ->action(function (Contract $record) {
                        $invoice = app(InvoiceService::class)->createFromContract($record);
                        Notification::make()->title('Factură creată cu succes')->success()->send();
                        return redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                    }),
                Tables\Actions\DeleteAction::make()->label('Șterge'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit'   => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
