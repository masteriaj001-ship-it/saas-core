<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(1),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant): void {
            $freePlan = Plan::where('name', 'free')->first();

            if ($freePlan && ! $tenant->subscription) {
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $freePlan->id,
                    'started_at' => now(),
                    'status' => 'active',
                ]);
            }
        });
    }
}
