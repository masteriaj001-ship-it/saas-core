<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Observers;

use App\Enums\WorkOrderActivityTypeEnum;
use App\Enums\WorkOrderStatusEnum;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Notifications\WorkOrderApprovedNotification;
use App\Modules\Talleres\Notifications\WorkOrderRejectedNotification;
use App\Modules\Talleres\Services\WorkOrderWebhookService;
use Illuminate\Support\Facades\Notification;

class WorkOrderObserver
{
    public function updating(WorkOrder $workOrder): void
    {
        if (! $workOrder->isDirty('status')) {
            return;
        }

        $originalStatus = $workOrder->getOriginal('status');

        $workOrder->activities()->create([
            'type' => WorkOrderActivityTypeEnum::StatusChange,
            'description' => sprintf(
                'Estado cambiado a %s',
                $workOrder->status->getLabel()
            ),
            'from_status' => $originalStatus?->value,
            'to_status' => $workOrder->status->value,
            'user_id' => auth()->id(),
            'metadata' => [],
        ]);

        app(WorkOrderWebhookService::class)->dispatch($workOrder, 'status_changed');

        $this->dispatchApprovalNotifications($workOrder, $originalStatus);
    }

    private function dispatchApprovalNotifications(WorkOrder $workOrder, ?WorkOrderStatusEnum $originalStatus): void
    {
        if ($originalStatus !== WorkOrderStatusEnum::WaitingApproval) {
            return;
        }

        $newStatus = $workOrder->status;

        $url = route('filament.admin.resources.work-orders.edit', [
            'tenant' => $workOrder->tenant->slug,
            'record' => $workOrder,
        ]);

        $users = User::role(['owner', 'editor'])
            ->where('tenant_id', $workOrder->tenant_id)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        if ($newStatus === WorkOrderStatusEnum::Approved) {
            Notification::send($users, new WorkOrderApprovedNotification(
                workOrderCode: $workOrder->code,
                workOrderTitle: $workOrder->title,
                url: $url,
            ));
        } elseif ($newStatus === WorkOrderStatusEnum::Rejected) {
            Notification::send($users, new WorkOrderRejectedNotification(
                workOrderCode: $workOrder->code,
                workOrderTitle: $workOrder->title,
                url: $url,
                rejectionReason: $workOrder->metadata['rejection_reason'] ?? null,
            ));
        }
    }
}
