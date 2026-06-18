<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Services;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Models\Tenant;
use App\Modules\Facturacion\Models\Invoice;

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

        $invoice = Invoice::create([
            'document_type' => $documentType,
            'prefix' => $isPos ? 'POS' : 'FE',
            'sequence' => $isPos ? null : $sequence,
            'pos_sequence' => $isPos ? $sequence : null,
            'document_number' => $documentNumber,
            'status' => 'draft',
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

        return $invoice->load('items');
    }
}
