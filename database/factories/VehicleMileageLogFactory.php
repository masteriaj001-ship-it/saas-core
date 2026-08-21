<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\VehicleMileageLog;
use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleMileageLogFactory extends Factory
{
    protected $model = VehicleMileageLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'client_vehicle_id' => ClientVehicle::factory(),
            'work_order_id' => null,
            'mileage' => fake()->numberBetween(0, 200000),
            'recorded_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'notes' => fake()->sentence(),
        ];
    }

    public function forWorkOrder(): static
    {
        return $this->state(fn (array $attrs) => [
            'work_order_id' => WorkOrder::factory(),
        ]);
    }
}
