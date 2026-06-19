<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkOrderStatusEnum;
use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteApprovalController extends Controller
{
    public function show(WorkOrder $workOrder): View
    {
        if ($workOrder->status === WorkOrderStatusEnum::Approved) {
            return $this->approved($workOrder);
        }

        if ($workOrder->status === WorkOrderStatusEnum::Rejected) {
            return $this->rejected($workOrder);
        }

        if ($workOrder->status !== WorkOrderStatusEnum::WaitingApproval) {
            abort(410, __('Este presupuesto ya no está disponible.'));
        }

        return view('presupuesto.show', [
            'workOrder' => $workOrder->load('items'),
        ]);
    }

    public function approve(WorkOrder $workOrder): RedirectResponse
    {
        if ($workOrder->status !== WorkOrderStatusEnum::WaitingApproval) {
            abort(409, __('Este presupuesto ya fue procesado.'));
        }

        $workOrder->update([
            'status' => WorkOrderStatusEnum::Approved,
            'approval_at' => now(),
            'approval_channel' => 'web',
        ]);

        return redirect()->route('quote.approval.approved', ['workOrder' => $workOrder]);
    }

    public function reject(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        if ($workOrder->status !== WorkOrderStatusEnum::WaitingApproval) {
            abort(409, __('Este presupuesto ya fue procesado.'));
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $workOrder->update([
            'status' => WorkOrderStatusEnum::Rejected,
            'approval_channel' => 'web',
            'metadata' => array_merge($workOrder->metadata ?? [], [
                'rejection_reason' => $validated['reason'] ?? null,
            ]),
        ]);

        return redirect()->route('quote.approval.rejected', ['workOrder' => $workOrder]);
    }

    public function approved(WorkOrder $workOrder): View
    {
        if ($workOrder->status !== WorkOrderStatusEnum::Approved) {
            abort(410);
        }

        return view('presupuesto.approved', ['workOrder' => $workOrder]);
    }

    public function rejected(WorkOrder $workOrder): View
    {
        if ($workOrder->status !== WorkOrderStatusEnum::Rejected) {
            abort(410);
        }

        return view('presupuesto.rejected', ['workOrder' => $workOrder]);
    }
}
