<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderActivityTypeEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkOrderObserverTest extends TestCase
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

    public function test_status_change_creates_activity(): void
    {
        $workOrder = WorkOrder::factory()->draft()->create();

        $workOrder->update(['status' => 'received']);

        $this->assertDatabaseHas('work_order_activities', [
            'work_order_id' => $workOrder->id,
            'type' => 'status_change',
            'user_id' => $this->user->id,
        ]);

        $activities = $workOrder->activities;
        $this->assertCount(1, $activities);
        $this->assertEquals(WorkOrderActivityTypeEnum::StatusChange, $activities->first()->type);
    }

    public function test_activity_records_from_and_to_status(): void
    {
        $workOrder = WorkOrder::factory()->draft()->create();

        $workOrder->update(['status' => 'received']);

        $activity = $workOrder->activities()->first();

        $this->assertNotNull($activity);
        $this->assertEquals('draft', $activity->from_status);
        $this->assertEquals('received', $activity->to_status);
        $this->assertStringContainsStringIgnoringCase('recibido', $activity->description);
    }

    public function test_no_activity_created_without_status_change(): void
    {
        $workOrder = WorkOrder::factory()->draft()->create();

        $workOrder->update(['title' => 'Título actualizado']);

        $this->assertDatabaseMissing('work_order_activities', [
            'work_order_id' => $workOrder->id,
        ]);
    }

    public function test_webhook_not_dispatched_without_url(): void
    {
        Http::fake();

        config()->set('talleres.webhook_url', null);

        $workOrder = WorkOrder::factory()->draft()->create();

        $workOrder->update(['status' => 'received']);

        Http::assertNothingSent();
    }
}
