<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Observers;

use App\Enums\WorkOrderActivityTypeEnum;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Services\WorkOrderWebhookService;

class WorkOrderObserver
{
    public function updating(WorkOrder $workOrder): void
    {
        if (! $workOrder->isDirty('status')) {
            return;
        }

        $workOrder->activities()->create([
            'type' => WorkOrderActivityTypeEnum::StatusChange,
            'description' => sprintf(
                'Estado cambiado a %s',
                $workOrder->status->getLabel()
            ),
            'from_status' => $workOrder->getOriginal('status')?->value,
            'to_status' => $workOrder->status->value,
            'user_id' => auth()->id(),
            'metadata' => [],
        ]);

        app(WorkOrderWebhookService::class)->dispatch($workOrder, 'status_changed');
    }
}
