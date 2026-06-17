<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkOrderChecklistStatusEnum;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderChecklistItemFactory extends Factory
{
    protected $model = WorkOrderChecklistItem::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'task' => $this->faker->sentence(3),
            'status' => WorkOrderChecklistStatusEnum::Pending,
            'position' => 0,
            'notes' => null,
            'assigned_to' => null,
            'completed_by' => null,
            'completed_at' => null,
        ];
    }

    public function done(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkOrderChecklistStatusEnum::Done,
            'completed_at' => now(),
        ]);
    }

    public function ok(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkOrderChecklistStatusEnum::Ok,
            'completed_at' => now(),
        ]);
    }
}
