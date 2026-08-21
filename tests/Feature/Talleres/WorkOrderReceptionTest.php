<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Actions\CreateWorkOrderReceptionAction;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderReceptionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private CreateWorkOrderReceptionAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->action = app(CreateWorkOrderReceptionAction::class);
    }

    public function test_creates_contact_client_vehicle_and_work_order_in_transaction(): void
    {
        $workOrder = $this->action->execute([
            'contact_name' => 'Juan Pérez',
            'contact_phone' => '3001234567',
            'vehicle_plate' => 'ABC-123',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Corolla',
            'mileage_km' => 45000,
            'battery_level' => '12.4V',
            'aesthetic_notes' => 'Rayón en puerta izquierda',
        ]);

        $this->assertInstanceOf(WorkOrder::class, $workOrder);
        $this->assertNotNull($workOrder->contact);
        $this->assertEquals('Juan Pérez', $workOrder->contact->name);
        $this->assertNotNull($workOrder->clientVehicle);
        $this->assertEquals('ABC-123', $workOrder->clientVehicle->plate);
        $this->assertEquals(45000, $workOrder->mileage_km);
        $this->assertTrue($workOrder->status === WorkOrderStatusEnum::Received);
        $this->assertStringStartsWith('WO-', $workOrder->code);
    }

    public function test_reuses_existing_contact_when_phone_matches(): void
    {
        $existing = Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
            'phone' => '3001234567',
            'contact_type' => 'client',
        ]);

        $this->action->execute([
            'contact_name' => 'Juan Pérez',
            'contact_phone' => '3001234567',
            'vehicle_plate' => 'XYZ-999',
        ]);

        $this->assertEquals(1, Contact::count());
        $this->assertEquals('Original Name', Contact::first()->name);
    }

    public function test_reuses_existing_client_vehicle_when_plate_matches(): void
    {
        $existing = ClientVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'ABC-123',
            'brand' => 'Toyota',
            'model' => 'Original',
        ]);

        $this->action->execute([
            'contact_name' => 'Test',
            'contact_phone' => '3001111111',
            'vehicle_plate' => 'ABC-123',
            'vehicle_brand' => 'Toyota',
            'vehicle_model' => 'Corolla',
        ]);

        $this->assertEquals(1, ClientVehicle::count());
        $this->assertEquals('Original', ClientVehicle::first()->model);
    }

    public function test_tenant_isolation_does_not_find_contacts_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $manager = app(TenantManager::class);

        $manager->setTenantContext($otherTenant->id);

        Contact::create([
            'name' => 'Otro Tenant',
            'phone' => '3001234567',
            'contact_type' => 'client',
        ]);

        $manager->setTenantContext($this->tenant->id);

        $workOrder = $this->action->execute([
            'contact_name' => 'Nuevo Cliente',
            'contact_phone' => '3001234567',
            'vehicle_plate' => 'NEW-001',
        ]);

        $this->assertNotNull($workOrder->contact);
        $this->assertEquals('Nuevo Cliente', $workOrder->contact->name);
        $this->assertEquals(1, Contact::withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(1, Contact::withoutGlobalScope('tenant')->where('tenant_id', $otherTenant->id)->count());
    }

    public function test_uses_existing_contact_id_when_provided(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Existente',
            'phone' => '3009999999',
            'contact_type' => 'client',
        ]);

        $workOrder = $this->action->execute([
            'contact_id' => $contact->id,
            'vehicle_plate' => 'TEST-001',
        ]);

        $this->assertEquals($contact->id, $workOrder->contact_id);
        $this->assertEquals(1, Contact::count());
    }
}
