<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages\CreateContact;
use App\Filament\Resources\ContactResource\Pages\EditContact;
use App\Filament\Resources\ContactResource\Pages\ListContacts;
use App\Models\Contact;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('Contactos');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Gestión');
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
                            ->options([
                                'client'   => __('Cliente'),
                                'supplier' => __('Proveedor'),
                                'employee' => __('Empleado'),
                                'other'    => __('Otro'),
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
                Section::make(__('Dirección'))
                    ->schema([
                        Textarea::make('address')
                            ->label(__('Dirección'))
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
                TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'client'   => 'info',
                        'supplier' => 'warning',
                        'employee' => 'success',
                        'other'    => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'client'   => __('Cliente'),
                        'supplier' => __('Proveedor'),
                        'employee' => __('Empleado'),
                        'other'    => __('Otro'),
                    }),
                TextColumn::make('tax_id')
                    ->label(__('RFC / ID Fiscal'))
                    ->searchable(),
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
                        'client'   => __('Cliente'),
                        'supplier' => __('Proveedor'),
                        'employee' => __('Empleado'),
                        'other'    => __('Otro'),
                    ]),
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
            'index'  => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'edit'   => EditContact::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->whereNull('deleted_at');
    }
}
