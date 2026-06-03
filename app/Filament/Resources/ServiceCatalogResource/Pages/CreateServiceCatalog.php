<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceCatalogResource\Pages;

use App\Filament\Resources\ServiceCatalogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceCatalog extends CreateRecord
{
    protected static string $resource = ServiceCatalogResource::class;
}
