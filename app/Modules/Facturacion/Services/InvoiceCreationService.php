<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Services;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Tenant;
use App\Modules\Facturacion\Exceptions\PaymentExceedsBalanceException;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Models\InvoicePayment;
use Illuminate\Support\Facades\DB;

class InvoiceCreationService
{
    public function __construct(
        private readonly DocumentSequenceService $sequenceService,
    ) {}

    public function create(
        Tenant $tenant,
        InvoiceDocumentTypeEnum $documentType,
        array $data,
    ): Invoice {
        $type = $documentType->value;
        $sequence = $this->sequenceService->nextSequence($tenant, $type);
        $documentNumber = $this->sequenceService->formatNumber($tenant, $type, $sequence);

        $isPos = $documentType === InvoiceDocumentTypeEnum::Pos;
        $taxRate = $isPos ? 0 : ($tenant->esResponsableIva() ? 19 : 0);

        $subtotal = 0;
        $taxTotal = 0;

        $itemsData = [];
        foreach ($data['items'] as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount'] ?? 0);

            $lineSubtotal = $qty * $price;
            $lineDiscount = $lineSubtotal * ($discount / 100);
            $lineNet = $lineSubtotal - $lineDiscount;
            $lineTax = $lineNet * ($taxRate / 100);
            $lineTotal = $lineNet + $lineTax;

            $subtotal += $lineNet;
            $taxTotal += $lineTax;

            $itemsData[] = [
                'description' => $item['description'],
                'quantity' => $qty,
                'unit_price' => $price,
                'discount' => $discount,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'subtotal' => $lineNet,
                'total' => $lineTotal,
            ];
        }

        $grandTotal = $subtotal + $taxTotal;

        $payment = $data['payment'] ?? null;

        $invoice = Invoice::create([
            'document_type' => $documentType,
            'prefix' => $isPos ? 'POS' : 'FE',
            'sequence' => $isPos ? null : $sequence,
            'pos_sequence' => $isPos ? $sequence : null,
            'document_number' => $documentNumber,
            'status' => $payment !== null ? InvoiceStatusEnum::Paid->value : InvoiceStatusEnum::Draft->value,
            'issued_at' => now(),
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($itemsData as $itemData) {
            $invoice->items()->create($itemData);
        }

        if ($payment !== null) {
            $this->registerPayment($invoice, $payment);
        }

        return $invoice->load('items', 'payments');
    }

    public function registerPayment(Invoice $invoice, array $payment): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $payment) {
            $amount = round((float) ($payment['amount'] ?? 0), 2);
            $balance = $invoice->balanceDue();

            if ($amount > $balance + 0.01) {
                throw new PaymentExceedsBalanceException($invoice, $amount, $balance);
            }

            $method = PaymentMethodEnum::tryFrom($payment['method']) ?? PaymentMethodEnum::Cash;
            $cashReceived = $payment['cash_received'] ?? null;
            $changeDue = $method === PaymentMethodEnum::Cash && $cashReceived !== null
                ? round(max(0, (float) $cashReceived - $amount), 2)
                : null;

            $invoicePayment = $invoice->payments()->create([
                'payment_method' => $method->value,
                'amount' => $amount,
                'cash_received' => $cashReceived,
                'change_due' => $changeDue,
                'reference' => $payment['reference'] ?? null,
                'paid_at' => now(),
            ]);

            if ($invoice->balanceDue() <= 0) {
                $invoice->update(['status' => InvoiceStatusEnum::Paid->value]);
            }

            return $invoicePayment;
        });
    }
}
