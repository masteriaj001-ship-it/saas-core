<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Tenant;
use App\Modules\Inventario\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contact_id' => Contact::factory(),
            'code' => fake()->unique()->bothify('SUP-####'),
            'trade_name' => fake()->company(),
            'payment_terms_days' => fake()->randomElement([15, 30, 45, 60]),
            'credit_limit' => fake()->randomFloat(2, 0, 5000000),
            'lead_time_days' => fake()->numberBetween(1, 30),
            'notes' => fake()->sentence(),
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
