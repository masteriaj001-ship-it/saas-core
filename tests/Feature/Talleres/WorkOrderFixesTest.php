<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\InspectionItemStatusEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ServiceCatalog;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use App\Modules\Talleres\Services\WorkOrderCodeGenerator;
use App\Services\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderFixesTest extends TestCase
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
    }

    public function test_items_relation_shows_service_catalog_name(): void
    {
        $serviceCatalog = ServiceCatalog::factory()->for($this->tenant)->create([
            'name' => 'Cambio de aceite sintético',
        ]);

        $workOrder = WorkOrder::factory()->for($this->tenant)->create([
            'code' => app(WorkOrderCodeGenerator::class)->next(),
            'title' => 'Mantenimiento',
            'status' => 'received',
        ]);

        $serviceItem = WorkOrderItem::factory()
            ->for($this->tenant)
            ->for($workOrder, 'workOrder')
            ->asService()
            ->create([
                'service_catalog_id' => $serviceCatalog->id,
            ]);

        $this->assertEquals('Cambio de aceite sintético', $serviceItem->serviceCatalog->name);
        $this->assertNull($serviceItem->item);
    }

    public function test_inspection_defaults_created_on_work_order_creation(): void
    {
        $defaults = config('inspection-defaults.mechanic', []);

        $this->assertNotEmpty($defaults, 'inspection-defaults.mechanic should not be empty');

        $workOrder = WorkOrder::factory()->for($this->tenant)->create([
            'code' => app(WorkOrderCodeGenerator::class)->next(),
            'title' => 'Inspección completa',
            'status' => 'received',
        ]);

        foreach ($defaults as $index => $itemName) {
            $workOrder->inspections()->create([
                'item_name' => $itemName,
                'status' => InspectionItemStatusEnum::Ok,
                'sort_order' => $index,
            ]);
        }

        $inspections = $workOrder->inspections()->orderBy('sort_order')->get();

        $this->assertCount(count($defaults), $inspections);

        foreach ($inspections as $i => $inspection) {
            $this->assertEquals($defaults[$i], $inspection->item_name);
            $this->assertEquals(InspectionItemStatusEnum::Ok, $inspection->status);
            $this->assertEquals($i, $inspection->sort_order);
        }
    }
}
