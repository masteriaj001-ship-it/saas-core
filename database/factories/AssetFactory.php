<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Talleres\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $types = ['vehicle', 'equipment', 'phones', 'computers', 'space'];

        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'code' => 'ASSET-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'asset_type' => fake()->randomElement($types),
            'status' => fake()->randomElement(['active', 'active', 'active', 'maintenance', 'disposed']),
            'metadata' => '{}',
            'acquired_at' => fake()->dateTimeBetween('-5 years', 'now'),
        ];
    }

    public function vehicle(): static
    {
        return $this->state(fn (array $attrs) => [
            'asset_type' => 'vehicle',
            'brand' => fake()->randomElement(['Toyota', 'Honda', 'Nissan', 'Ford', 'Chevrolet', 'Mazda']),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2000, (int) now()->format('Y')),
            'plate' => strtoupper(fake()->bothify('???-####')),
            'vin' => strtoupper(fake()->bothify('1HG?????????????')),
            'fuel_type' => fake()->randomElement(['gasoline', 'diesel', 'hybrid']),
            'current_mileage' => fake()->numberBetween(0, 200000),
            'color' => fake()->safeColorName(),
        ]);
    }

    public function equipment(): static
    {
        return $this->state(fn (array $attrs) => [
            'asset_type' => 'equipment',
        ]);
    }

    public function phones(): static
    {
        return $this->state(fn (array $attrs) => [
            'asset_type' => 'phones',
            'status' => 'active',
        ]);
    }

    public function computers(): static
    {
        return $this->state(fn (array $attrs) => [
            'asset_type' => 'computers',
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'maintenance',
        ]);
    }

    public function disposed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'disposed',
        ]);
    }
}
