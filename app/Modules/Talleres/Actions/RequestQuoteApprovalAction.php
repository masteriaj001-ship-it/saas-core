<?php

declare(strict_types=1);

namespace App\Modules\Talleres\Actions;

use App\Enums\WorkOrderStatusEnum;
use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Support\Facades\URL;

final readonly class RequestQuoteApprovalAction
{
    public function execute(WorkOrder $workOrder): string
    {
        $workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval]);

        return URL::temporarySignedRoute(
            'quote.approval.show',
            now()->addDays(7),
            ['workOrder' => $workOrder->id]
        );
    }
}
