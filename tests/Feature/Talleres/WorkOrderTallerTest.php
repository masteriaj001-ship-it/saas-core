<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\VehicleTypeEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
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
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);

        $this->seed(RolePermissionSeeder::class);
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

    public function test_can_create_asset_inline_from_work_order_form(): void
    {
        // Simula el callback createOptionUsing del Select asset_id
        $asset = Asset::create([
            'name' => 'Inline Vehicle',
            'plate' => 'XYZ-987',
            'vehicle_type' => VehicleTypeEnum::Sedan,
            'asset_type' => 'vehicle',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'name' => 'Inline Vehicle',
            'plate' => 'XYZ-987',
            'asset_type' => 'vehicle',
            'status' => 'active',
        ]);
    }
}
