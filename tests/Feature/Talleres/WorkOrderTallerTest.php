<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\VehicleTypeEnum;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTallerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private ClientVehicle $clientVehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();
        $this->clientVehicle = ClientVehicle::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_work_order_with_service_description(): void
    {
        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $this->clientVehicle->id,
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

    public function test_can_create_contact_inline_from_work_order_form(): void
    {
        // Simula el callback createOptionUsing del Select contact_id
        $contact = Contact::create([
            'name' => 'Inline Contact',
            'phone' => '555-0100',
            'contact_type' => 'client',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Inline Contact',
            'phone' => '555-0100',
            'contact_type' => 'client',
        ]);
    }

    public function test_can_create_client_vehicle_inline_from_work_order_form(): void
    {
        // Simula el callback createOptionUsing del Select client_vehicle_id
        $clientVehicle = ClientVehicle::create([
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'plate' => 'XYZ-987',
            'vehicle_type' => VehicleTypeEnum::Sedan,
        ]);

        $this->assertDatabaseHas('client_vehicles', [
            'id' => $clientVehicle->id,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'plate' => 'XYZ-987',
            'vehicle_type' => VehicleTypeEnum::Sedan->value,
        ]);
    }

    public function test_can_create_work_order_with_service_items(): void
    {
        $service = ServiceCatalog::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cambio de aceite',
            'base_price' => 35000,
            'estimated_minutes' => 30,
            'is_active' => true,
        ]);
        $partItem = Item::factory()->for($this->tenant)->create();

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $this->clientVehicle->id,
            'code' => 'WO-0002',
            'title' => 'Mantenimiento general',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Part->value,
            'item_id' => $partItem->id,
            'quantity' => 2,
            'unit_price' => 15000,
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Service->value,
            'service_catalog_id' => $service->id,
            'item_id' => null,
            'quantity' => 1,
            'unit_price' => 35000,
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Labor->value,
            'item_id' => null,
            'service_catalog_id' => null,
            'quantity' => 1,
            'unit_price' => 5000,
            'description' => 'Mano de obra: cambio de frenos',
        ]);

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $workOrder->id,
            'type' => 'part',
            'item_id' => $partItem->id,
            'service_catalog_id' => null,
        ]);

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $workOrder->id,
            'type' => 'service',
            'item_id' => null,
            'service_catalog_id' => $service->id,
        ]);

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $workOrder->id,
            'type' => 'labor',
            'item_id' => null,
            'service_catalog_id' => null,
        ]);
    }

    public function test_cannot_create_work_order_with_invalid_item_fk(): void
    {
        $this->expectException(QueryException::class);

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $this->clientVehicle->id,
            'code' => 'WO-0003',
            'title' => 'WorkOrder with invalid item',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Part->value,
            'item_id' => '00000000-0000-0000-0000-000000000000',
            'quantity' => 1,
            'unit_price' => 10000,
        ]);
    }
}
