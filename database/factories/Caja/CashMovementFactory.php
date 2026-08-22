<?php

declare(strict_types=1);

namespace Database\Factories\Caja;

use App\Modules\Caja\Models\CashMovement;
use App\Modules\Talleres\Models\WorkOrder;
use Database\Factories\Caja\CashShiftFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    public function definition(): array
    {
        $types = ['sale', 'expense', 'income', 'refund'];
        $methods = ['cash', 'card', 'transfer', 'nequi', 'daviplata', 'other'];

        return [
            'tenant_id' => \App\Modules\Talleres\Models\Tenant::factory(),
            'shift_id' => \App\Modules\Caja\Models\CashShift::factory(),
            'type' => $types[array_rand($types)],
            'payment_method' => $methods[array_rand($methods)],
            'amount' => random_int(1000, 1000000) / 100,
            'description' => fake()->sentence(3),
            'created_by' => \App\Models\User::factory(),
        ];
    }

    public function sale(): static
    {
        return $this->state(function (array $attrs): array {
            return [
                'type' => 'sale',
                'payment_method' => array_rand(['cash', 'card', 'transfer']) . '_method',
            ];
        });
    }

    public function expense(): static
    {
        return $this->state(function (array $attrs): array {
            return [
                'type' => 'expense',
            ];
        });
    }

    public function income(): static
    {
        return $this->state(function (array $attrs): array {
            return [
                'type' => 'income',
            ];
        });
    }

    public function refund(): static
    {
        return $this->state(function (array $attrs): array {
            return [
                'type' => 'refund',
                'amount' => -random_int(1000, 50000) / 100,
            ];
        });
    }

    public function withWorkOrder(): static
    {
        return $this->state(function (array $attrs): array {
            return [
                'work_order_id' => WorkOrder::factory(),
            ];
        });
    }

    public function withInvoice(): static
    {
        return $this->state(function (array $attrs): array {
            return [
                'invoice_id' => \App\Modules\Facturacion\Models\Invoice::factory(),
            ];
        });
    }