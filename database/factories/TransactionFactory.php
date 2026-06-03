<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contact_id' => Contact::factory(),
            'type' => 'sale',
            'invoice_number' => 'FAC-00001',
            'cufe' => null,
            'resolution_number' => null,
            'status' => 'draft',
            'subtotal' => 0,
            'total_tax' => 0,
            'total_retentions' => 0,
            'total_amount' => 0,
            'payment_method' => 'cash',
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sale',
            'payment_method' => fake()->randomElement(['cash', 'transfer', 'card', 'check', 'credit']),
        ]);
    }

    public function purchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'purchase',
            'payment_method' => fake()->randomElement(['transfer', 'check', 'credit']),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'cufe' => null,
        ]);
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'issued',
            'cufe' => 'CUFE-'.strtoupper((string) Str::uuid()),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cufe' => 'CUFE-'.strtoupper((string) Str::uuid()),
        ]);
    }
}
