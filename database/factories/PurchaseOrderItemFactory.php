<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\Tenant;
use App\Modules\Inventario\Models\PurchaseOrder;
use App\Modules\Inventario\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $unitCost = fake()->randomFloat(2, 1000, 500000);

        return [
            'tenant_id' => Tenant::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'item_id' => Item::factory(),
            'description' => fake()->words(3, true),
            'quantity' => $quantity,
            'received_quantity' => 0,
            'unit_cost' => $unitCost,
            'tax_rate' => 19,
            'tax_amount' => $unitCost * $quantity * 0.19,
            'subtotal' => $unitCost * $quantity,
            'batch_number' => fake()->optional()->bothify('LOT-####'),
            'expires_at' => fake()->optional()->dateTimeBetween('+6 months', '+2 years'),
            'metadata' => '{}',
        ];
    }
}
