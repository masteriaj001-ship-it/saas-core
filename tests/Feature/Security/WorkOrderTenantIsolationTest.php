<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_cannot_see_tenant_b_work_orders(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->for($tenantA)->create();
        $userB = User::factory()->for($tenantB)->create();

        WorkOrder::factory()->for($tenantA)->count(3)->create();
        WorkOrder::factory()->for($tenantB)->count(2)->create();

        $this->actingAs($userA);
        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->assertEquals(3, WorkOrder::count());

        $this->actingAs($userB);
        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->assertEquals(2, WorkOrder::count());
    }
}
