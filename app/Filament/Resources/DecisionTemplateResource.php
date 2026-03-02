<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DecisionTemplateResource\Pages;
use App\Models\Company;
use App\Models\DecisionTemplate;
use App\Services\DecisionService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DecisionTemplateResource extends Resource
{
    protected static ?string $model = DecisionTemplate::class;

    protected static ?string $navigationGroup = 'Decizii Administrative';
    protected static ?string $navigationLabel = 'Șabloane decizii';
    protected static ?string $modelLabel = 'Șablon decizie';
    protected static ?string $pluralModelLabel = 'Șabloane decizii';
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('company_id')
                ->label('Companie (opțional)')
                ->options(fn (): array => Company::withoutGlobalScopes()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->nullable()
                ->helperText('Dacă este gol, șablonul este standard (global).'),

            TextInput::make('code')
                ->label('Cod')
                ->required()
                ->maxLength(100)
                ->helperText('Ex: decizie_numerotare'),

            TextInput::make('name')
                ->label('Denumire')
                ->required()
                ->maxLength(255),

            TextInput::make('category')
                ->label('Categorie')
                ->default('Decizii Administrative')
                ->required(),

            Toggle::make('is_active')
                ->label('Activ')
                ->default(true),

            Repeater::make('custom_fields_schema')
                ->label('Schema câmpuri personalizate')
                ->schema([
                    TextInput::make('key')
                        ->label('Cheie')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label('Etichetă')
                        ->required()
                        ->maxLength(120),
                    Select::make('field_type')
                        ->label('Tip câmp')
                        ->required()
                        ->options([
                            'text' => 'Text',
                            'textarea' => 'Text lung',
                            'number' => 'Număr',
                            'date' => 'Dată',
                            'select' => 'Select',
                            'toggle' => 'Da/Nu',
                            'array_strings' => 'Listă valori (string)',
                            'assets' => 'Listă active (casare)',
                        ])
                        ->default('text')
                        ->live(),
                    TagsInput::make('options')
                        ->label('Opțiuni (pentru select)')
                        ->visible(fn (Get $get): bool => $get('field_type') === 'select'),
                    Toggle::make('required')
                        ->label('Obligatoriu')
                        ->default(false),
                ])
                ->columns(2)
                ->addActionLabel('Adaugă câmp')
                ->reorderableWithButtons()
                ->collapsed(),

            Textarea::make('body_template')
                ->label('Conținut șablon')
                ->rows(18)
                ->required()
                ->columnSpanFull(),

            Placeholder::make('placeholder_list')
                ->label('Variabile disponibile')
                ->content(fn (Get $get): HtmlString => self::renderPlaceholders($get('custom_fields_schema')))
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Denumire')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Cod')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('company.name')
                    ->label('Companie')
                    ->formatStateUsing(fn (?string $state) => $state ?: 'Sistem')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Actualizat')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make()->label('Editează'),
                DeleteAction::make()->label('Șterge'),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDecisionTemplates::route('/'),
            'create' => Pages\CreateDecisionTemplate::route('/create'),
            'edit' => Pages\EditDecisionTemplate::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('superadmin') ?? false;
    }

    private static function renderPlaceholders(?array $customFields = null): HtmlString
    {
        $items = app(DecisionService::class)->placeholders($customFields ?? []);

        $rows = collect($items)->map(function (string $description, string $key): string {
            return '<tr class="border-b border-gray-100 dark:border-gray-700">'
                . '<td class="py-1 pr-4"><code>' . e($key) . '</code></td>'
                . '<td class="py-1">' . e($description) . '</td>'
                . '</tr>';
        })->implode('');

        return new HtmlString('
            <div class="overflow-x-auto text-sm">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b-2 border-gray-200 dark:border-gray-600">
                            <th class="py-2 pr-4">Variabilă</th>
                            <th class="py-2">Descriere</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        ');
    }
}
