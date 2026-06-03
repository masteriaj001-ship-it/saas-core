<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Actions;

use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use Illuminate\Support\Facades\DB;

final class CreateWorkOrderAction
{
    public function execute(array $data): WorkOrder
    {
        $code = $this->generateCode();

        return DB::transaction(function () use ($data, $code) {
            return WorkOrder::create($data + ['code' => $code]);
        });
    }

    public function addItem(WorkOrder $workOrder, array $data): WorkOrderItem
    {
        return DB::transaction(function () use ($workOrder, $data) {
            return $workOrder->items()->create($data);
        });
    }

    private function generateCode(): string
    {
        $prefix = 'WO-';
        $last = WorkOrder::withTrashed()
            ->where('code', 'ilike', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(code, 4) AS INTEGER) DESC')
            ->first();

        if ($last) {
            $num = (int) substr($last->code, 3) + 1;
        } else {
            $num = 1;
        }

        return $prefix.str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
