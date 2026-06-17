<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Actions\CreateWorkOrderReceptionAction;
use App\Modules\Talleres\Models\Asset;
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

    public function test_creates_contact_asset_and_work_order_in_transaction(): void
    {
        $workOrder = $this->action->execute([
            'contact_name' => 'Juan Pérez',
            'contact_phone' => '3001234567',
            'asset_plate' => 'ABC-123',
            'asset_brand' => 'Toyota',
            'asset_model' => 'Corolla',
            'mileage_km' => 45000,
            'battery_level' => '12.4V',
            'aesthetic_notes' => 'Rayón en puerta izquierda',
        ]);

        $this->assertInstanceOf(WorkOrder::class, $workOrder);
        $this->assertNotNull($workOrder->contact);
        $this->assertEquals('Juan Pérez', $workOrder->contact->name);
        $this->assertNotNull($workOrder->asset);
        $this->assertEquals('ABC-123', $workOrder->asset->plate);
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
            'asset_name' => 'Vehículo Test',
        ]);

        $this->assertEquals(1, Contact::count());
        $this->assertEquals('Original Name', Contact::first()->name);
    }

    public function test_reuses_existing_asset_when_plate_matches(): void
    {
        $existing = Asset::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Toyota Original',
            'plate' => 'ABC-123',
            'asset_type' => 'vehicle',
        ]);

        $this->action->execute([
            'contact_name' => 'Test',
            'contact_phone' => '3001111111',
            'asset_plate' => 'ABC-123',
            'asset_name' => 'Toyota Corolla',
        ]);

        $this->assertEquals(1, Asset::count());
        $this->assertEquals('Toyota Original', Asset::first()->name);
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
            'asset_name' => 'Vehículo Test',
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
            'asset_name' => 'Vehículo Test',
        ]);

        $this->assertEquals($contact->id, $workOrder->contact_id);
        $this->assertEquals(1, Contact::count());
    }
}
