<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        $itemTypes = ['spare', 'product', 'service', 'raw_material'];
        $units = ['unit', 'kg', 'lt', 'm', 'piece'];
        $price = fake()->randomFloat(2, 1000, 500000);

        return [
            'tenant_id' => Tenant::factory(),
            'sku' => 'ITEM-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => fake()->word().' '.fake()->word(),
            'description' => fake()->sentence(),
            'item_type' => fake()->randomElement($itemTypes),
            'unit' => fake()->randomElement($units),
            'price' => $price,
            'cost' => round($price * fake()->randomFloat(2, 0.6, 0.8), 2),
            'stock' => fake()->numberBetween(0, 100),
            'min_stock' => fake()->numberBetween(5, 20),
            'metadata' => '{}',
        ];
    }

    public function spare(): static
    {
        return $this->state(fn (array $attrs) => [
            'item_type' => 'spare',
            'unit' => 'piece',
        ]);
    }

    public function product(): static
    {
        return $this->state(fn (array $attrs) => [
            'item_type' => 'product',
            'unit' => 'unit',
        ]);
    }

    public function service(): static
    {
        return $this->state(fn (array $attrs) => [
            'item_type' => 'service',
            'unit' => 'unit',
            'stock' => 999,
            'min_stock' => 1,
        ]);
    }

    public function rawMaterial(): static
    {
        return $this->state(fn (array $attrs) => [
            'item_type' => 'raw_material',
            'unit' => 'kg',
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attrs) => [
            'stock' => fake()->numberBetween(0, 3),
            'min_stock' => fake()->numberBetween(10, 30),
        ]);
    }
}
