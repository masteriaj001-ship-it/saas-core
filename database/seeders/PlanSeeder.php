<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Plataforma\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'free',
                'label' => 'Gratuito',
                'price_cop' => 0,
                'max_users' => 2,
                'max_work_orders' => 20,
                'features' => ['pos', 'work_orders', 'contacts', 'inventory'],
                'is_active' => true,
            ],
            [
                'name' => 'pro',
                'label' => 'Pro',
                'price_cop' => 89900,
                'max_users' => 10,
                'max_work_orders' => null,
                'features' => ['pos', 'work_orders', 'contacts', 'inventory', 'reports', 'multi_location'],
                'is_active' => true,
            ],
            [
                'name' => 'enterprise',
                'label' => 'Enterprise',
                'price_cop' => 249900,
                'max_users' => null,
                'max_work_orders' => null,
                'features' => ['pos', 'work_orders', 'contacts', 'inventory', 'reports', 'multi_location', 'api', 'white_label'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['name' => $plan['name']],
                $plan,
            );
        }
    }
}
