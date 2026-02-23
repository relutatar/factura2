<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers;
use App\Models\Client;
use App\Services\AnafService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Tip client')
                    ->options([
                        'persoana_juridica' => 'Persoană Juridică',
                        'persoana_fizica' => 'Persoană Fizică',
                    ])
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('name')
                    ->label('Nume')
                    ->required(),
                Forms\Components\TextInput::make('cif')
                    ->label('CIF')
                    ->live()
                    ->visible(fn (Forms\Get $get) => $get('type') === 'persoana_juridica')
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('anaf_lookup')
                            ->label('Caută în ANAF')
                            ->icon('heroicon-o-magnifying-glass')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
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

                                $set('name',    $result['denumire']   ?? '');
                                $set('reg_com', $result['nrRegCom']   ?? '');
                                $set('phone',   $result['telefon']    ?? '');
                                $set('address', $result['adresa']     ?? '');
                                $set('city',    $result['localitate'] ?? '');
                                $set('county',  $result['judet']      ?? '');

                                Notification::make()
                                    ->title('Date preluate din ANAF cu succes')
                                    ->success()
                                    ->send();
                            })
                    ),
                Forms\Components\TextInput::make('cnp')
                    ->label('CNP')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'persoana_fizica'),
                Forms\Components\TextInput::make('reg_com')
                    ->label('Reg. Com.')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'persoana_juridica'),
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
                    ->label('Notițe'),
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
                    ->color(fn (string $state) => match($state) {
                        'persoana_juridica' => 'success',
                        'persoana_fizica' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'persoana_juridica' => 'Persoană Juridică',
                        'persoana_fizica' => 'Persoană Fizică',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('cif')
                    ->label('CIF')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cnp')
                    ->label('CNP')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Oraș')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable(),
            ])
            ->filters([
                // Add filters as needed
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            //
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
}
