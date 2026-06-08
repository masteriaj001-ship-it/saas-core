<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\InspectionItemStatusEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderInspection;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderInspectionTest extends TestCase
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
    }

    public function test_work_order_inspection_can_be_created(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $inspection = WorkOrderInspection::factory()->create([
            'work_order_id' => $workOrder->id,
            'item_name' => 'Parabrisas',
            'status' => InspectionItemStatusEnum::Ok,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('work_order_inspections', [
            'id' => $inspection->id,
            'item_name' => 'Parabrisas',
            'status' => 'ok',
            'sort_order' => 1,
        ]);
    }

    public function test_work_order_inspection_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherWorkOrder = WorkOrder::factory()->for($otherTenant)->create();

        app(TenantManager::class)->setTenantContext($otherTenant->id);
        $otherInspection = WorkOrderInspection::factory()->create([
            'work_order_id' => $otherWorkOrder->id,
        ]);

        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $myWorkOrder = WorkOrder::factory()->create();
        $myInspection = WorkOrderInspection::factory()->create([
            'work_order_id' => $myWorkOrder->id,
        ]);

        $visible = WorkOrderInspection::whereIn('id', [$otherInspection->id, $myInspection->id])->get();

        $this->assertCount(1, $visible);
        $this->assertEquals($myInspection->id, $visible->first()->id);
    }

    public function test_work_order_has_inspections_relation(): void
    {
        $workOrder = WorkOrder::factory()->create();

        WorkOrderInspection::factory()->create([
            'work_order_id' => $workOrder->id,
            'sort_order' => 2,
            'item_name' => 'B',
        ]);

        WorkOrderInspection::factory()->create([
            'work_order_id' => $workOrder->id,
            'sort_order' => 1,
            'item_name' => 'A',
        ]);

        $inspections = $workOrder->inspections;

        $this->assertCount(2, $inspections);
        $this->assertEquals('A', $inspections->first()->item_name);
        $this->assertEquals('B', $inspections->last()->item_name);
    }

    public function test_inspection_status_enum_has_three_cases(): void
    {
        $cases = InspectionItemStatusEnum::cases();

        $this->assertCount(3, $cases);
        $this->assertTrue(InspectionItemStatusEnum::tryFrom('ok') !== null);
        $this->assertTrue(InspectionItemStatusEnum::tryFrom('damaged') !== null);
        $this->assertTrue(InspectionItemStatusEnum::tryFrom('missing') !== null);
    }

    public function test_inspection_defaults_config_exists(): void
    {
        $defaults = config('inspection-defaults.mechanic');

        $this->assertNotNull($defaults);
        $this->assertIsArray($defaults);
        $this->assertNotEmpty($defaults);
        $this->assertContains('Parabrisas', $defaults);
    }
}
