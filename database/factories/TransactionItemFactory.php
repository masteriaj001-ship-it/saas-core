<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionItemFactory extends Factory
{
    protected $model = TransactionItem::class;

    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 10);
        $unitPrice = fake()->randomFloat(2, 10000, 500000);
        $taxRate = fake()->randomElement([0, 5, 19]);
        $subtotal = $qty * $unitPrice;
        $taxAmount = $subtotal * ($taxRate / 100);
        $discountAmount = fake()->optional(0.3)->randomFloat(2, 0, $subtotal * 0.2) ?? 0;

        return [
            'tenant_id' => Tenant::factory(),
            'transaction_id' => Transaction::factory(),
            'item_id' => Item::factory(),
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_item_amount' => $subtotal + $taxAmount - $discountAmount,
        ];
    }
}
