<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
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
        ];
    }
}
