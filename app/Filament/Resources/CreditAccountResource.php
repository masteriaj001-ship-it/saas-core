<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CreditAccountResource\Pages\CreateCreditAccount;
use App\Filament\Resources\CreditAccountResource\Pages\EditCreditAccount;
use App\Filament\Resources\CreditAccountResource\Pages\ListCreditAccounts;
use App\Modules\Facturacion\Models\CreditAccount;
use App\Modules\Facturacion\Services\CreditAccountService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CreditAccountResource extends Resource
{
    protected static ?string $model = CreditAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 12;

    public static function getNavigationLabel(): string
    {
        return __('Cuentas de Crédito');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Facturación');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Información del Cliente'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact.name')
                            ->label(__('Contacto'))
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('contact.phone')
                            ->label(__('Teléfono'))
                            ->disabled()
                            ->dehydrated(false),
                    ]),
                Section::make(__('Condiciones de Crédito'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('credit_limit')
                            ->label(__('Límite de Crédito'))
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('current_balance')
                            ->label(__('Balance Actual'))
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('payment_terms_days')
                            ->label(__('Días Plazo'))
                            ->numeric()
                            ->default(30)
                            ->required(),
                    ]),
                Section::make(__('Estado'))
                    ->schema([
                        TextInput::make('is_active')
                            ->label(__('Activo'))
                            ->boolean()
                            ->default(true),
                    ]),
                Section::make(__('Notas'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('Notas'))
                            ->rows(3),
                    ]),
                Section::make(__('Metadatos'))
                    ->schema([
                        KeyValue::make('metadata')
                            ->label(__('Metadatos'))
                            ->keyLabel(__('Clave'))
                            ->valueLabel(__('Valor')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact.name')
                    ->label(__('Cliente'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('credit_limit')
                    ->label(__('Límite'))
                    ->sortable()
                    ->money('COP'),
                TextColumn::make('current_balance')
                    ->label(__('Balance'))
                    ->sortable()
                    ->money('COP')
                    ->color(fn (CreditAccount $record): string => (float) $record->current_balance > 0 ? 'danger' : 'success'),
                TextColumn::make('payment_terms_days')
                    ->label(__('Plazo'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label(__('Estado'))
                    ->options([
                        1 => __('Activo'),
                        0 => __('Inactivo'),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('registerPayment')
                    ->label(__('Registrar Pago'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->form([
                        TextInput::make('amount')
                            ->label(__('Monto'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                        TextInput::make('reference')
                            ->label(__('Referencia'))
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->label(__('Notas'))
                            ->rows(2),
                    ])
                    ->action(function (CreditAccount $record, array $data): void {
                        app(CreditAccountService::class)->payment(
                            account: $record,
                            amount: $data['amount'],
                            reference: $data['reference'] ?? null,
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title(__('Pago registrado'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditAccounts::route('/'),
            'create' => CreateCreditAccount::route('/create'),
            'edit' => EditCreditAccount::route('/{record}/edit'),
        ];
    }
}
