<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\ImpersonationLog;
use App\Modules\Plataforma\Services\ImpersonationService;
use App\Services\TenantManager;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_superadmin_can_impersonate_tenant(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $tenant = Tenant::factory()->create();

        $service = app(ImpersonationService::class);
        $log = $service->start($superadmin, $tenant);

        $this->assertNotNull($log);
        $this->assertEquals($superadmin->id, $log->superadmin_id);
        $this->assertEquals($tenant->id, $log->tenant_id);
        $this->assertTrue($service->isImpersonating());
        $this->assertEquals($tenant->id, $service->getImpersonatingTenantId());
    }

    public function test_impersonation_registers_log_with_timestamp(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $tenant = Tenant::factory()->create();

        $service = app(ImpersonationService::class);
        $log = $service->start($superadmin, $tenant);

        $this->assertDatabaseHas('impersonation_logs', [
            'superadmin_id' => $superadmin->id,
            'tenant_id' => $tenant->id,
            'started_at' => $log->started_at,
        ]);
    }

    public function test_banner_visible_during_impersonation(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $tenant = Tenant::factory()->create();

        $service = app(ImpersonationService::class);
        $service->start($superadmin, $tenant);

        $this->assertTrue($service->isImpersonating());
        $this->assertNotNull($service->getImpersonatingTenantId());
    }

    public function test_stopping_impersonation_registers_ended_at(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $tenant = Tenant::factory()->create();

        $service = app(ImpersonationService::class);
        $service->start($superadmin, $tenant);

        $this->assertTrue($service->isImpersonating());

        $service->stop($superadmin);

        $this->assertFalse($service->isImpersonating());
        $this->assertNull($service->getImpersonatingTenantId());

        $log = ImpersonationLog::where('superadmin_id', $superadmin->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($log->ended_at);
    }

    public function test_only_superadmin_can_impersonate(): void
    {
        $tenant = Tenant::factory()->create();

        $tenantManager = app(TenantManager::class);
        $tenantManager->setTenantContext($tenant->id);

        $regularUser = User::factory()->create(['is_superadmin' => false]);

        $tenantManager->clearTenantContext();

        $this->actingAs($regularUser);

        $response = $this->get(route('superadmin.impersonate', $tenant->id));

        $response->assertStatus(403);
    }
}
