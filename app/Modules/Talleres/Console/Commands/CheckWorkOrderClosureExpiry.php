<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Console\Commands;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderActivity;
use App\Modules\Talleres\Notifications\WorkOrderBreachNotification;
use App\Modules\Talleres\Notifications\WorkOrderPickupAlertNotification;
use App\Modules\Talleres\Services\WorkOrderClosureService;
use App\Services\TenantManager;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CheckWorkOrderClosureExpiry extends Command
{
    private const BREACH_AFTER_HOURS = 72;

    private const PICKUP_ALERT_AFTER_HOURS = 48;

    protected $signature = 'work-orders:check-closure-expiry {--tenant= : Limit the sweep to a specific tenant ID}';

    protected $description = 'Move waiting_client/no_pickup work orders past the closure window to breach and block their contacts';

    public function handle(): int
    {
        $tenantManager = app(TenantManager::class);

        $tenantId = $this->option('tenant');

        $tenantIds = $tenantId !== null && $tenantId !== ''
            ? [$tenantId]
            : Tenant::query()->orderBy('id')->pluck('id')->all();

        $tenantFailures = 0;

        foreach ($tenantIds as $id) {
            try {
                $tenantManager->setTenantContext($id);
                $this->processTenant($id);
            } catch (Throwable $exception) {
                $tenantFailures++;

                Log::error('Sweep de cierre: fallo al procesar el tenant.', [
                    'tenant_id' => $id,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                $tenantManager->clearTenantContext();
            }
        }

        return $tenantFailures > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function processTenant(string $tenantId): void
    {
        $workOrders = WorkOrder::query()
            ->whereIn('status', [
                WorkOrderStatusEnum::WaitingClient->value,
                WorkOrderStatusEnum::NoPickup->value,
            ])
            ->lazyById();

        foreach ($workOrders as $workOrder) {
            try {
                $this->processOrder($workOrder, $tenantId);
            } catch (Throwable $exception) {
                Log::warning('Sweep de cierre: orden saltada.', [
                    'work_order_id' => $workOrder->id,
                    'tenant_id' => $tenantId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function processOrder(WorkOrder $workOrder, string $tenantId): void
    {
        $windowStartedAt = $this->resolveWindowStart($workOrder);

        if ($windowStartedAt->lte(now()->subHours(self::BREACH_AFTER_HOURS))) {
            $this->breach($workOrder, $tenantId);

            return;
        }

        if ($workOrder->status === WorkOrderStatusEnum::NoPickup
            && $windowStartedAt->lte(now()->subHours(self::PICKUP_ALERT_AFTER_HOURS))
            && blank($workOrder->settings['pickup_alert_sent_at'] ?? null)
        ) {
            $this->sendPickupAlert($workOrder, $tenantId);
        }
    }

    private function resolveWindowStart(WorkOrder $workOrder): CarbonInterface
    {
        $stamp = $workOrder->settings['closure_window_started_at'] ?? null;

        if (filled($stamp)) {
            return Carbon::parse($stamp);
        }

        $activity = WorkOrderActivity::query()
            ->where('work_order_id', $workOrder->id)
            ->where('to_status', $workOrder->status->value)
            ->orderByDesc('created_at')
            ->first();

        if ($activity !== null) {
            Log::info('Sweep de cierre: ventana resuelta por fila de activity.', [
                'work_order_id' => $workOrder->id,
                'fallback' => 'activity',
            ]);

            return $activity->created_at;
        }

        Log::info('Sweep de cierre: ventana resuelta por created_at de la orden.', [
            'work_order_id' => $workOrder->id,
            'fallback' => 'created_at',
        ]);

        return $workOrder->created_at;
    }

    private function breach(WorkOrder $workOrder, string $tenantId): void
    {
        try {
            DB::transaction(function () use ($workOrder, $tenantId): void {
                app(WorkOrderClosureService::class)->transition($workOrder, WorkOrderStatusEnum::Breach);

                $contact = $workOrder->contact;

                if ($contact !== null) {
                    $contact->update(['blocked_until' => now()->addDays(7)]);
                }

                $this->notifyBreach($workOrder, $tenantId);
            });
        } catch (Throwable $exception) {
            if ($workOrder->isLegacyClosure()) {
                Log::warning('Sweep de cierre: orden saltada.', [
                    'work_order_id' => $workOrder->id,
                    'tenant_id' => $tenantId,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }

            Log::warning('Sweep de cierre: breach revertida sin notificar (transacción completa); la orden sigue elegible y se reintentará.', [
                'work_order_id' => $workOrder->id,
                'tenant_id' => $tenantId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendPickupAlert(WorkOrder $workOrder, string $tenantId): void
    {
        $users = User::role(['owner', 'editor'])
            ->where('tenant_id', $tenantId)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($workOrder): void {
            $settings = $workOrder->settings ?? [];
            $settings['pickup_alert_sent_at'] = now()->toIso8601String();
            $workOrder->update(['settings' => $settings]);
        });

        Notification::send($users, new WorkOrderPickupAlertNotification(
            workOrderCode: $workOrder->code,
            workOrderTitle: $workOrder->title,
            url: route('filament.admin.resources.work-orders.edit', [
                'tenant' => $workOrder->tenant->slug,
                'record' => $workOrder,
            ]),
        ));
    }

    private function notifyBreach(WorkOrder $workOrder, string $tenantId): void
    {
        $users = User::role(['owner', 'editor'])
            ->where('tenant_id', $tenantId)
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new WorkOrderBreachNotification(
            workOrderCode: $workOrder->code,
            workOrderTitle: $workOrder->title,
            url: route('filament.admin.resources.work-orders.edit', [
                'tenant' => $workOrder->tenant->slug,
                'record' => $workOrder,
            ]),
        ));
    }
}
