<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTallerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();
        $this->asset = Asset::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_admin_can_create_work_order_with_service_description(): void
    {
        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'asset_id' => $this->asset->id,
            'code' => 'WO-0001',
            'title' => 'Cambio de aceite',
            'service_description' => 'Cambio de aceite y filtros',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'service_description' => 'Cambio de aceite y filtros',
        ]);
    }
}
