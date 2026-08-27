<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Facturacion\Models\CreditAccount;
use App\Modules\Facturacion\Models\CreditTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    protected $model = CreditTransaction::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'credit_account_id' => CreditAccount::factory(),
            'type' => 'charge',
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'due_date' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'paid_at' => null,
            'invoice_id' => null,
            'reference' => fake()->optional(0.5)->sentence(3),
            'notes' => fake()->optional(0.3)->sentence(),
            'ip_address' => fake()->ipv4(),
            'metadata' => [],
            'created_by' => null,
        ];
    }

    public function charge(): static
    {
        return $this->state(fn () => [
            'type' => 'charge',
        ]);
    }

    public function payment(): static
    {
        return $this->state(fn () => [
            'type' => 'payment',
            'due_date' => null,
            'paid_at' => now(),
        ]);
    }

    public function chargeReverse(): static
    {
        return $this->state(fn () => [
            'type' => 'charge_reverse',
            'due_date' => null,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'type' => 'charge',
            'due_date' => fake()->dateTimeBetween('-2 months', '-1 day'),
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'paid_at' => now(),
        ]);
    }
}
