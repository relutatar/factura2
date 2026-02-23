<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Company;
use App\Models\CompanyType;
use App\Services\AnafService;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationGroup = 'Configurare';
    protected static ?string $navigationLabel = 'Companii';
    protected static ?string $modelLabel = 'Companie';
    protected static ?string $pluralModelLabel = 'Companii';
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informații generale')
                ->schema([
                    TextInput::make('name')
                        ->label('Denumire companie')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),

                    Select::make('company_type_id')
                        ->label('Tip companie')
                        ->options(CompanyType::selectOptions())
                        ->searchable()
                        ->nullable()
                        ->columnSpan(1),

                    TextInput::make('invoice_prefix')
                        ->label('Prefix facturi')
                        ->required()
                        ->maxLength(10)
                        ->helperText('Ex: NOD, PBM')
                        ->columnSpan(1),
                ])->columns(4),

            Section::make('Date juridice')
                ->schema([
                    TextInput::make('cif')
                        ->label('CIF')
                        ->required()
                        ->maxLength(50)
                        ->live()
                        ->suffixAction(
                            FormAction::make('anaf_lookup')
                                ->label('Caută în ANAF')
                                ->icon('heroicon-o-magnifying-glass')
                                ->action(function (Get $get, Set $set) {
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
                                    $set('address', $result['adresa']     ?? '');
                                    $set('city',    $result['localitate'] ?? '');
                                    $set('county',  $result['judet']      ?? '');

                                    Notification::make()
                                        ->title('Date preluate din ANAF cu succes')
                                        ->success()
                                        ->send();
                                })
                        ),

                    TextInput::make('reg_com')
                        ->label('Nr. Reg. Comerțului')
                        ->maxLength(50),
                ])->columns(2),

            Section::make('Adresă')
                ->schema([
                    TextInput::make('address')
                        ->label('Stradă / Adresă')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('city')
                        ->label('Localitate')
                        ->maxLength(100),

                    TextInput::make('county')
                        ->label('Județ')
                        ->maxLength(100),
                ])->columns(2),

            Section::make('Date bancare')
                ->schema([
                    TextInput::make('iban')
                        ->label('IBAN')
                        ->maxLength(34),

                    TextInput::make('bank')
                        ->label('Bancă')
                        ->maxLength(100),
                ])->columns(2),

            Section::make('e-Factura ANAF')
                ->description('Configurare autentificare ANAF SPV pentru trimiterea facturilor electronice.')
                ->schema([
                    TextInput::make('efactura_cif')
                        ->label('CIF pentru ANAF')
                        ->helperText('CIF-ul utilizat la autentificarea în SPV ANAF.')
                        ->maxLength(50),

                    Toggle::make('efactura_test_mode')
                        ->label('Mod test ANAF')
                        ->helperText('Activat = trimitere în sandbox ANAF, fără efecte legale.')
                        ->default(true),

                    FileUpload::make('efactura_certificate_path')
                        ->label('Certificat digital (.p12 / .pfx)')
                        ->acceptedFileTypes(['application/x-pkcs12', '.p12', '.pfx'])
                        ->directory('certificates')
                        ->visibility('private')
                        ->columnSpanFull(),

                    TextInput::make('efactura_certificate_password')
                        ->label('Parolă certificat')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])->columns(2)->collapsible(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if ($user?->hasRole('superadmin')) {
            return parent::getEloquentQuery()->withoutGlobalScopes();
        }

        $accessibleIds = $user?->companies()->pluck('companies.id')->all() ?? [];

        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->whereIn('id', $accessibleIds);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Denumire')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('companyType.name')
                    ->label('Tip')
                    ->badge()
                    ->color(fn (?Company $record) => $record?->companyType?->color ?? 'gray'),

                TextColumn::make('cif')
                    ->label('CIF')
                    ->searchable(),

                TextColumn::make('city')
                    ->label('Localitate')
                    ->searchable(),

                TextColumn::make('invoice_prefix')
                    ->label('Prefix')
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make()->label('Editează'),
                DeleteAction::make()->label('Șterge'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit'   => Pages\EditCompany::route('/{record}/edit'),
        ];
    }

    // ─── Authorization ──────────────────────────────────────────────────────────────────

    /** Only superadmin can create new companies. */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('superadmin') ?? false;
    }

    /** Superadmin can edit any company; regular user can only edit their own. */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if ($user?->hasRole('superadmin')) {
            return true;
        }
        return $user?->canAccessCompany($record->id) ?? false;
    }

    /** Only superadmin can delete companies. */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('superadmin') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('superadmin') ?? false;
    }
}
