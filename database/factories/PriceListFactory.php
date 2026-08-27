<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Inventario\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->randomElement(['Lista General', 'Lista Mayorista', 'Lista VIP', 'Lista Flota', 'Lista Promocional']),
            'description' => fake()->sentence(),
            'is_default' => false,
            'is_active' => true,
            'metadata' => '{}',
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attrs) => [
            'is_default' => true,
            'name' => 'Lista General',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attrs) => [
            'is_active' => false,
        ]);
    }
}
