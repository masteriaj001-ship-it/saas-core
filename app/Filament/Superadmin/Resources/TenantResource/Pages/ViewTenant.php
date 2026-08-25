<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources\TenantResource\Pages;

use App\Filament\Superadmin\Resources\TenantResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('name')
                    ->label(__('Nombre')),
                TextEntry::make('slug')
                    ->label(__('Slug'))
                    ->copyable(),
                TextEntry::make('planName')
                    ->label(__('Plan'))
                    ->badge(),
                TextEntry::make('is_active')
                    ->label(__('Estado'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('Activo') : __('Inactivo'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextEntry::make('subscription.started_at')
                    ->label(__('Inicio de suscripción'))
                    ->dateTime(),
                TextEntry::make('subscription.expires_at')
                    ->label(__('Vencimiento'))
                    ->dateTime()
                    ->placeholder(__('Sin vencimiento')),
                TextEntry::make('created_at')
                    ->label(__('Registrado'))
                    ->dateTime(),
            ]);
    }
}
