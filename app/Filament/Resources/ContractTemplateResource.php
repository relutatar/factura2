<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractTemplateResource\Pages;
use App\Models\ContractTemplate;
use App\Services\ContractTemplateService;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ContractTemplateResource extends Resource
{
    protected static ?string $model = ContractTemplate::class;

    protected static ?string $navigationGroup  = 'Contracte';
    protected static ?string $navigationLabel  = 'Șabloane contracte';
    protected static ?string $modelLabel       = 'Șablon contract';
    protected static ?string $pluralModelLabel = 'Șabloane contracte';
    protected static ?string $navigationIcon   = 'heroicon-o-document-duplicate';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Denumire șablon')
                ->required()
                ->maxLength(255),

            Toggle::make('is_default')
                ->label('Șablon implicit')
                ->helperText('Dacă este selectat, acest șablon va fi preselectat pe contractele noi.')
                ->default(false),

            Toggle::make('is_active')
                ->label('Activ')
                ->default(true),

            Textarea::make('description')
                ->label('Descriere')
                ->rows(2)
                ->columnSpanFull(),

            Repeater::make('custom_fields')
                ->label('Atribute suplimentare contract')
                ->helperText('Definiți câmpurile personalizate disponibile pentru contractele create din acest șablon.')
                ->schema([
                    TextInput::make('key')
                        ->label('Cheie')
                        ->required()
                        ->maxLength(50)
                        ->rules(['regex:/^[a-z][a-z0-9_]*$/'])
                        ->dehydrateStateUsing(fn (?string $state): string => (string) str($state ?? '')
                            ->lower()
                            ->replace('-', '_')
                            ->replace(' ', '_')
                            ->replaceMatches('/[^a-z0-9_]/', '')
                            ->trim('_')
                        )
                        ->helperText('Folosit în placeholder: {{attr.cheie}}'),
                    TextInput::make('label')
                        ->label('Etichetă')
                        ->required()
                        ->maxLength(120),
                    Select::make('field_type')
                        ->label('Tip câmp')
                        ->options([
                            'text'     => 'Text',
                            'textarea' => 'Text lung',
                            'number'   => 'Număr',
                            'date'     => 'Dată',
                            'select'   => 'Select',
                            'toggle'   => 'Da/Nu',
                        ])
                        ->default('text')
                        ->required()
                        ->live(),
                    TagsInput::make('options')
                        ->label('Opțiuni select')
                        ->placeholder('Adaugă opțiune')
                        ->visible(fn (Get $get): bool => $get('field_type') === 'select')
                        ->helperText('Pentru tipul Select, fiecare etichetă adăugată devine o opțiune.'),
                    Toggle::make('required')
                        ->label('Obligatoriu')
                        ->default(false),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->reorderableWithButtons()
                ->addActionLabel('Adaugă atribut')
                ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['key'] ?? null)
                ->collapsed(),

            Select::make('standard_model')
                ->label('Model standard (prepopulare)')
                ->options(fn (): array => app(ContractTemplateService::class)->standardModelOptions())
                ->default('prestari_servicii_cadru')
                ->dehydrated(false)
                ->searchable()
                ->placeholder('Alegeți un model standard')
                ->helperText('Selectați un model și apăsați „Aplică model standard”.'),

            Actions::make([
                Action::make('apply_standard_model')
                    ->label('Aplică model standard')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->action(function (Get $get, Set $set): void {
                        $model = $get('standard_model');

                        if (blank($model)) {
                            Notification::make()
                                ->title('Selectați mai întâi un model standard')
                                ->warning()
                                ->send();
                            return;
                        }

                        $content = app(ContractTemplateService::class)->standardModelContent((string) $model);
                        $customFields = app(ContractTemplateService::class)->standardModelCustomFields((string) $model);
                        $set('content', $content);
                        $set('custom_fields', $customFields);

                        Notification::make()
                            ->title('Text șablon și atribute prepopulate')
                            ->success()
                            ->send();
                    }),
            ])->columnSpan(2),

            RichEditor::make('content')
                ->label('Text șablon')
                ->required()
                ->columnSpanFull()
                ->toolbarButtons([
                    'blockquote',
                    'bold',
                    'bulletList',
                    'codeBlock',
                    'h2',
                    'h3',
                    'italic',
                    'orderedList',
                    'redo',
                    'strike',
                    'underline',
                    'undo',
                ])
                ->helperText('Editor WYSIWYG. Inserați placeholders prin copy/paste din lista de mai jos (ex: {{contract.number}}).'),

            Placeholder::make('placeholder_list')
                ->label('Placeholders disponibile')
                ->content(fn (Get $get): HtmlString => self::renderPlaceholders($get('custom_fields')))
                ->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Denumire')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descriere')
                    ->limit(70)
                    ->toggleable(),

                IconColumn::make('is_default')
                    ->label('Implicit')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Actualizat la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('is_default', 'desc')
            ->actions([
                EditAction::make()->label('Editează'),
                DeleteAction::make()->label('Șterge'),
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
            'index'  => Pages\ListContractTemplates::route('/'),
            'create' => Pages\CreateContractTemplate::route('/create'),
            'edit'   => Pages\EditContractTemplate::route('/{record}/edit'),
        ];
    }

    private static function renderPlaceholders(?array $customFields = null): HtmlString
    {
        $items = app(ContractTemplateService::class)->placeholders($customFields ?? []);

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
                            <th class="py-2 pr-4">Placeholder</th>
                            <th class="py-2">Descriere</th>
                        </tr>
                    </thead>
                    <tbody>' . $rows . '</tbody>
                </table>
            </div>
        ');
    }
}
