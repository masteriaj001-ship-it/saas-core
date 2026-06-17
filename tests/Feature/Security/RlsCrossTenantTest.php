<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RlsCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    private TenantManager $tenantManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantManager = app(TenantManager::class);
    }

    public function test_tenant_a_no_ve_assets_de_tenant_b_con_rls_real(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        $this->tenantManager->setTenantContext($tenantA->id);
        $assetA = Asset::factory()->for($tenantA)->create(['name' => 'Asset de A']);

        $this->tenantManager->setTenantContext($tenantB->id);
        $assetB = Asset::factory()->for($tenantB)->create(['name' => 'Asset de B']);

        $this->tenantManager->setTenantContext($tenantA->id);

        $assets = Asset::all();

        $this->assertCount(1, $assets);
        $this->assertEquals($assetA->id, $assets->first()->id);
    }

    public function test_sin_tenant_context_rls_bloquea_todo(): void
    {
        $this->expectExceptionMessageMatches('/tenant_context_missing|P0001/');

        DB::connection('pgsql-rls')->select('SELECT current_tenant_id()');
    }

    public function test_superadmin_sin_contexto_no_puede_query_directa(): void
    {
        $this->expectExceptionMessageMatches('/tenant_context_missing|P0001/');

        DB::connection('pgsql-rls')
            ->table(Asset::make()->getTable())
            ->select('*')
            ->get();
    }

    public function test_eloquent_scope_protege_sin_rls(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->tenantManager->setTenantContext($tenantA->id);
        $assetA = Asset::factory()->for($tenantA)->create();

        $this->tenantManager->setTenantContext($tenantB->id);
        Asset::factory()->for($tenantB)->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $this->actingAs($userA);
        $this->tenantManager->setTenantContext($tenantA->id);

        $assets = Asset::all();

        $this->assertCount(1, $assets);
        $this->assertEquals($assetA->id, $assets->first()->id);
    }

    public function test_without_global_scope_expone_datos_si_rls_bypassed(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->tenantManager->setTenantContext($tenantA->id);
        Asset::factory()->for($tenantA)->create(['name' => 'A']);

        $this->tenantManager->setTenantContext($tenantB->id);
        Asset::factory()->for($tenantB)->create(['name' => 'B']);

        $this->tenantManager->setTenantContext($tenantA->id);
        $assets = Asset::withoutGlobalScope('tenant')->get();

        $this->assertGreaterThanOrEqual(1, $assets->count());
    }
}
