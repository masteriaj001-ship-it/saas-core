<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            "tenant_id" => Tenant::factory(),
            "contact_type" => fake()->randomElement(["client", "supplier", "employee", "other"]),
            "name" => fake()->name(),
            "email" => fake()->unique()->safeEmail(),
            "phone" => fake()->phoneNumber(),
            "tax_id" => (string) fake()->unique()->numberBetween(100000000, 999999999),
            "address" => fake()->address(),
            "metadata" => "{}",
        ];
    }

    public function client(): static
    {
        return $this->state(fn (array $attrs) => [
            "contact_type" => "client",
        ]);
    }

    public function supplier(): static
    {
        return $this->state(fn (array $attrs) => [
            "contact_type" => "supplier",
        ]);
    }

    public function employee(): static
    {
        return $this->state(fn (array $attrs) => [
            "contact_type" => "employee",
        ]);
    }
}
