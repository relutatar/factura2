<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Enums\ContractAmendmentStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AmendmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'amendments';

    protected static ?string $title = 'Acte adiționale';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('amendment_number')
                ->label('Număr')
                ->numeric()
                ->required(),
            Forms\Components\DatePicker::make('signed_date')
                ->label('Data semnării')
                ->native(false)
                ->displayFormat('d.m.Y'),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(collect(ContractAmendmentStatus::cases())
                    ->mapWithKeys(fn (ContractAmendmentStatus $status) => [$status->value => $status->label()])
                    ->all())
                ->default(ContractAmendmentStatus::Draft->value),
            Forms\Components\RichEditor::make('body')
                ->label('Conținut')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amendment_number')->label('Nr.')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ContractAmendmentStatus ? $state->label() : (string) $state)
                    ->color(fn (mixed $state): string => $state instanceof ContractAmendmentStatus ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('signed_date')->label('Semnat')->date('d.m.Y'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Adaugă act adițional'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Vezi'),
                Tables\Actions\EditAction::make()->label('Editează'),
                Tables\Actions\DeleteAction::make()->label('Șterge'),
            ]);
    }
}
