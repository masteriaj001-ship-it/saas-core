<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Exceptions\PlanLimitExceededException;
use App\Modules\Plataforma\Models\Plan;
use App\Modules\Plataforma\Models\Subscription;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_cannot_create_work_order_if_exceeding_plan_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantManager = app(TenantManager::class);
        $tenantManager->setTenantContext($tenant->id);

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $freePlan = Plan::where('name', 'free')->first();
        $subscription->update(['plan_id' => $freePlan->id]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        for ($i = 0; $i < $freePlan->max_work_orders; $i++) {
            WorkOrder::create([
                'code' => "OT-{$i}",
                'title' => "OT {$i}",
                'status' => 'draft',
                'client_report' => 'Test',
            ]);
        }

        $this->expectException(PlanLimitExceededException::class);

        WorkOrder::create([
            'code' => 'OT-EXCEED',
            'title' => 'OT Exceed',
            'status' => 'draft',
            'client_report' => 'Test',
        ]);

        $tenantManager->clearTenantContext();
    }

    public function test_cannot_create_user_if_exceeding_plan_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantManager = app(TenantManager::class);
        $tenantManager->setTenantContext($tenant->id);

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $freePlan = Plan::where('name', 'free')->first();
        $subscription->update(['plan_id' => $freePlan->id]);

        for ($i = 0; $i < $freePlan->max_users; $i++) {
            User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@test.com",
                'password' => Hash::make('password'),
            ]);
        }

        $this->expectException(PlanLimitExceededException::class);

        User::create([
            'name' => 'User Exceed',
            'email' => 'exceed@test.com',
            'password' => Hash::make('password'),
        ]);

        $tenantManager->clearTenantContext();
    }

    public function test_unlimited_plan_allows_unlimited_creation(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantManager = app(TenantManager::class);
        $tenantManager->setTenantContext($tenant->id);

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $proPlan = Plan::where('name', 'pro')->first();
        $subscription->update(['plan_id' => $proPlan->id]);

        for ($i = 0; $i < 5; $i++) {
            WorkOrder::create([
                'code' => "OT-UNL-{$i}",
                'title' => "OT Unlimited {$i}",
                'status' => 'draft',
                'client_report' => 'Test',
            ]);
        }

        $this->assertEquals(5, WorkOrder::where('tenant_id', $tenant->id)->count());

        $tenantManager->clearTenantContext();
    }

    public function test_clear_error_message_on_exceeding_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $tenantManager = app(TenantManager::class);
        $tenantManager->setTenantContext($tenant->id);

        $subscription = Subscription::where('tenant_id', $tenant->id)->first();
        $freePlan = Plan::where('name', 'free')->first();
        $subscription->update(['plan_id' => $freePlan->id]);

        for ($i = 0; $i < $freePlan->max_work_orders; $i++) {
            WorkOrder::create([
                'code' => "OT-ERR-{$i}",
                'title' => "OT {$i}",
                'status' => 'draft',
                'client_report' => 'Test',
            ]);
        }

        try {
            WorkOrder::create([
                'code' => 'OT-ERR-EXCEED',
                'title' => 'OT Exceed',
                'status' => 'draft',
                'client_report' => 'Test',
            ]);
            $this->fail('Expected PlanLimitExceededException');
        } catch (PlanLimitExceededException $e) {
            $this->assertStringContainsString('ordenes de trabajo', $e->getMessage());
            $this->assertStringContainsString('Límite de plan', $e->getMessage());
        }

        $tenantManager->clearTenantContext();
    }
}
