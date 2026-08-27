<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkshopBayResource\Pages;

use App\Filament\Resources\WorkshopBayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkshopBays extends ListRecords
{
    protected static string $resource = WorkshopBayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
