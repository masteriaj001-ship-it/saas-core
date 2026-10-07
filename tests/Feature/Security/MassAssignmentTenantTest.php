<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Modules\Caja\Models\CashMovement;
use App\Modules\Caja\Models\CashShift;
use App\Modules\Talleres\Models\SmsCode;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassAssignmentTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_tenant_id_is_not_mass_assignable(): void
    {
        $otherTenant = Tenant::factory()->create();

        $shift = new CashShift(['tenant_id' => $otherTenant->id]);
        $movement = new CashMovement(['tenant_id' => $otherTenant->id]);
        $sms = new SmsCode(['tenant_id' => $otherTenant->id]);
        $module = new TenantModule(['tenant_id' => $otherTenant->id]);

        $this->assertNotEquals($otherTenant->id, $shift->tenant_id);
        $this->assertNotEquals($otherTenant->id, $movement->tenant_id);
        $this->assertNotEquals($otherTenant->id, $sms->tenant_id);
        $this->assertNotEquals($otherTenant->id, $module->tenant_id);
    }

    public function test_creating_hook_still_injects_context_tenant(): void
    {
        $shift = CashShift::openShift($this->user, 100000);

        $this->assertEquals($this->tenant->id, $shift->tenant_id);
    }
}
