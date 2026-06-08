<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 10);
        $unitPrice = fake()->randomFloat(2, 10, 500);
        $subtotal = $quantity * $unitPrice;
        $taxRate = 19.00;
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        return [
            'invoice_id' => Invoice::factory(),
            'work_order_item_id' => null,
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'subtotal' => $subtotal,
            'total' => $total,
        ];
    }
}
