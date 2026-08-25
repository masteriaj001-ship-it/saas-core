<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Widgets;

use App\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivityWidget extends TableWidget
{
    protected static ?string $heading = 'Actividad Reciente';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::withoutGlobalScopes()
                    ->with('subscription.plan')
                    ->where('is_active', true)
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('Taller')),
                TextColumn::make('subscription.plan.label')
                    ->label(__('Plan'))
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('Registrado'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated([5, 10, 25]);
    }
}
