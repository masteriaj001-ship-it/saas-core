<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Observers;

use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Services\CreditAccountService;

class InvoiceObserver
{
    public function __construct(
        private CreditAccountService $creditService,
    ) {}

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->contact_id) {
            return;
        }

        $oldStatus = $invoice->getOriginal('status');
        $newStatus = $invoice->status->value;
        $paymentMethod = $invoice->payment_method;

        if ($paymentMethod !== PaymentMethodEnum::Credit->value) {
            return;
        }

        if (
            in_array($newStatus, [InvoiceStatusEnum::Issued->value, InvoiceStatusEnum::Paid->value])
            && $oldStatus === InvoiceStatusEnum::Draft->value
        ) {
            $this->creditService->charge(
                invoice: $invoice,
                amount: (float) $invoice->grand_total,
                dueDate: $invoice->due_at?->toDateString(),
            );
        }

        if (
            $newStatus === InvoiceStatusEnum::Cancelled->value
            && in_array($oldStatus, [InvoiceStatusEnum::Issued->value, InvoiceStatusEnum::Paid->value])
        ) {
            $this->creditService->reverseCharge($invoice);
        }
    }
}
