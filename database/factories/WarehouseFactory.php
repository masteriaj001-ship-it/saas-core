<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Inventario\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'WH-'.fake()->unique()->numberBetween(1, 999),
            'name' => fake()->city().' Warehouse',
            'address' => fake()->address(),
            'is_default' => false,
            'is_active' => true,
            'metadata' => '{}',
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attrs) => [
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attrs) => [
            'is_active' => false,
        ]);
    }
}
