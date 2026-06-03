<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceCatalogResource\Pages;

use App\Filament\Resources\ServiceCatalogResource;
use Filament\Resources\Pages\EditRecord;

class EditServiceCatalog extends EditRecord
{
    protected static string $resource = ServiceCatalogResource::class;
}
