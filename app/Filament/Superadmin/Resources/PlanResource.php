<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\PlanResource\Pages\CreatePlan;
use App\Filament\Superadmin\Resources\PlanResource\Pages\EditPlan;
use App\Filament\Superadmin\Resources\PlanResource\Pages\ListPlans;
use App\Modules\Plataforma\Models\Plan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\NumberInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('Planes');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Plataforma');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Información del Plan'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nombre interno'))
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        TextInput::make('label')
                            ->label(__('Etiqueta visible'))
                            ->required()
                            ->maxLength(100),
                        NumberInput::make('price_cop')
                            ->label(__('Precio COP'))
                            ->default(0)
                            ->minValue(0),
                        NumberInput::make('max_users')
                            ->label(__('Máximo usuarios'))
                            ->placeholder('Sin límite')
                            ->minValue(1),
                        NumberInput::make('max_work_orders')
                            ->label(__('Máximo OTs/mes'))
                            ->placeholder('Sin límite')
                            ->minValue(1),
                        KeyValue::make('features')
                            ->label(__('Características')),
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
                TextColumn::make('label')
                    ->label(__('Etiqueta'))
                    ->searchable(),
                TextColumn::make('price_cop')
                    ->label(__('Precio COP'))
                    ->formatStateUsing(fn ($state): string => '$'.number_format($state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('max_users')
                    ->label(__('Max Usuarios'))
                    ->placeholder('∞'),
                TextColumn::make('max_work_orders')
                    ->label(__('Max OTs/mes'))
                    ->placeholder('∞'),
                IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
