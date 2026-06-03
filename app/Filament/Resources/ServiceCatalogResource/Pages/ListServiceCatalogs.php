<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceCatalogResource\Pages;

use App\Filament\Resources\ServiceCatalogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceCatalogs extends ListRecords
{
    protected static string $resource = ServiceCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
