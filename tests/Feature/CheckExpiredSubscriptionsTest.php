<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckExpiredSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_downgrades_expired_pro_subscription_to_free(): void
    {
        $tenant = Tenant::factory()->create();
        $proPlan = Plan::where('name', 'pro')->first();
        $freePlan = Plan::where('name', 'free')->first();

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $subscription->update([
            'plan_id' => $proPlan->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:check-expired')
            ->assertExitCode(0);

        $subscription->refresh();

        $this->assertEquals($freePlan->id, $subscription->plan_id);
        $this->assertEquals('active', $subscription->status);
        $this->assertNull($subscription->expires_at);
    }

    public function test_creates_subscription_log_on_downgrade(): void
    {
        $tenant = Tenant::factory()->create();
        $proPlan = Plan::where('name', 'pro')->first();
        $freePlan = Plan::where('name', 'free')->first();

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $subscription->update([
            'plan_id' => $proPlan->id,
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('subscriptions:check-expired');

        $this->assertDatabaseHas('subscription_logs', [
            'tenant_id' => $tenant->id,
            'plan_from' => $proPlan->id,
            'plan_to' => $freePlan->id,
            'reason' => 'expired_downgraded_to_free',
        ]);
    }

    public function test_does_not_touch_active_non_expired_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $proPlan = Plan::where('name', 'pro')->first();

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $subscription->update([
            'plan_id' => $proPlan->id,
            'expires_at' => now()->addMonth(),
        ]);

        $this->artisan('subscriptions:check-expired');

        $subscription->refresh();

        $this->assertEquals($proPlan->id, $subscription->plan_id);
        $this->assertEquals('active', $subscription->status);
    }

    public function test_does_not_touch_free_plan_with_no_expiry(): void
    {
        $tenant = Tenant::factory()->create();
        $freePlan = Plan::where('name', 'free')->first();

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();

        $this->artisan('subscriptions:check-expired');

        $subscription->refresh();

        $this->assertEquals($freePlan->id, $subscription->plan_id);
        $this->assertEquals('active', $subscription->status);
    }

    public function test_reports_correct_count(): void
    {
        $proPlan = Plan::where('name', 'pro')->first();
        $freePlan = Plan::where('name', 'free')->first();

        $expired = Tenant::factory()->create();
        $sub1 = Subscription::where('tenant_id', $expired->id)->first();
        $sub1->update([
            'plan_id' => $proPlan->id,
            'expires_at' => now()->subDay(),
        ]);

        $active = Tenant::factory()->create();
        $sub2 = Subscription::where('tenant_id', $active->id)->first();
        $sub2->update([
            'plan_id' => $proPlan->id,
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $expired->id,
            'plan_id' => $proPlan->id,
        ]);

        $this->artisan('subscriptions:check-expired')
            ->assertExitCode(0);

        $this->assertDatabaseCount('subscription_logs', 1);

        $this->assertDatabaseHas('subscription_logs', [
            'tenant_id' => $expired->id,
            'plan_from' => $proPlan->id,
            'plan_to' => $freePlan->id,
            'reason' => 'expired_downgraded_to_free',
        ]);
    }
}
