<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractAnnexResource\Pages;
use App\Models\Contract;
use App\Models\ContractAnnex;
use App\Models\DocumentTemplate;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractAnnexResource extends Resource
{
    protected static ?string $model = ContractAnnex::class;

    protected static ?string $navigationGroup = 'Contracte';
    protected static ?string $navigationLabel = 'Anexe';
    protected static ?string $modelLabel = 'Anexă';
    protected static ?string $pluralModelLabel = 'Anexe';
    protected static ?string $navigationIcon = 'heroicon-o-paper-clip';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Anexă')->tabs([
                Tabs\Tab::make('Date generale')->schema([
                    Select::make('contract_id')
                        ->label('Contract')
                        ->options(fn () => Contract::withoutGlobalScopes()->pluck('number', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('title')
                        ->label('Titlu anexă')
                        ->required(),

                    TextInput::make('annex_code')
                        ->label('Cod (ex: Anexa 1, Anexa A)'),

                    Textarea::make('notes')
                        ->label('Observații')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),

                Tabs\Tab::make('Conținut generat')->schema([
                    Select::make('document_template_id')
                        ->label('Șablon document')
                        ->options(fn () => DocumentTemplate::withoutGlobalScopes()
                            ->where('context_type', 'contract')
                            ->where('is_active', true)
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    RichEditor::make('body')
                        ->label('Conținut anexă generate')
                        ->columnSpanFull()
                        ->nullable(),
                ]),

                Tabs\Tab::make('Fișier atașat')->schema([
                    FileUpload::make('file_path')
                        ->label('Fișier anexă')
                        ->directory('annexes')
                        ->storeFileNamesIn('file_original_name')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/*',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ])
                        ->visibility('private')
                        ->nullable(),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titlu')->searchable(),
                TextColumn::make('annex_code')->label('Cod')->default('—'),
                TextColumn::make('contract.number')->label('Contract')->searchable(),
                TextColumn::make('documentTemplate.name')->label('Șablon')->default('—'),
                TextColumn::make('file_original_name')->label('Fișier')->default('—')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make()->label('Vezi'),
                EditAction::make()->label('Editează'),
                DeleteAction::make()->label('Șterge'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractAnnexes::route('/'),
            'create' => Pages\CreateContractAnnex::route('/create'),
            'edit' => Pages\EditContractAnnex::route('/{record}/edit'),
            'view' => Pages\ViewContractAnnex::route('/{record}'),
        ];
    }
}
