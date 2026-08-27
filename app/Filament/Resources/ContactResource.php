<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ContactRoleEnum;
use App\Filament\Resources\ContactResource\Pages\CreateContact;
use App\Filament\Resources\ContactResource\Pages\EditContact;
use App\Filament\Resources\ContactResource\Pages\ListContacts;
use App\Models\Contact;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Contactos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Gestión';
    }

    public static function getModelLabel(): string
    {
        return 'Contacto';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Contactos';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('General'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255),
                        Select::make('contact_type')
                            ->label(__('Tipo'))
                            ->required()
                            ->live()
                            ->options([
                                'client' => __('Cliente'),
                                'supplier' => __('Proveedor'),
                                'employee' => __('Empleado'),
                                'other' => __('Otro'),
                            ]),
                        TextInput::make('email')
                            ->label(__('Correo electrónico'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('Teléfono'))
                            ->maxLength(40),
                        TextInput::make('tax_id')
                            ->label(__('RFC / ID Fiscal'))
                            ->maxLength(50),
                    ]),
                Section::make(__('Roles'))
                    ->visible(fn (Get $get): bool => $get('contact_type') === 'employee')
                    ->schema([
                        Repeater::make('roles')
                            ->relationship()
                            ->simple(
                                Select::make('role_code')
                                    ->label(__('Rol'))
                                    ->options(ContactRoleEnum::class)
                                    ->required(),
                            )
                            ->addActionLabel(__('Agregar rol')),
                    ]),
                Section::make(__('Dirección'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('address')
                            ->label(__('Dirección'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->label(__('Ciudad'))
                            ->maxLength(100),
                    ]),
                Section::make(__('Identificación'))
                    ->columns(2)
                    ->schema([
                        Select::make('document_type')
                            ->label(__('Tipo de documento'))
                            ->options([
                                'CC' => __('Cédula de Ciudadanía'),
                                'NIT' => __('NIT'),
                                'CE' => __('Cédula de Extranjería'),
                                'PAS' => __('Pasaporte'),
                                'TI' => __('Tarjeta de Identidad'),
                            ]),
                        TextInput::make('document_number')
                            ->label(__('Número de documento'))
                            ->maxLength(30),
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
                TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'client' => 'info',
                        'supplier' => 'warning',
                        'employee' => 'success',
                        'other' => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'client' => __('Cliente'),
                        'supplier' => __('Proveedor'),
                        'employee' => __('Empleado'),
                        'other' => __('Otro'),
                    }),
                TextColumn::make('roles.role_code')
                    ->label(__('Roles'))
                    ->badge()
                    ->color(fn (string $state): string|array|null => ContactRoleEnum::tryFrom($state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn (string $state): string => ContactRoleEnum::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('tax_id')
                    ->label(__('RFC / ID Fiscal'))
                    ->searchable(),
                TextColumn::make('document_number')
                    ->label(__('Documento'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label(__('Correo'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('Teléfono'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('contact_type')
                    ->label(__('Tipo'))
                    ->options([
                        'client' => __('Cliente'),
                        'supplier' => __('Proveedor'),
                        'employee' => __('Empleado'),
                        'other' => __('Otro'),
                    ]),
                SelectFilter::make('role_code')
                    ->label(__('Rol'))
                    ->relationship('roles', 'role_code'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('edit_contacts')),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('delete_contacts')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('delete_contacts')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'edit' => EditContact::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }
}
