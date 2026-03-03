<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AnnexesRelationManager extends RelationManager
{
    protected static string $relationship = 'annexes';

    protected static ?string $title = 'Anexe';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Titlu')
                ->required(),
            Forms\Components\TextInput::make('annex_code')
                ->label('Cod anexă'),
            Forms\Components\RichEditor::make('body')
                ->label('Conținut')
                ->columnSpanFull(),
            Forms\Components\FileUpload::make('file_path')
                ->label('Fișier')
                ->directory('annexes')
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Titlu')->searchable(),
                Tables\Columns\TextColumn::make('annex_code')->label('Cod')->default('—'),
                Tables\Columns\TextColumn::make('file_original_name')->label('Fișier')->default('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Adaugă anexă'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Vezi'),
                Tables\Actions\EditAction::make()->label('Editează'),
                Tables\Actions\DeleteAction::make()->label('Șterge'),
            ]);
    }
}
