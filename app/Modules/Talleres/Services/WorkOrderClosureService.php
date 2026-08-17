<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Services;

use App\Enums\WorkOrderStatusEnum;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderActivity;
use App\Modules\Talleres\Models\WorkOrderMedia;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkOrderClosureService
{
    private const CLOSURE_TRANSITIONS = [
        WorkOrderStatusEnum::InProgress->value => [
            WorkOrderStatusEnum::WorkDone,
            WorkOrderStatusEnum::EvidencePending,
        ],
        WorkOrderStatusEnum::EvidencePending->value => [
            WorkOrderStatusEnum::WorkDone,
        ],
        WorkOrderStatusEnum::WorkDone->value => [
            WorkOrderStatusEnum::WaitingClient,
        ],
        WorkOrderStatusEnum::WaitingClient->value => [
            WorkOrderStatusEnum::Completed,
            WorkOrderStatusEnum::NoPickup,
        ],
        WorkOrderStatusEnum::NoPickup->value => [
            WorkOrderStatusEnum::Completed,
        ],
    ];

    public function transition(WorkOrder $workOrder, WorkOrderStatusEnum $to): WorkOrder
    {
        if ($workOrder->isLegacyClosure()) {
            throw new RuntimeException('Las órdenes legacy no admiten transiciones del nuevo flujo de cierre.');
        }

        $allowed = self::CLOSURE_TRANSITIONS[$workOrder->status->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new RuntimeException(
                sprintf('Transición inválida: %s → %s', $workOrder->status->value, $to->value),
            );
        }

        if (in_array($to, [WorkOrderStatusEnum::WorkDone], true)) {
            $this->assertWorkDonePreconditions($workOrder);
        }

        if (in_array($to, [WorkOrderStatusEnum::Completed, WorkOrderStatusEnum::NoPickup], true)) {
            $this->assertSignaturePresent($workOrder);
        }

        return DB::transaction(function () use ($workOrder, $to): WorkOrder {
            $from = $workOrder->status;
            $workOrder->update(['status' => $to]);

            WorkOrderActivity::create([
                'work_order_id' => $workOrder->id,
                'type' => 'status_change',
                'description' => sprintf('Estado cambiado de %s a %s', $from->value, $to->value),
                'from_status' => $from->value,
                'to_status' => $to->value,
                'metadata' => [
                    'photos_version' => $this->photoVersions($workOrder),
                ],
            ]);

            return $workOrder->fresh();
        });
    }

    private function assertWorkDonePreconditions(WorkOrder $workOrder): void
    {
        if (! $workOrder->hasCompleteFinalChecklist()) {
            throw new RuntimeException('No se puede cerrar: quedan ítems pendientes en el checklist final.');
        }

        if (! $workOrder->hasBeforeAfterPhotos()) {
            throw new RuntimeException('No se puede cerrar: se requieren fotos antes y después del trabajo.');
        }
    }

    private function assertSignaturePresent(WorkOrder $workOrder): void
    {
        if (blank($workOrder->signature_hash) || $workOrder->signed_at === null) {
            throw new RuntimeException('Se requiere firma del cliente para completar el cierre.');
        }
    }

    private function photoVersions(WorkOrder $workOrder): array
    {
        return WorkOrderMedia::query()
            ->where('work_order_id', $workOrder->id)
            ->pluck('id')
            ->all();
    }
}
