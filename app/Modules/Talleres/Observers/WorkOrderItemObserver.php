<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Observers;

use App\Enums\WorkOrderStatusEnum;
use App\Modules\Talleres\Models\WorkOrderItem;

class WorkOrderItemObserver
{
    public function saved(WorkOrderItem $workOrderItem): void
    {
        $workOrder = $workOrderItem->workOrder;

        if ($workOrder === null) {
            return;
        }

        if ($workOrder->status !== WorkOrderStatusEnum::WaitingApproval) {
            return;
        }

        $workOrder->update(['status' => WorkOrderStatusEnum::Quoted]);
    }
}
