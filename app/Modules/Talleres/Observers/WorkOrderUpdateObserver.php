<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Observers;

use App\Modules\Talleres\Models\WorkOrder;

class WorkOrderObserver
{
    public function created(WorkOrder $workOrder): void
    {
        //
    }

    public function updated(WorkOrder $workOrder): void
    {
        //
    }

    public function creating(WorkOrder $workOrder): void
    {
        $workOrder->updated_by = auth()->id();
    }
}
