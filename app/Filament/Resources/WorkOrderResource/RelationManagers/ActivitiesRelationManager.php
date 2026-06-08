<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkOrderResource\RelationManagers;

use App\Enums\WorkOrderActivityTypeEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (WorkOrderActivityTypeEnum $state): string => $state->getLabel()),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sistema'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
