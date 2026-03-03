<?php

namespace App\Filament\Resources;

use App\Enums\ContractAmendmentStatus;
use App\Filament\Resources\ContractAmendmentResource\Pages;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractAmendment;
use App\Models\DocumentTemplate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractAmendmentResource extends Resource
{
    protected static ?string $model = ContractAmendment::class;

    protected static ?string $navigationGroup = 'Contracte';
    protected static ?string $navigationLabel = 'Acte Adiționale';
    protected static ?string $modelLabel = 'Act Adițional';
    protected static ?string $pluralModelLabel = 'Acte Adiționale';
    protected static ?string $navigationIcon = 'heroicon-o-document-plus';

    public static function canAccess(): bool
    {
        $company = Company::withoutGlobalScopes()->find(session('active_company_id'));

        return $company?->hasModule('acte_aditionale') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Act adițional')->tabs([
                Tabs\Tab::make('Date document')->schema([
                    Select::make('contract_id')
                        ->label('Contract')
                        ->options(fn () => Contract::withoutGlobalScopes()->pluck('number', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('document_template_id')
                        ->label('Șablon document')
                        ->options(fn () => DocumentTemplate::withoutGlobalScopes()
                            ->where('context_type', 'contract')
                            ->where('is_active', true)
                            ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    TextInput::make('amendment_number')
                        ->label('Număr act adițional')
                        ->numeric()
                        ->helperText('Dacă este gol, se alocă automat per contract.'),

                    DatePicker::make('signed_date')
                        ->label('Data semnării')
                        ->native(false)
                        ->displayFormat('d.m.Y'),

                    Select::make('status')
                        ->label('Status')
                        ->options(collect(ContractAmendmentStatus::cases())
                            ->mapWithKeys(fn (ContractAmendmentStatus $status) => [$status->value => $status->label()])
                            ->all())
                        ->default(ContractAmendmentStatus::Draft->value)
                        ->required(),

                    Textarea::make('notes')
                        ->label('Observații')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),

                Tabs\Tab::make('Conținut')->schema([
                    RichEditor::make('body')
                        ->label('Text act adițional')
                        ->required()
                        ->columnSpanFull(),
                ]),

                Tabs\Tab::make('Atribute modificate')->schema([
                    KeyValue::make('attributes')
                        ->label('Atribute modificate prin actul adițional')
                        ->helperText('Ex: valoare_noua, data_expirare_noua etc.')
                        ->nullable()
                        ->columnSpanFull(),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amendment_number')->label('Nr. AA')->sortable(),
                TextColumn::make('contract.number')->label('Contract')->searchable(),
                TextColumn::make('contract.client.name')->label('Client')->searchable(),
                TextColumn::make('signed_date')->label('Semnat la')->date('d.m.Y')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ContractAmendmentStatus ? $state->label() : (string) $state)
                    ->color(fn (mixed $state): string => $state instanceof ContractAmendmentStatus ? $state->color() : 'gray'),
            ])
            ->defaultSort('amendment_number', 'desc')
            ->actions([
                ViewAction::make()->label('Vezi'),
                EditAction::make()
                    ->label('Editează')
                    ->visible(fn (ContractAmendment $record): bool => $record->status === ContractAmendmentStatus::Draft),

                Action::make('semneaza')
                    ->label('Marchează ca semnat')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->visible(fn (ContractAmendment $record): bool => $record->status === ContractAmendmentStatus::Draft)
                    ->action(function (ContractAmendment $record): void {
                        $record->update([
                            'status' => ContractAmendmentStatus::Semnat,
                            'content_snapshot' => $record->body,
                            'signed_date' => $record->signed_date ?? now()->toDateString(),
                        ]);

                        Notification::make()->title('Act adițional marcat ca semnat')->success()->send();
                    }),

                Action::make('anuleaza')
                    ->label('Anulează')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ContractAmendment $record): bool => $record->status !== ContractAmendmentStatus::Anulat)
                    ->action(function (ContractAmendment $record): void {
                        $record->update(['status' => ContractAmendmentStatus::Anulat]);
                        Notification::make()->title('Act adițional anulat')->warning()->send();
                    }),

                Action::make('descarca_pdf')
                    ->label('Descarcă PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ContractAmendment $record): bool => ! empty($record->pdf_path) && file_exists($record->pdf_path))
                    ->url(fn (ContractAmendment $record): string => route('contract-amendments.pdf', $record))
                    ->openUrlInNewTab(),

                DeleteAction::make()->label('Șterge'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractAmendments::route('/'),
            'create' => Pages\CreateContractAmendment::route('/create'),
            'edit' => Pages\EditContractAmendment::route('/{record}/edit'),
            'view' => Pages\ViewContractAmendment::route('/{record}'),
        ];
    }
}
