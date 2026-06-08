<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderActivityTypeEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderActivity;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderActivityTest extends TestCase
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

    public function test_work_order_activity_can_be_created(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $activity = WorkOrderActivity::factory()->create([
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'type' => WorkOrderActivityTypeEnum::StatusChange,
            'description' => 'OT creada',
            'from_status' => null,
            'to_status' => 'received',
            'metadata' => ['reason' => 'Ingreso por mostrador'],
        ]);

        $this->assertDatabaseHas('work_order_activities', [
            'id' => $activity->id,
            'type' => 'status_change',
            'description' => 'OT creada',
            'to_status' => 'received',
        ]);

        $this->assertEquals(
            ['reason' => 'Ingreso por mostrador'],
            $activity->fresh()->metadata
        );
    }

    public function test_work_order_activity_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherWorkOrder = WorkOrder::factory()->for($otherTenant)->create();

        app(TenantManager::class)->setTenantContext($otherTenant->id);
        $otherActivity = WorkOrderActivity::factory()->create([
            'work_order_id' => $otherWorkOrder->id,
        ]);

        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $myWorkOrder = WorkOrder::factory()->create();
        $myActivity = WorkOrderActivity::factory()->create([
            'work_order_id' => $myWorkOrder->id,
        ]);

        $visible = WorkOrderActivity::whereIn('id', [$otherActivity->id, $myActivity->id])->get();

        $this->assertCount(1, $visible);
        $this->assertEquals($myActivity->id, $visible->first()->id);
    }

    public function test_work_order_has_activities_relation(): void
    {
        $workOrder = WorkOrder::factory()->create();
        WorkOrderActivity::factory()->count(3)->create([
            'work_order_id' => $workOrder->id,
        ]);

        $activities = $workOrder->activities;

        $this->assertCount(3, $activities);
        $this->assertInstanceOf(WorkOrderActivity::class, $activities->first());
    }

    public function test_activity_type_enum_has_four_cases(): void
    {
        $cases = WorkOrderActivityTypeEnum::cases();

        $this->assertCount(4, $cases);
        $this->assertTrue(WorkOrderActivityTypeEnum::tryFrom('status_change') !== null);
        $this->assertTrue(WorkOrderActivityTypeEnum::tryFrom('note') !== null);
        $this->assertTrue(WorkOrderActivityTypeEnum::tryFrom('assignment') !== null);
        $this->assertTrue(WorkOrderActivityTypeEnum::tryFrom('qc') !== null);
    }
}
