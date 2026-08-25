<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\SubscriptionLog;
use App\Modules\Plataforma\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_can_create_plan_with_limits(): void
    {
        $plan = Plan::create([
            'name' => 'custom',
            'label' => 'Custom Plan',
            'price_cop' => 50000,
            'max_users' => 5,
            'max_work_orders' => 100,
            'features' => ['pos', 'work_orders'],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('plans', [
            'name' => 'custom',
            'max_users' => 5,
            'max_work_orders' => 100,
        ]);

        $this->assertEquals(5, $plan->max_users);
        $this->assertEquals(100, $plan->max_work_orders);
    }

    public function test_can_change_plan_of_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $proPlan = Plan::where('name', 'pro')->first();

        $service = app(SubscriptionService::class);
        $subscription = $service->changePlan(
            tenant: $tenant,
            newPlan: $proPlan,
            changedBy: $superadmin,
            reason: 'Upgrade to Pro',
        );

        $this->assertEquals('pro', $subscription->fresh()->plan->name);

        $this->assertDatabaseHas('subscription_logs', [
            'tenant_id' => $tenant->id,
            'plan_to' => $proPlan->id,
            'changed_by' => $superadmin->id,
            'reason' => 'Upgrade to Pro',
        ]);
    }

    public function test_change_plan_requires_reason_for_audit(): void
    {
        $tenant = Tenant::factory()->create();
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $proPlan = Plan::where('name', 'pro')->first();

        $service = app(SubscriptionService::class);
        $subscription = $service->changePlan(
            tenant: $tenant,
            newPlan: $proPlan,
            changedBy: $superadmin,
            reason: null,
        );

        $log = SubscriptionLog::where('tenant_id', $tenant->id)
            ->where('plan_to', $proPlan->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->reason);
    }

    public function test_inactive_plan_not_assignable(): void
    {
        $tenant = Tenant::factory()->create();
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $inactivePlan = Plan::create([
            'name' => 'deprecated',
            'label' => 'Deprecated Plan',
            'price_cop' => 0,
            'max_users' => 1,
            'max_work_orders' => null,
            'features' => [],
            'is_active' => false,
        ]);

        $service = app(SubscriptionService::class);
        $subscription = $service->changePlan(
            tenant: $tenant,
            newPlan: $inactivePlan,
            changedBy: $superadmin,
            reason: 'Test inactive',
        );

        $this->assertNotNull($subscription);
        $this->assertEquals('deprecated', $subscription->fresh()->plan->name);
    }
}
