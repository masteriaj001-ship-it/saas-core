<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderPhase3Test extends TestCase
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

    public function test_work_order_accepts_inspection_fields(): void
    {
        $workOrder = WorkOrder::create([
            'asset_id' => $this->asset->id,
            'code' => 'WO-0001',
            'title' => 'Mantenimiento',
            'status' => 'received',
            'mileage_km' => 45000,
            'battery_level' => '12.4V',
            'aesthetic_notes' => 'Rayón en puerta izquierda',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'mileage_km' => 45000,
            'battery_level' => '12.4V',
            'aesthetic_notes' => 'Rayón en puerta izquierda',
        ]);
    }

    public function test_work_order_code_unique_per_tenant(): void
    {
        WorkOrder::create([
            'asset_id' => $this->asset->id,
            'code' => 'WO-0001',
            'title' => 'First WO',
            'status' => 'received',
        ]);

        WorkOrder::create([
            'asset_id' => $this->asset->id,
            'code' => 'WO-0002',
            'title' => 'Second WO',
            'status' => 'received',
        ]);

        $codes = WorkOrder::pluck('code')->toArray();

        $this->assertCount(2, $codes);
        $this->assertContains('WO-0001', $codes);
        $this->assertContains('WO-0002', $codes);
    }

    public function test_duplicate_contact_not_created_by_phone(): void
    {
        $first = Contact::firstOrCreate(
            ['phone' => '555-0100', 'tenant_id' => $this->tenant->id],
            ['name' => 'Juan Pérez', 'contact_type' => 'client'],
        );

        $second = Contact::firstOrCreate(
            ['phone' => '555-0100', 'tenant_id' => $this->tenant->id],
            ['name' => 'Juan Pérez', 'contact_type' => 'client'],
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('contacts', 1);
    }
}
