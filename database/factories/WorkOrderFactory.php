<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Talleres\Models\Asset;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'asset_id' => Asset::factory(),
            'contact_id' => Contact::factory()->client(),
            'code' => fake()->unique()->bothify('WO-####'),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['draft', 'in_progress', 'completed', 'cancelled']),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attrs) => ['status' => 'draft']);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'in_progress',
            'started_at' => now()->subDays(fake()->numberBetween(1, 10)),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'completed',
            'started_at' => now()->subDays(fake()->numberBetween(10, 30)),
            'completed_at' => now()->subDays(fake()->numberBetween(1, 9)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attrs) => ['status' => 'cancelled']);
    }
}
