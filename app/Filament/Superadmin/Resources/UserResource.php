<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('Usuarios');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Plataforma');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant.name')
                    ->label(__('Taller'))
                    ->sortable()
                    ->placeholder(__('Global')),
                TextColumn::make('is_superadmin')
                    ->label(__('Rol'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Superadmin' : 'Usuario')
                    ->color(fn (bool $state): string => $state ? 'danger' : 'gray'),
                TextColumn::make('created_at')
                    ->label(__('Registrado'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('tenant')
            ->withoutGlobalScopes();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
