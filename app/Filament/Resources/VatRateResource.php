<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VatRateResource\Pages;
use App\Models\VatRate;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VatRateResource extends Resource
{
    protected static ?string $model = VatRate::class;

    protected static ?string $navigationGroup  = 'Configurare';
    protected static ?string $navigationLabel  = 'Cote TVA';
    protected static ?string $modelLabel       = 'Cotă TVA';
    protected static ?string $pluralModelLabel = 'Cote TVA';
    protected static ?string $navigationIcon   = 'heroicon-o-percent-badge';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('value')
                ->label('Valoare (%)')
                ->numeric()
                ->suffix('%')
                ->required(),

            TextInput::make('label')
                ->label('Etichetă')
                ->required()
                ->maxLength(100),

            TextInput::make('description')
                ->label('Descriere')
                ->maxLength(255),

            TextInput::make('sort_order')
                ->label('Ordine')
                ->numeric()
                ->default(0),

            Toggle::make('is_default')
                ->label('Implicită'),

            Toggle::make('is_active')
                ->label('Activă')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('value')
                    ->label('Cotă')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->sortable(),

                TextColumn::make('label')
                    ->label('Etichetă')
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Descriere')
                    ->searchable(),

                IconColumn::make('is_default')
                    ->label('Implicită')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Activă')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    // ─── Authorization ──────────────────────────────────────────────────────────
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('superadmin') ?? false;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('superadmin') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('superadmin') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('superadmin') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('superadmin') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVatRates::route('/'),
            'create' => Pages\CreateVatRate::route('/create'),
            'edit'   => Pages\EditVatRate::route('/{record}/edit'),
        ];
    }
}
