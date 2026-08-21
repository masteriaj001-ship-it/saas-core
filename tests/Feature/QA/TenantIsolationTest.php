<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\Contact;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\ServiceCatalog;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = User::factory()->for($this->tenantA)->create();
        $this->userB = User::factory()->for($this->tenantB)->create();
    }

    public function test_contacts_are_isolated_between_tenants(): void
    {
        Contact::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Client A',
            'contact_type' => 'client',
        ]);

        Contact::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Client B',
            'contact_type' => 'client',
        ]);

        $this->actingAs($this->userA);
        app(TenantManager::class)->setTenantContext($this->tenantA->id);

        $contacts = Contact::query()->tenant()->get();

        $this->assertCount(1, $contacts);
        $this->assertEquals('Client A', $contacts->first()->name);
    }

    public function test_items_are_isolated_between_tenants(): void
    {
        Item::factory()->spare()->for($this->tenantA)->create(['name' => 'Item A']);
        Item::factory()->spare()->for($this->tenantB)->create(['name' => 'Item B']);

        $this->actingAs($this->userA);
        app(TenantManager::class)->setTenantContext($this->tenantA->id);

        $items = Item::query()->tenant()->get();

        $this->assertCount(1, $items);
        $this->assertEquals('Item A', $items->first()->name);
    }

    public function test_work_orders_are_isolated_between_tenants(): void
    {
        $vehicleA = ClientVehicle::factory()->for($this->tenantA)->create();
        $vehicleB = ClientVehicle::factory()->for($this->tenantB)->create();

        WorkOrder::create([
            'tenant_id' => $this->tenantA->id,
            'client_vehicle_id' => $vehicleA->id,
            'code' => 'WO-A-001',
            'title' => 'Work Order A',
            'status' => 'draft',
        ]);

        WorkOrder::create([
            'tenant_id' => $this->tenantB->id,
            'client_vehicle_id' => $vehicleB->id,
            'code' => 'WO-B-001',
            'title' => 'Work Order B',
            'status' => 'draft',
        ]);

        $this->actingAs($this->userA);
        app(TenantManager::class)->setTenantContext($this->tenantA->id);

        $workOrders = WorkOrder::query()->tenant()->get();

        $this->assertCount(1, $workOrders);
        $this->assertEquals('WO-A-001', $workOrders->first()->code);
    }

    public function test_client_vehicles_are_isolated_between_tenants(): void
    {
        ClientVehicle::factory()->for($this->tenantA)->create(['plate' => 'AAA-111']);
        ClientVehicle::factory()->for($this->tenantB)->create(['plate' => 'BBB-222']);

        $this->actingAs($this->userA);
        app(TenantManager::class)->setTenantContext($this->tenantA->id);

        $vehicles = ClientVehicle::query()->tenant()->get();

        $this->assertCount(1, $vehicles);
        $this->assertEquals('AAA-111', $vehicles->first()->plate);
    }

    public function test_service_catalog_isolated_between_tenants(): void
    {
        ServiceCatalog::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Service A',
            'base_price' => 10000,
            'is_active' => true,
        ]);

        ServiceCatalog::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Service B',
            'base_price' => 20000,
            'is_active' => true,
        ]);

        $this->actingAs($this->userA);
        app(TenantManager::class)->setTenantContext($this->tenantA->id);

        $services = ServiceCatalog::where('tenant_id', $this->tenantA->id)->get();

        $this->assertCount(1, $services);
        $this->assertEquals('Service A', $services->first()->name);
    }

    public function test_user_cannot_see_other_tenant_data_via_query(): void
    {
        Contact::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Private Contact A',
            'contact_type' => 'client',
        ]);

        Contact::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Private Contact B',
            'contact_type' => 'client',
        ]);

        $this->actingAs($this->userA);
        app(TenantManager::class)->setTenantContext($this->tenantA->id);

        $allContacts = Contact::query()->tenant()->pluck('name');

        $this->assertContains('Private Contact A', $allContacts);
        $this->assertNotContains('Private Contact B', $allContacts);
    }
}
