<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\Tenant;
use App\Modules\Talleres\Models\WorkshopBay;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkshopBayFactory extends Factory
{
    protected $model = WorkshopBay::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'location_id' => Location::factory(),
            'code' => fake()->unique()->bothify('BAY-##'),
            'name' => fake()->randomElement(['Bahía 1', 'Bahía 2', 'Bahía 3', 'Elevador 1', 'Elevador 2', 'Pintura 1', 'Diagnóstico 1']),
            'type' => fake()->randomElement(['standard', 'lift', 'paint', 'diagnostic']),
            'is_active' => true,
            'metadata' => '{}',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attrs) => [
            'is_active' => false,
        ]);
    }
}
