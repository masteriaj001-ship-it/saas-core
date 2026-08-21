<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Services;

use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Support\Facades\Http;

final class WorkOrderWebhookService
{
    public function dispatch(WorkOrder $workOrder, string $event): void
    {
        $url = config('talleres.webhook_url');

        if (blank($url)) {
            return;
        }

        $timeout = (int) config('talleres.webhook_timeout', 5);

        Http::timeout($timeout)->post($url, [
            'event' => $event,
            'work_order_id' => $workOrder->id,
            'tenant_id' => $workOrder->tenant_id,
            'code' => $workOrder->code,
            'status' => $workOrder->status->value,
            'status_label' => $workOrder->status->getLabel(),
            'contact_id' => $workOrder->contact_id,
            'client_vehicle_id' => $workOrder->client_vehicle_id,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
