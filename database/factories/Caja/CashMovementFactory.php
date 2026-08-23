<?php

declare(strict_types=1);

namespace Database\Factories\Caja;

use App\Models\User;
use App\Modules\Caja\Models\CashMovement;
use App\Modules\Caja\Models\CashShift;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Talleres\Models\Tenant;
use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    public function definition(): array
    {
        $types = ['sale', 'expense', 'income', 'refund'];
        $methods = ['cash', 'card', 'transfer', 'nequi', 'daviplata', 'other'];

        return [
            'tenant_id' => Tenant::factory(),
            'shift_id' => CashShift::factory(),
            'type' => $types[array_rand($types)],
            'payment_method' => $methods[array_rand($methods)],
            'amount' => random_int(1000, 1000000) / 100,
            'description' => fake()->sentence(3),
            'created_by' => User::factory(),
        ];
    }

    public function sale(): static
    {
        return $this->state(function (array $attrs): array {
            return [
                'type' => 'sale',
                'payment_method' => array_rand(['cash', 'card', 'transfer']).'_method',
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
                'invoice_id' => Invoice::factory(),
            ];
        });
    }
}
