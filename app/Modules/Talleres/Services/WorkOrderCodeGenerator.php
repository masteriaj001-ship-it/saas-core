<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Services;

use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

final class WorkOrderCodeGenerator
{
    private const PREFIX = 'WO-';

    public function next(): string
    {
        return DB::transaction(function () {
            $last = WorkOrder::withTrashed()
                ->where('code', 'ilike', self::PREFIX.'%')
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(code, 4) AS INTEGER) DESC')
                ->first();

            $num = $last ? (int) substr($last->code, 3) + 1 : 1;

            return self::PREFIX.str_pad((string) $num, 4, '0', STR_PAD_LEFT);
        });
    }
}
