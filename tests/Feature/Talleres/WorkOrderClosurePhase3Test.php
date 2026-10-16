<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Tenant;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Services\WorkOrderClosureService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkOrderClosurePhase3Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private WorkOrderClosureService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->tenant = Tenant::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->service = app(WorkOrderClosureService::class);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();
        parent::tearDown();
    }

    private function makeWorkOrder(WorkOrderStatusEnum $status, array $settings = [], array $attributes = []): WorkOrder
    {
        $clientVehicle = ClientVehicle::factory()->create();
        $contact = Contact::factory()->client()->create();

        $workOrder = new WorkOrder;
        $workOrder->forceFill(array_merge([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $clientVehicle->id,
            'contact_id' => $contact->id,
            'code' => 'CLOSURE-P3-'.uniqid(),
            'title' => 'Phase 3 closure',
            'status' => $status,
            'settings' => $settings,
        ], $attributes));
        $workOrder->save();

        return $workOrder;
    }

    public function test_waiting_client_and_no_pickup_can_transition_to_breach_without_signature(): void
    {
        $waitingClient = $this->makeWorkOrder(WorkOrderStatusEnum::WaitingClient);

        $updatedWaiting = $this->service->transition($waitingClient, WorkOrderStatusEnum::Breach);

        $this->assertEquals(WorkOrderStatusEnum::Breach, $updatedWaiting->status);

        $noPickup = $this->makeWorkOrder(WorkOrderStatusEnum::NoPickup);

        $updatedNoPickup = $this->service->transition($noPickup, WorkOrderStatusEnum::Breach);

        $this->assertEquals(WorkOrderStatusEnum::Breach, $updatedNoPickup->status);
    }

    public function test_transition_stamps_closure_window_in_settings(): void
    {
        $workOrder = $this->makeWorkOrder(WorkOrderStatusEnum::WorkDone, attributes: [
            'signature_hash' => 'abc123',
            'signed_at' => now(),
        ]);

        $toWaiting = $this->service->transition($workOrder, WorkOrderStatusEnum::WaitingClient);

        $this->assertNotEmpty($toWaiting->settings['closure_window_started_at'] ?? null);

        $stamp = $toWaiting->settings['closure_window_started_at'];
        $this->assertNotFalse(
            \DateTimeImmutable::createFromFormat(DATE_ATOM, $stamp),
            "closure_window_started_at debe ser ISO-8601, se obtuvo: {$stamp}"
        );

        $toNoPickup = $this->service->transition($toWaiting, WorkOrderStatusEnum::NoPickup);

        $this->assertNotEmpty($toNoPickup->settings['closure_window_started_at'] ?? null);

        $stampNoPickup = $toNoPickup->settings['closure_window_started_at'];
        $this->assertNotFalse(
            \DateTimeImmutable::createFromFormat(DATE_ATOM, $stampNoPickup),
            "closure_window_started_at debe ser ISO-8601, se obtuvo: {$stampNoPickup}"
        );
    }
}
