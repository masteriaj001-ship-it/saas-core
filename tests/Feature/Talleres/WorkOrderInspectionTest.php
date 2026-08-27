<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Exceptions\InspectionIncompleteException;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderMedia;
use App\Modules\Talleres\Services\WorkOrderClosureService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkOrderInspectionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private WorkOrder $workOrder;

    private WorkOrderClosureService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->tenant = Tenant::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->user = User::factory()->create();

        $clientVehicle = ClientVehicle::factory()->create();
        $contact = Contact::factory()->create();

        $this->workOrder = new WorkOrder;
        $this->workOrder->forceFill([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $clientVehicle->id,
            'contact_id' => $contact->id,
            'code' => 'INSPECT-001',
            'title' => 'Inspection test',
            'status' => WorkOrderStatusEnum::Draft,
        ]);
        $this->workOrder->save();

        $this->service = app(WorkOrderClosureService::class);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();
        parent::tearDown();
    }

    private function addCompleteInspection(): void
    {
        $this->workOrder->update([
            'inspection_checklist' => [
                'body' => ['front' => 'ok', 'rear' => 'scratch', 'left' => 'ok', 'right' => 'ok'],
                'glass' => ['windshield' => 'ok', 'rear_window' => 'ok', 'left_window' => 'crack', 'right_window' => 'ok'],
            ],
            'inspection_completed_at' => now(),
        ]);
    }

    private function addEntryPhotos(int $count = 4): void
    {
        for ($i = 0; $i < $count; $i++) {
            WorkOrderMedia::factory()->asBefore()->create([
                'work_order_id' => $this->workOrder->id,
            ]);
        }
    }

    public function test_can_complete_inspection_with_valid_checklist(): void
    {
        $this->addCompleteInspection();

        $this->assertTrue($this->workOrder->fresh()->hasCompletedInspection());
    }

    public function test_transition_draft_received_without_inspection_throws_exception(): void
    {
        $this->addEntryPhotos(4);

        $this->expectException(InspectionIncompleteException::class);
        $this->expectExceptionMessage('inspección de ingreso');

        $this->service->transitionToReceived($this->workOrder, $this->user);
    }

    public function test_transition_draft_received_without_photos_throws_exception(): void
    {
        $this->addCompleteInspection();

        $this->expectException(InspectionIncompleteException::class);
        $this->expectExceptionMessage('4 fotos de ingreso');

        $this->service->transitionToReceived($this->workOrder, $this->user);
    }

    public function test_transition_ok_with_inspection_and_photos(): void
    {
        $this->addCompleteInspection();
        $this->addEntryPhotos(4);

        $updated = $this->service->transitionToReceived($this->workOrder, $this->user);

        $this->assertEquals(WorkOrderStatusEnum::Received, $updated->status);
        $this->assertEquals($this->user->id, $updated->inspection_completed_by);
        $this->assertDatabaseHas('work_order_activities', [
            'work_order_id' => $this->workOrder->id,
            'type' => 'status_change',
            'to_status' => 'received',
        ]);
    }

    public function test_inspection_completed_at_set_automatically(): void
    {
        $this->addCompleteInspection();
        $this->addEntryPhotos(4);

        $this->service->transitionToReceived($this->workOrder, $this->user);

        $this->assertNotNull($this->workOrder->fresh()->inspection_completed_at);
    }

    public function test_actual_started_at_set_on_transition_to_inprogress(): void
    {
        $this->addCompleteInspection();
        $this->addEntryPhotos(4);
        $this->service->transitionToReceived($this->workOrder, $this->user);

        $this->workOrder->update(['status' => WorkOrderStatusEnum::InProgress]);

        $this->assertNotNull($this->workOrder->fresh()->actual_started_at);
    }

    public function test_actual_completed_at_set_on_transition_to_workdone(): void
    {
        $this->addCompleteInspection();
        $this->addEntryPhotos(4);
        $this->service->transitionToReceived($this->workOrder, $this->user);
        $this->workOrder->update(['status' => WorkOrderStatusEnum::InProgress]);
        $this->workOrder->update(['status' => WorkOrderStatusEnum::WorkDone]);

        $this->assertNotNull($this->workOrder->fresh()->actual_completed_at);
    }

    public function test_transition_from_non_draft_throws_exception(): void
    {
        $this->workOrder->update(['status' => WorkOrderStatusEnum::Received]);
        $this->addCompleteInspection();
        $this->addEntryPhotos(4);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('solo desde draft');

        $this->service->transitionToReceived($this->workOrder->fresh(), $this->user);
    }
}
