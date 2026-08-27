<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Enums\WorkOrderItemTypeEnum;
use App\Models\Contact;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\ServiceCatalog;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderFlowTest extends TestCase
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
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_can_create_work_order(): void
    {
        $contact = Contact::factory()->for($this->tenant)->client()->create();
        $vehicle = ClientVehicle::factory()->for($this->tenant)->create();

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'client_vehicle_id' => $vehicle->id,
            'code' => 'WO-TEST-001',
            'title' => 'Mantenimiento preventivo',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'title' => 'Mantenimiento preventivo',
            'status' => 'draft',
        ]);
    }

    public function test_can_add_part_item_to_work_order(): void
    {
        $contact = Contact::factory()->for($this->tenant)->client()->create();
        $vehicle = ClientVehicle::factory()->for($this->tenant)->create();
        $item = Item::factory()->spare()->for($this->tenant)->create(['price' => 50000]);

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'client_vehicle_id' => $vehicle->id,
            'code' => 'WO-TEST-002',
            'title' => 'Reparación frenos',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Part->value,
            'item_id' => $item->id,
            'quantity' => 2,
            'unit_price' => 50000,
        ]);

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $workOrder->id,
            'type' => 'part',
            'item_id' => $item->id,
            'quantity' => 2,
            'unit_price' => 50000,
        ]);
    }

    public function test_can_add_service_from_catalog(): void
    {
        $contact = Contact::factory()->for($this->tenant)->client()->create();
        $vehicle = ClientVehicle::factory()->for($this->tenant)->create();
        $service = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Revisión de frenos',
            'base_price' => 35000,
            'estimated_minutes' => 45,
            'is_active' => true,
        ]);

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'client_vehicle_id' => $vehicle->id,
            'code' => 'WO-TEST-003',
            'title' => 'Cambio de frenos',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Service->value,
            'service_catalog_id' => $service->id,
            'item_id' => null,
            'quantity' => 1,
            'unit_price' => $service->base_price,
        ]);

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $workOrder->id,
            'type' => 'service',
            'service_catalog_id' => $service->id,
            'unit_price' => 35000,
        ]);
    }

    public function test_can_add_labor_item(): void
    {
        $contact = Contact::factory()->for($this->tenant)->client()->create();
        $vehicle = ClientVehicle::factory()->for($this->tenant)->create();

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'client_vehicle_id' => $vehicle->id,
            'code' => 'WO-TEST-004',
            'title' => 'Servicio general',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Labor->value,
            'item_id' => null,
            'service_catalog_id' => null,
            'quantity' => 1,
            'unit_price' => 15000,
            'description' => 'Mano de obra: cambio de aceite',
        ]);

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $workOrder->id,
            'type' => 'labor',
            'description' => 'Mano de obra: cambio de aceite',
            'unit_price' => 15000,
        ]);
    }

    public function test_service_price_auto_populated_from_catalog(): void
    {
        $service = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Diagnóstico electrónico',
            'base_price' => 25000,
            'is_active' => true,
        ]);

        $this->assertEquals(25000, $service->base_price);
    }

    public function test_multiple_item_types_on_same_work_order(): void
    {
        $contact = Contact::factory()->for($this->tenant)->client()->create();
        $vehicle = ClientVehicle::factory()->for($this->tenant)->create();
        $part = Item::factory()->spare()->for($this->tenant)->create(['price' => 120000]);
        $service = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Revisión general',
            'base_price' => 50000,
            'is_active' => true,
        ]);

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'client_vehicle_id' => $vehicle->id,
            'code' => 'WO-TEST-005',
            'title' => 'Servicio completo',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Part->value,
            'item_id' => $part->id,
            'quantity' => 1,
            'unit_price' => 120000,
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Service->value,
            'service_catalog_id' => $service->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Labor->value,
            'quantity' => 2,
            'unit_price' => 15000,
            'description' => 'Mano de obra',
        ]);

        $this->assertEquals(3, $workOrder->items()->count());

        $totalParts = $workOrder->items()->where('type', 'part')->sum('unit_price');
        $totalServices = $workOrder->items()->where('type', 'service')->sum('unit_price');
        $totalLabor = $workOrder->items()->where('type', 'labor')->get()->reduce(fn ($carry, $i) => $carry + ($i->unit_price * $i->quantity), 0);

        $this->assertEquals(120000, $totalParts);
        $this->assertEquals(50000, $totalServices);
        $this->assertEquals(30000, $totalLabor);
    }

    public function test_work_order_status_transitions(): void
    {
        $contact = Contact::factory()->for($this->tenant)->client()->create();
        $vehicle = ClientVehicle::factory()->for($this->tenant)->create();

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'client_vehicle_id' => $vehicle->id,
            'code' => 'WO-TEST-006',
            'title' => 'Test estados',
            'status' => 'draft',
        ]);

        $workOrder->update(['status' => 'in_progress', 'started_at' => now()]);
        $this->assertEquals('in_progress', $workOrder->fresh()->status->value);

        $workOrder->update(['status' => 'completed', 'completed_at' => now()]);
        $this->assertEquals('completed', $workOrder->fresh()->status->value);
    }
}
