<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Talleres\Models\ServiceCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceCatalogFactory extends Factory
{
    protected $model = ServiceCatalog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement([
                'Cambio de aceite',
                'Alineación y balanceo',
                'Revisión de frenos',
                'Diagnóstico general',
                'Cambio de llantas',
                'Afinación mayor',
                'Afinación menor',
                'Reparación de motor',
            ]),
            'description' => fake()->sentence(),
            'base_price' => fake()->numberBetween(500, 50000),
            'estimated_minutes' => fake()->randomElement([30, 45, 60, 90, 120, 180, 240]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attrs) => ['is_active' => false]);
    }
}
