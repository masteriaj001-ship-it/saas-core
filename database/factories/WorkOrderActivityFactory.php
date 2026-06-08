<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkOrderActivityTypeEnum;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderActivityFactory extends Factory
{
    protected $model = WorkOrderActivity::class;

    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'user_id' => null,
            'type' => WorkOrderActivityTypeEnum::StatusChange,
            'description' => $this->faker->sentence(),
            'from_status' => null,
            'to_status' => null,
            'metadata' => [],
        ];
    }

    public function asNote(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => WorkOrderActivityTypeEnum::Note,
            'description' => 'Nota: '.$this->faker->sentence(),
            'from_status' => null,
            'to_status' => null,
        ]);
    }

    public function asAssignment(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => WorkOrderActivityTypeEnum::Assignment,
            'description' => 'Asignación: '.$this->faker->sentence(),
            'from_status' => null,
            'to_status' => null,
        ]);
    }
}
