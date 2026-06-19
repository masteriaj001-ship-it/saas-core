<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BudgetStatusEnum;
use App\Models\Tenant;
use App\Modules\Budget\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => fake()->unique()->bothify('BGT-######'),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->email(),
            'vehicle_data' => [
                'make' => fake()->randomElement(['Mazda', 'Toyota', 'Chevrolet', 'Renault', 'Nissan']),
                'model' => fake()->randomElement(['Mazda 3', 'Corolla', 'Spark GT', 'Sandero', 'March']),
                'plate' => strtoupper(fake()->bothify('???-###')),
                'year' => (string) fake()->numberBetween(2010, 2024),
                'color' => fake()->colorName(),
            ],
            'status' => BudgetStatusEnum::Draft->value,
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => BudgetStatusEnum::Sent->value,
            'sent_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => BudgetStatusEnum::Approved->value,
            'sent_at' => now()->subDay(),
            'approved_at' => now(),
            'responded_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => BudgetStatusEnum::Rejected->value,
            'sent_at' => now()->subDay(),
            'rejected_at' => now(),
            'responded_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
