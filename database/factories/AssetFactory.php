<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        $types = ["phones", "computers", "vehicles"];

        return [
            "tenant_id" => Tenant::factory(),
            "name" => fake()->company(),
            "code" => "ASSET-" . str_pad((string) fake()->unique()->numberBetween(1, 999), 3, "0", STR_PAD_LEFT),
            "asset_type" => fake()->randomElement($types),
            "status" => fake()->randomElement(["active", "active", "active", "maintenance", "disposed"]),
            "metadata" => "{}",
            "acquired_at" => fake()->dateTimeBetween("-5 years", "now"),
        ];
    }

    public function phones(): static
    {
        return $this->state(fn (array $attrs) => [
            "asset_type" => "phones",
            "status" => "active",
        ]);
    }

    public function computers(): static
    {
        return $this->state(fn (array $attrs) => [
            "asset_type" => "computers",
        ]);
    }

    public function vehicles(): static
    {
        return $this->state(fn (array $attrs) => [
            "asset_type" => "vehicles",
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attrs) => [
            "status" => "maintenance",
        ]);
    }

    public function disposed(): static
    {
        return $this->state(fn (array $attrs) => [
            "status" => "disposed",
        ]);
    }
}
