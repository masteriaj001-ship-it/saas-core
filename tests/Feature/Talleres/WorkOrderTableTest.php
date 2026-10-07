<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Filament\Resources\WorkOrderResource;
use App\Filament\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkOrderTableTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole('owner');
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);
    }

    public function test_list_renders_work_order_without_vehicle(): void
    {
        $order = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => null,
        ]);

        Livewire::test(ListWorkOrders::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$order]);
    }

    public function test_search_does_not_error(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => null,
        ]);

        Livewire::test(ListWorkOrders::class)
            ->searchTable('test-sin-resultados-xyz')
            ->assertSuccessful();
    }

    public function test_table_query_eager_loads_relations(): void
    {
        $eagerLoads = array_keys(WorkOrderResource::getEloquentQuery()->getEagerLoads());

        $this->assertContains('mechanic', $eagerLoads);
        $this->assertContains('clientVehicle', $eagerLoads);
    }
}
