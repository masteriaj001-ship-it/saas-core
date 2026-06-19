<?php

declare(strict_types=1);

namespace App\Modules\Budget\Observers;

use App\Enums\BudgetStatusEnum;
use App\Modules\Budget\Models\Budget;
use App\Modules\Budget\Services\BudgetConversionService;

class BudgetObserver
{
    public function __construct(
        private readonly BudgetConversionService $conversionService,
    ) {}

    public function updated(Budget $budget): void
    {
        if (! $budget->isDirty('status')) {
            return;
        }

        if ($budget->status === BudgetStatusEnum::Approved) {
            $this->conversionService->convert($budget);
        }
    }
}
