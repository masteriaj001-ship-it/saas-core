<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Widgets;

use App\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ChurnRiskWidget extends TableWidget
{
    protected static ?string $heading = 'En Riesgo de Churn';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '200px';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::withoutGlobalScopes()
                    ->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('updated_at')
                            ->orWhere('updated_at', '<', now()->subDays(30));
                    })
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('Taller')),
                TextColumn::make('slug')
                    ->label(__('Slug')),
                TextColumn::make('updated_at')
                    ->label(__('Última actividad'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('Sin actividad')),
            ])
            ->paginated([5, 10, 25]);
    }
}
