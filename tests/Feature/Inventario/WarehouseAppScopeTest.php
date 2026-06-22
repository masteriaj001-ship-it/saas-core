<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WarehouseAppScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_can_create_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'code' => $warehouse->code,
        ]);
        $this->assertEquals($this->tenant->id, $warehouse->tenant_id);
    }

    public function test_warehouse_code_is_unique_per_tenant(): void
    {
        Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WH-001',
        ]);

        $this->expectException(QueryException::class);

        Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WH-001',
        ]);
    }

    public function test_same_code_allowed_in_different_tenants(): void
    {
        $otherTenant = Tenant::factory()->create();

        Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WH-001',
        ]);

        $warehouse = Warehouse::factory()->create([
            'tenant_id' => $otherTenant->id,
            'code' => 'WH-001',
        ]);

        $this->assertEquals($otherTenant->id, $warehouse->tenant_id);
    }

    public function test_only_one_default_warehouse_per_tenant(): void
    {
        Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'MAIN',
            'is_default' => true,
        ]);

        $this->expectException(QueryException::class);

        Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SECONDARY',
            'is_default' => true,
        ]);
    }

    public function test_can_update_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $warehouse->update(['name' => 'Updated Name']);

        $this->assertEquals('Updated Name', $warehouse->fresh()->name);
    }

    public function test_can_soft_delete_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $warehouse->delete();

        $this->assertSoftDeleted($warehouse);
    }

    public function test_warehouse_is_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        Warehouse::factory()->create(['tenant_id' => $otherTenant->id]);

        $count = Warehouse::count();

        $this->assertEquals(0, $count, 'Global scope should hide other tenant warehouses.');
    }
}
