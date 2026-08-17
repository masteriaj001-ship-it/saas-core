<?php

declare(strict_types=1);

namespace App\Modules\Shared\Services\Print;

use App\Modules\Facturacion\Models\Invoice;

final class TicketRenderer
{
    /**
     * @return array{
     *     document_number: string,
     *     issued_at: string|null,
     *     items: list<array{description: string, quantity: int, total: float}>,
     *     subtotal: float,
     *     tax_total: float,
     *     grand_total: float,
     * }
     */
    public function render(Invoice $invoice): array
    {
        $invoice->loadMissing(['items']);

        $items = $invoice->items->map(fn ($item): array => [
            'description' => $item->description,
            'quantity' => (int) $item->quantity,
            'total' => (float) $item->total,
        ])->all();

        return [
            'document_number' => $invoice->document_number,
            'issued_at' => $invoice->issued_at?->format('d/m/Y H:i'),
            'items' => $items,
            'subtotal' => (float) $invoice->subtotal,
            'tax_total' => (float) $invoice->tax_total,
            'grand_total' => (float) $invoice->grand_total,
        ];
    }
}