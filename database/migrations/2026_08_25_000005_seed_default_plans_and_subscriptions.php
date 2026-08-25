<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $plans = [
            [
                'id' => (string) Str::uuid(),
                'name' => 'free',
                'label' => 'Gratuito',
                'price_cop' => 0,
                'max_users' => 2,
                'max_work_orders' => 20,
                'features' => json_encode(['pos', 'work_orders', 'contacts', 'inventory']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'pro',
                'label' => 'Pro',
                'price_cop' => 89900,
                'max_users' => 10,
                'max_work_orders' => null,
                'features' => json_encode(['pos', 'work_orders', 'contacts', 'inventory', 'reports', 'multi_location']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'enterprise',
                'label' => 'Enterprise',
                'price_cop' => 249900,
                'max_users' => null,
                'max_work_orders' => null,
                'features' => json_encode(['pos', 'work_orders', 'contacts', 'inventory', 'reports', 'multi_location', 'api', 'white_label']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($plans as $plan) {
            DB::table('plans')->insertOrIgnore($plan);
        }

        $freePlanId = DB::table('plans')->where('name', 'free')->value('id');
        $proPlanId = DB::table('plans')->where('name', 'pro')->value('id');
        $enterprisePlanId = DB::table('plans')->where('name', 'enterprise')->value('id');

        $planMap = [
            'free' => $freePlanId,
            'pro' => $proPlanId,
            'enterprise' => $enterprisePlanId,
        ];

        $tenants = DB::table('tenants')
            ->whereNotIn('id', DB::table('subscriptions')->pluck('tenant_id'))
            ->get();

        foreach ($tenants as $tenant) {
            $planName = $tenant->plan ?? 'free';
            $planId = $planMap[$planName] ?? $freePlanId;

            DB::table('subscriptions')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
                'started_at' => $tenant->created_at ?? now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('subscriptions')->whereNull('changed_by')->delete();
        DB::table('plans')->delete();
    }
};
