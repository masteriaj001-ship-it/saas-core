<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Http\Requests\WorkOrders\CreateWorkOrderRequest;
use App\Http\Requests\WorkOrders\UpdateWorkOrderRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class WorkOrderService
{
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = WorkOrder::select(['id', 'code', 'title', 'status', 'priority', 'asset_id', 'contact_id', 'created_at'])
            ->with(['asset:id,name,code', 'contact:id,name'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['asset_id'])) {
            $query->where('asset_id', $filters['asset_id']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'ilike', "%{$filters['search']}%")
                  ->orWhere('code', 'ilike', "%{$filters['search']}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function create(CreateWorkOrderRequest $request): WorkOrder
    {
        $code = $this->generateCode();

        return WorkOrder::create(
            $request->validated() + ['code' => $code]
        );
    }

    public function update(string $id, UpdateWorkOrderRequest $request): WorkOrder
    {
        $workOrder = WorkOrder::findOrFail($id);
        $workOrder->update($request->validated());
        return $workOrder->fresh();
    }

    public function delete(string $id): void
    {
        WorkOrder::findOrFail($id)->delete();
    }

    public function addItem(string $workOrderId, array $data): WorkOrderItem
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);

        return $workOrder->items()->create($data);
    }

    public function removeItem(string $workOrderId, string $itemId): void
    {
        $workOrder = WorkOrder::findOrFail($workOrderId);
        $workOrder->items()->findOrFail($itemId)->delete();
    }

    private function generateCode(): string
    {
        $prefix = 'WO-';
        $last = WorkOrder::withTrashed()
            ->where('code', 'ilike', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(code, 4) AS INTEGER) DESC")
            ->first();

        if ($last) {
            $num = (int) substr($last->code, 3) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
