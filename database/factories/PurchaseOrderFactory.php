<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Inventario\Enums\PurchaseStatus;
use App\Modules\Inventario\Models\PurchaseOrder;
use App\Modules\Inventario\Models\Supplier;
use App\Modules\Inventario\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'code' => fake()->unique()->bothify('OC-####'),
            'status' => PurchaseStatus::DRAFT,
            'ordered_at' => null,
            'expected_at' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'received_at' => null,
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'notes' => fake()->sentence(),
            'metadata' => '{}',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => PurchaseStatus::DRAFT,
        ]);
    }

    public function ordered(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => PurchaseStatus::ORDERED,
            'ordered_at' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => PurchaseStatus::RECEIVED,
            'ordered_at' => now()->subWeek(),
            'received_at' => now(),
        ]);
    }
}
