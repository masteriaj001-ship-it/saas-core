<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkOrderResource\Pages;

use App\Filament\Resources\WorkOrderResource;
use App\Modules\Talleres\Models\WorkOrder;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $prefix = 'WO-';
        $last = WorkOrder::withTrashed()
            ->where('code', 'ilike', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(code, 4) AS INTEGER) DESC')
            ->first();

        $num = $last ? (int) substr($last->code, 3) + 1 : 1;
        $data['code'] = $prefix.str_pad((string) $num, 4, '0', STR_PAD_LEFT);

        return $data;
    }
}
