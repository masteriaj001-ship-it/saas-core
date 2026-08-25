<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use App\Modules\Plataforma\Models\SubscriptionLog;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_new_tenant_receives_free_plan_automatically(): void
    {
        $tenant = Tenant::factory()->create();

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($subscription);
        $this->assertEquals('free', $subscription->plan->name);
        $this->assertEquals('active', $subscription->status);
        $this->assertNull($subscription->expires_at);
    }

    public function test_plan_change_registers_in_subscription_logs(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $tenant = Tenant::factory()->create();
        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $proPlan = Plan::where('name', 'pro')->first();
        $oldPlanId = $subscription->plan_id;

        $subscription->update([
            'plan_id' => $proPlan->id,
            'changed_by' => $superadmin->id,
        ]);

        SubscriptionLog::create([
            'tenant_id' => $tenant->id,
            'plan_from' => $oldPlanId,
            'plan_to' => $proPlan->id,
            'changed_by' => $superadmin->id,
            'changed_at' => now(),
            'reason' => 'Upgrade to Pro',
        ]);

        $this->assertDatabaseHas('subscription_logs', [
            'tenant_id' => $tenant->id,
            'plan_from' => $oldPlanId,
            'plan_to' => $proPlan->id,
            'changed_by' => $superadmin->id,
        ]);

        $this->assertEquals('pro', $subscription->fresh()->plan->name);
    }

    public function test_expired_plan_blocks_access(): void
    {
        $tenant = Tenant::factory()->create();
        $expiredPlan = Plan::where('name', 'pro')->first();

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $subscription->update([
            'plan_id' => $expiredPlan->id,
            'expires_at' => now()->subDay(),
        ]);

        $subscription->refresh();

        $this->assertTrue($subscription->isExpired());
        $this->assertFalse($subscription->isActive());
    }

    public function test_suspended_tenant_blocks_access(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $subscription->update(['status' => 'suspended']);

        $subscription->refresh();

        $this->assertTrue($subscription->isSuspended());
        $this->assertFalse($subscription->isActive());
    }
}
