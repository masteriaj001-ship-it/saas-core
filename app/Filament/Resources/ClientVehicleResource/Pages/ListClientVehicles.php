<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientVehicleResource\Pages;

use App\Filament\Resources\ClientVehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientVehicles extends ListRecords
{
    protected static string $resource = ClientVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
