<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources\GlobalAssetResource\Pages;

use App\Filament\Superadmin\Resources\GlobalAssetResource;
use Filament\Resources\Pages\ListRecords;

class ListGlobalAssets extends ListRecords
{
    protected static string $resource = GlobalAssetResource::class;
}
