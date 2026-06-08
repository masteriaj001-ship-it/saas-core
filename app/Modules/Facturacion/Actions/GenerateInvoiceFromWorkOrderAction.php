<?php

declare(strict_types=1);

namespace App\Modules\Facturacion\Actions;

use App\Models\Tenant;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Services\InvoiceCodeGenerator;
use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

final class GenerateInvoiceFromWorkOrderAction
{
    public function execute(WorkOrder $workOrder): Invoice
    {
        return DB::transaction(function () use ($workOrder) {
            $code = app(InvoiceCodeGenerator::class)->next(
                $workOrder->tenant_id
            );

            $tenant = Tenant::find($workOrder->tenant_id);
            $taxRate = $tenant?->esResponsableIva() ? 19.00 : 0.00;

            $invoice = Invoice::create([
                'work_order_id' => $workOrder->id,
                'contact_id' => $workOrder->contact_id,
                'document_type' => 'invoice',
                'prefix' => $code['prefix'],
                'sequence' => $code['sequence'],
                'document_number' => $code['document_number'],
                'status' => 'draft',
                'issued_at' => now(),
            ]);

            foreach ($workOrder->items as $item) {
                $description = match ($item->type->value) {
                    'part' => $item->item?->name ?? 'Repuesto',
                    'service', 'labor' => $item->serviceCatalog?->name ?? 'Servicio',
                    default => 'Item',
                };

                $subtotal = $item->quantity * $item->unit_price;
                $taxAmount = round($subtotal * $taxRate / 100, 2);

                $invoice->items()->create([
                    'work_order_item_id' => $item->id,
                    'description' => $description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $subtotal,
                    'total' => $subtotal + $taxAmount,
                ]);
            }

            $invoice->subtotal = $invoice->items->sum('subtotal');
            $invoice->tax_total = $invoice->items->sum('tax_amount');
            $invoice->grand_total = $invoice->items->sum('total');
            $invoice->save();

            return $invoice;
        });
    }
}
