<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Superadmin\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Superadmin\Resources\TenantResource\Pages\ListTenants;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                        Select::make('plan')
                            ->label(__('Plan'))
                            ->required()
                            ->default('basic')
                            ->options([
                                'basic' => __('Basic'),
                                'premium' => __('Premium'),
                                'enterprise' => __('Enterprise'),
                            ]),
                        Toggle::make('is_active')
                            ->label(__('Activo'))
                            ->default(true),
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
                TextColumn::make('plan')
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
                SelectFilter::make('plan')
                    ->label(__('Plan'))
                    ->options([
                        'basic' => __('Basic'),
                        'premium' => __('Premium'),
                        'enterprise' => __('Enterprise'),
                    ]),
                SelectFilter::make('is_active')
                    ->label(__('Estado'))
                    ->options([
                        '1' => __('Activo'),
                        '0' => __('Inactivo'),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
