<?php

declare(strict_types=1);

namespace Database\Factories\Caja;

use App\Models\User;
use App\Modules\Caja\Models\CashShift;
use App\Modules\Talleres\Models\Tenant;
use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashShiftFactory extends Factory
{
    protected $model = CashShift::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'opened_by' => User::factory(),
            'initial_amount' => random_int(100000, 5000000) / 100,
            'status' => 'open',
            'metadata' => [],
        ];
    }

    public function closed(): static
    {
        return $this->state(function (array $attrs): array {
            return [
                'closed_by' => User::factory(),
                'closed_at' => now(),
                'actual_cash' => $attrs['initial_amount'] + random_int(-50000, 50000) / 100,
                'expected_cash' => $attrs['initial_amount'],
                'difference' => random_int(-50000, 50000) / 100,
                'status' => 'closed',
                'notes' => 'Cierre del turno',
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
}
