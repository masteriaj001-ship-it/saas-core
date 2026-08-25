<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Superadmin\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Superadmin\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Superadmin\Resources\TenantResource\Pages\ViewTenant;
use App\Models\Tenant;
use App\Modules\Plataforma\Models\Plan;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Talleres');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Plataforma');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Información General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),
                        Toggle::make('is_active')
                            ->label(__('Activo'))
                            ->default(true),
                    ]),
                Section::make(__('Facturación'))
                    ->columns(1)
                    ->schema([
                        Toggle::make('settings.es_responsable_iva')
                            ->label(__('¿Responsable de IVA?'))
                            ->helperText(__('Activa si el taller está en régimen común y cobra IVA en sus facturas.'))
                            ->default(false),
                    ]),
                Section::make(__('Impresión'))
                    ->description(__('Configuración del Punto de Venta (impresora térmica y cajón).'))
                    ->columns(2)
                    ->schema([
                        Select::make('settings.pos_hardware.printer_driver')
                            ->label(__('Driver de impresión'))
                            ->options([
                                'window_print' => __('Navegador (window.print)'),
                                'esc_pos' => __('ESC/POS (red TCP)'),
                            ])
                            ->default('window_print')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('settings.pos_hardware.printer_host')
                            ->label(__('IP de la impresora'))
                            ->placeholder('192.168.1.50')
                            ->helperText(__('Solo usado con driver ESC/POS.'))
                            ->visible(fn (Get $get): bool => $get('settings.pos_hardware.printer_driver') === 'esc_pos'),
                        TextInput::make('settings.pos_hardware.printer_port')
                            ->label(__('Puerto'))
                            ->numeric()
                            ->default(9100)
                            ->minValue(1)
                            ->maxValue(65535)
                            ->visible(fn (Get $get): bool => $get('settings.pos_hardware.printer_driver') === 'esc_pos'),
                        Toggle::make('settings.pos_hardware.cash_drawer_after_payment')
                            ->label(__('Abrir cajón tras cobro'))
                            ->default(true)
                            ->live(),
                        Select::make('settings.pos_hardware.cash_drawer_channel')
                            ->label(__('Canal del cajón'))
                            ->options([
                                0 => '0',
                                1 => '1',
                                2 => '2',
                            ])
                            ->default(2)
                            ->helperText(__('Canal ESC/POS de pulses (habitual 2).'))
                            ->visible(fn (Get $get): bool => $get('settings.pos_hardware.cash_drawer_after_payment') === true),
                    ]),
                Section::make('Administrador del Taller')
                    ->description('Usuario que administrará este tenant')
                    ->columns(2)
                    ->visibleOn('create')
                    ->schema([
                        TextInput::make('admin_name')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('admin_email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique('users', 'email', ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('admin_password')
                            ->label('Contraseña')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->maxLength(255),
                        TextInput::make('admin_password_confirmation')
                            ->label('Confirmar contraseña')
                            ->password()
                            ->required()
                            ->same('admin_password')
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('planName')
                    ->label(__('Plan'))
                    ->badge(),
                IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('Registrado'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('subscription.plan.name')
                    ->label(__('Plan'))
                    ->options(fn (): array => Plan::pluck('label', 'name')->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->whereHas('subscription.plan', function ($q) use ($data) {
                            $q->where('plans.name', $data['value']);
                        });
                    }),
                SelectFilter::make('is_active')
                    ->label(__('Estado'))
                    ->options([
                        '1' => __('Activo'),
                        '0' => __('Inactivo'),
                    ]),
            ])
            ->actions([
                Action::make('impersonate')
                    ->label(__('Impersonar'))
                    ->icon('heroicon-o-user-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Impersonar tenant'))
                    ->modalDescription(fn (Tenant $record): string => __('Vas a entrar como').' '.$record->name.'. '.__('Todas tus acciones quedarán registradas.'))
                    ->modalSubmitActionLabel(__('Entrar como este taller'))
                    ->url(fn (Tenant $record): string => route('superadmin.impersonate', $record->id)),
                EditAction::make(),
                ViewAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading(__('Confirmar eliminación del taller'))
                    ->modalDescription(__('Esta acción eliminará permanentemente el taller y todos sus datos asociados. Esta operación no se puede deshacer.'))
                    ->modalSubmitActionLabel(__('Eliminar taller'))
                    ->successNotificationTitle(__('Taller eliminado')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading(__('Confirmar eliminación masiva'))
                        ->modalDescription(__('Se eliminarán permanentemente los talleres seleccionados y todos sus datos asociados.'))
                        ->modalSubmitActionLabel(__('Eliminar seleccionados')),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
            'view' => ViewTenant::route('/{record}'),
        ];
    }
}
