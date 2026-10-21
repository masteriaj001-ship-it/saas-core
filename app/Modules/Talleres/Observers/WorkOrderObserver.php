<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Observers;

use App\Enums\WorkOrderActivityTypeEnum;
use App\Enums\WorkOrderStatusEnum;
use App\Models\User;
use App\Modules\Inventario\Services\StockConsumptionService;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Notifications\WorkOrderApprovedNotification;
use App\Modules\Talleres\Notifications\WorkOrderRejectedNotification;
use App\Modules\Talleres\Services\WorkOrderWebhookService;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class WorkOrderObserver
{
    public function creating(WorkOrder $workOrder): void
    {
        $this->rejectBlockedContact($workOrder);
    }

    public function updating(WorkOrder $workOrder): void
    {
        if ($workOrder->isDirty('contact_id')) {
            $this->rejectBlockedContact($workOrder);
        }

        if (! $workOrder->isDirty('status')) {
            return;
        }

        $originalStatus = $workOrder->getOriginal('status');

        if ($workOrder->status === WorkOrderStatusEnum::InProgress && $originalStatus !== WorkOrderStatusEnum::InProgress) {
            $workOrder->actual_started_at = $workOrder->actual_started_at ?? now();
        }

        if ($workOrder->status === WorkOrderStatusEnum::WorkDone && $originalStatus !== WorkOrderStatusEnum::WorkDone) {
            $workOrder->actual_completed_at = $workOrder->actual_completed_at ?? now();
        }

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
        $this->consumeStockOnCompletion($workOrder, $originalStatus);
    }

    private function rejectBlockedContact(WorkOrder $workOrder): void
    {
        if ($workOrder->contact_id === null) {
            return;
        }

        $contact = $workOrder->contact()->first();

        if ($contact === null || $contact->blocked_until === null) {
            return;
        }

        if ($contact->blocked_until->isFuture()) {
            $message = sprintf(
                __('El contacto tiene un bloqueo activo hasta el %s. No se pueden crear órdenes para este contacto.'),
                $contact->blocked_until->format('Y-m-d H:i'),
            );

            FilamentNotification::make()
                ->danger()
                ->title($message)
                ->send();

            throw ValidationException::withMessages([
                'contact_id' => $message,
                'data.contact_id' => $message,
            ]);
        }
    }

    private function consumeStockOnCompletion(WorkOrder $workOrder, ?WorkOrderStatusEnum $originalStatus): void
    {
        if ($workOrder->status !== WorkOrderStatusEnum::Completed) {
            return;
        }

        if ($originalStatus === WorkOrderStatusEnum::Completed) {
            return;
        }

        app(StockConsumptionService::class)->consumeForWorkOrder($workOrder);
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
