<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethodEnum;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1, 1000);

        return [
            'invoice_id' => Invoice::factory(),
            'payment_method' => PaymentMethodEnum::Cash,
            'amount' => $amount,
            'cash_received' => $amount,
            'change_due' => 0,
            'reference' => null,
            'paid_at' => now(),
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attrs) => [
            'payment_method' => PaymentMethodEnum::Cash,
            'cash_received' => $attrs['cash_received'] ?? null,
        ]);
    }

    public function card(): static
    {
        return $this->state(fn (array $attrs) => [
            'payment_method' => PaymentMethodEnum::Card,
            'cash_received' => null,
            'change_due' => null,
        ]);
    }
}
