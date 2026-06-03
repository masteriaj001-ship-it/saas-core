<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Talleres\Models\Asset;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TallerTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_cannot_see_tenant_b_assets(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA = User::factory()->for($tenantA)->create();
        $userB = User::factory()->for($tenantB)->create();

        Asset::factory()->for($tenantA)->count(3)->create();
        Asset::factory()->for($tenantB)->count(2)->create();

        $this->actingAs($userA);
        app(TenantManager::class)->setTenantContext($tenantA->id);
        $this->assertEquals(3, Asset::count());

        $this->actingAs($userB);
        app(TenantManager::class)->setTenantContext($tenantB->id);
        $this->assertEquals(2, Asset::count());
    }

    public function test_superadmin_can_see_all_assets(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'is_superadmin' => true,
        ]);

        Asset::factory()->for($tenantA)->count(3)->create();
        Asset::factory()->for($tenantB)->count(2)->create();

        $this->actingAs($superadmin);
        $total = Asset::withoutGlobalScope('tenant')->count();

        $this->assertEquals(5, $total);
    }
}
