<?php

declare(strict_types=1);

namespace App\Filament\Superadmin\Resources\GlobalWorkOrderResource\Pages;

use App\Filament\Superadmin\Resources\GlobalWorkOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListGlobalWorkOrders extends ListRecords
{
    protected static string $resource = GlobalWorkOrderResource::class;
}
