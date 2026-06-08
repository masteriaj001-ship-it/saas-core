<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkOrderItemTypeEnum;
use App\Models\Item;
use App\Modules\Talleres\Models\ServiceCatalog;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderItemFactory extends Factory
{
    protected $model = WorkOrderItem::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'item_id' => Item::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->numberBetween(1000, 100000),
            'type' => WorkOrderItemTypeEnum::Part->value,
        ];
    }

    public function asService(): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => WorkOrderItemTypeEnum::Service->value,
            'item_id' => null,
            'service_catalog_id' => ServiceCatalog::factory(),
        ]);
    }

    public function asLabor(): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => WorkOrderItemTypeEnum::Labor->value,
            'item_id' => null,
            'service_catalog_id' => ServiceCatalog::factory(),
        ]);
    }
}
