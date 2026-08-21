<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFlowTest extends TestCase
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

    public function test_can_create_client_contact(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Juan Perez',
            'phone' => '555-0100',
            'email' => 'juan@example.com',
            'contact_type' => 'client',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Juan Perez',
            'contact_type' => 'client',
        ]);
    }

    public function test_can_create_supplier_contact(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Repuestos SA',
            'contact_type' => 'supplier',
        ]);

        $this->assertEquals('supplier', $contact->contact_type);
    }

    public function test_can_create_employee_contact(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Carlos Mecanico',
            'contact_type' => 'employee',
        ]);

        $this->assertEquals('employee', $contact->contact_type);
    }

    public function test_contact_has_phone(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Contact',
            'phone' => '+54 11 5555-1234',
            'contact_type' => 'client',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'phone' => '+54 11 5555-1234',
        ]);
    }

    public function test_contact_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Contact A',
            'contact_type' => 'client',
        ]);

        Contact::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Contact B',
            'contact_type' => 'client',
        ]);

        $this->assertEquals(1, Contact::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(1, Contact::where('tenant_id', $otherTenant->id)->count());
    }

    public function test_contact_can_be_linked_to_work_order(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Frecuente',
            'contact_type' => 'client',
        ]);

        $vehicle = ClientVehicle::factory()->for($this->tenant)->create();

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'client_vehicle_id' => $vehicle->id,
            'code' => 'WO-CT-001',
            'title' => 'Servicio con contacto',
            'status' => 'draft',
        ]);

        $this->assertEquals($contact->id, $workOrder->contact_id);
        $this->assertEquals('Cliente Frecuente', $workOrder->contact->name);
    }

    public function test_contact_can_have_multiple_work_orders(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente VIP',
            'contact_type' => 'client',
        ]);

        $vehicle = ClientVehicle::factory()->for($this->tenant)->create();

        WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'client_vehicle_id' => $vehicle->id,
            'code' => 'WO-CT-002',
            'title' => 'Primera visita',
            'status' => 'completed',
        ]);

        WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'client_vehicle_id' => $vehicle->id,
            'code' => 'WO-CT-003',
            'title' => 'Segunda visita',
            'status' => 'draft',
        ]);

        $this->assertEquals(2, WorkOrder::where('contact_id', $contact->id)->count());
    }

    public function test_client_contact_creation_inline(): void
    {
        $contact = Contact::create([
            'name' => 'Nuevo Cliente',
            'phone' => '555-9999',
            'contact_type' => 'client',
        ]);

        $this->assertDatabaseHas('contacts', [
            'name' => 'Nuevo Cliente',
            'contact_type' => 'client',
        ]);
    }
}
