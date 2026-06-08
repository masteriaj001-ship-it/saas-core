<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InspectionItemStatusEnum;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderInspectionFactory extends Factory
{
    protected $model = WorkOrderInspection::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'item_name' => $this->faker->word(),
            'status' => InspectionItemStatusEnum::Ok,
            'notes' => null,
            'photo_path' => null,
            'sort_order' => 0,
        ];
    }

    public function damaged(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InspectionItemStatusEnum::Damaged,
            'notes' => $this->faker->sentence(),
        ]);
    }

    public function missing(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InspectionItemStatusEnum::Missing,
            'notes' => null,
        ]);
    }
}
