<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientVehicleResource\Pages;

use App\Filament\Resources\ClientVehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClientVehicle extends EditRecord
{
    protected static string $resource = ClientVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
