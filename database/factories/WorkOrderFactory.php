<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Tenant;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'asset_id' => Asset::factory(),
            'contact_id' => Contact::factory()->client(),
            'code' => fake()->unique()->bothify('WO-####'),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(WorkOrderStatusEnum::cases())->value,
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'mechanic_id' => null,
            'advisor_id' => null,
            'reception_notes' => null,
            'fuel_level' => null,
            'diagnosis_summary' => null,
            'approval_channel' => null,
            'approval_at' => null,
            'qc_passed' => null,
            'qc_notes' => null,
            'delivery_at' => null,
        ];
    }

    public function withMechanic(): static
    {
        return $this->afterCreating(function (WorkOrder $workOrder): void {
            $mechanic = Contact::factory()->create([
                'tenant_id' => $workOrder->tenant_id,
                'contact_type' => 'employee',
            ]);
            ContactRole::factory()->create([
                'tenant_id' => $workOrder->tenant_id,
                'contact_id' => $mechanic->id,
                'role_code' => 'mechanic',
            ]);
            $workOrder->mechanic_id = $mechanic->id;
            $workOrder->save();
        });
    }

    public function withAdvisor(): static
    {
        return $this->afterCreating(function (WorkOrder $workOrder): void {
            $advisor = Contact::factory()->create([
                'tenant_id' => $workOrder->tenant_id,
                'contact_type' => 'employee',
            ]);
            ContactRole::factory()->create([
                'tenant_id' => $workOrder->tenant_id,
                'contact_id' => $advisor->id,
                'role_code' => 'service_advisor',
            ]);
            $workOrder->advisor_id = $advisor->id;
            $workOrder->save();
        });
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
