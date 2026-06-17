<?php

declare(strict_types=1);

/**
 * Application-level tests for GAP-003: Tenant Context for Queue Jobs.
 *
 * These tests verify that the BelongsToTenantJob trait captures tenant
 * context at dispatch time, and the SetTenantContextForJob middleware
 * restores it when the job runs.
 *
 * @see \Tests\Feature\Security\TenantModuleRlsTest — RLS tests
 */

namespace Tests\Feature\Security;

use App\Jobs\Middleware\SetTenantContextForJob;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Doubles\Jobs\WithTenantContextJob;
use Tests\TestCase;

final class TenantJobContextAppScopeTest extends TestCase
{
    use RefreshDatabase;

    private TenantManager $tenantManager;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantManager = app(TenantManager::class);

        $this->tenant = Tenant::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->tenantManager->clearTenantContext();
        parent::tearDown();
    }

    public function test_trait_captures_tenant_id_at_dispatch(): void
    {
        $this->tenantManager->setTenantContext($this->tenant->id);

        $job = new WithTenantContextJob;

        $this->assertNotNull($job->tenantId, 'Job dispatched with context should have tenantId set.');
        $this->assertEquals(
            $this->tenant->id,
            $job->tenantId,
            'tenantId should match the current context at dispatch time.'
        );
    }

    public function test_trait_leaves_tenant_id_null_without_context(): void
    {
        $job = new WithTenantContextJob;

        $this->assertNull($job->tenantId, 'Job dispatched without context should have null tenantId.');
    }

    public function test_middleware_sets_context_before_handle(): void
    {
        $this->tenantManager->setTenantContext($this->tenant->id);

        $job = new WithTenantContextJob;

        $this->tenantManager->clearTenantContext();

        $middleware = app(SetTenantContextForJob::class);

        $middleware->handle($job, function () {
            $this->assertTrue(
                $this->tenantManager->hasContext(),
                'Context should be set inside middleware callback.'
            );
            $this->assertEquals(
                $this->tenant->id,
                $this->tenantManager->getCurrentTenantId(),
                'Context should match the tenant captured at dispatch time.'
            );
        });
    }

    public function test_middleware_skips_context_when_tenant_id_null(): void
    {
        $job = new WithTenantContextJob;

        $middleware = app(SetTenantContextForJob::class);

        $middleware->handle($job, function () {
            $this->assertFalse(
                $this->tenantManager->hasContext(),
                'Context should NOT be set inside middleware when tenantId is null.'
            );
        });
    }
}
