<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderChecklistStatusEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderChecklistItem;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderChecklistAppScopeTest extends TestCase
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

    public function test_checklist_item_can_be_created(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $item = WorkOrderChecklistItem::factory()->create([
            'work_order_id' => $workOrder->id,
            'task' => 'Cambiar aceite',
            'status' => WorkOrderChecklistStatusEnum::Pending,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('work_order_checklist_items', [
            'id' => $item->id,
            'task' => 'Cambiar aceite',
            'status' => 'pending',
            'position' => 1,
        ]);
    }

    public function test_checklist_item_status_transition_pending_to_done_to_ok(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $item = WorkOrderChecklistItem::factory()->create([
            'work_order_id' => $workOrder->id,
            'status' => WorkOrderChecklistStatusEnum::Pending,
        ]);

        $item->update(['status' => WorkOrderChecklistStatusEnum::Done]);
        $this->assertEquals('done', $item->fresh()->status->value);

        $item->update(['status' => WorkOrderChecklistStatusEnum::Ok]);
        $this->assertEquals('ok', $item->fresh()->status->value);
    }

    public function test_checklist_item_soft_delete_restores(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $item = WorkOrderChecklistItem::factory()->create([
            'work_order_id' => $workOrder->id,
        ]);

        $item->delete();
        $this->assertNotNull($item->fresh()->deleted_at);
        $this->assertNull(WorkOrderChecklistItem::find($item->id));

        $item->restore();
        $this->assertNotNull(WorkOrderChecklistItem::find($item->id));
    }

    public function test_checklist_item_assigned_to_is_nullable(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $item = WorkOrderChecklistItem::factory()->create([
            'work_order_id' => $workOrder->id,
            'assigned_to' => null,
        ]);

        $this->assertDatabaseHas('work_order_checklist_items', [
            'id' => $item->id,
            'assigned_to' => null,
        ]);
    }

    public function test_checklist_items_ordered_by_position(): void
    {
        $workOrder = WorkOrder::factory()->create();

        WorkOrderChecklistItem::factory()->create([
            'work_order_id' => $workOrder->id,
            'task' => 'B',
            'position' => 2,
        ]);

        WorkOrderChecklistItem::factory()->create([
            'work_order_id' => $workOrder->id,
            'task' => 'A',
            'position' => 1,
        ]);

        $items = $workOrder->checklistItems;

        $this->assertCount(2, $items);
        $this->assertEquals('A', $items->first()->task);
        $this->assertEquals('B', $items->last()->task);
    }
}
