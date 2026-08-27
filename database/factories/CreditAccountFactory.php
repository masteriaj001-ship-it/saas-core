<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Tenant;
use App\Modules\Facturacion\Models\CreditAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditAccount>
 */
class CreditAccountFactory extends Factory
{
    protected $model = CreditAccount::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contact_id' => Contact::factory(),
            'credit_limit' => fake()->randomFloat(2, 100000, 5000000),
            'current_balance' => 0,
            'payment_terms_days' => fake()->randomElement([15, 30, 45, 60]),
            'is_active' => true,
            'notes' => fake()->optional(0.3)->sentence(),
            'metadata' => [],
            'created_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn () => [
            'current_balance' => $balance,
        ]);
    }

    public function noLimit(): static
    {
        return $this->state(fn () => [
            'credit_limit' => 0,
        ]);
    }
}
