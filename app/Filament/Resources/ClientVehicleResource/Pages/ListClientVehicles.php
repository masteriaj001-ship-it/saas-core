<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientVehicleResource\Pages;

use App\Filament\Resources\ClientVehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientVehicles extends ListRecords
{
    protected static string $resource = ClientVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
