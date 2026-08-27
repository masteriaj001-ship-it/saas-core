<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Tenant;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class WorkOrderFieldCleanupTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->tenant = Tenant::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();
        parent::tearDown();
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        $clientVehicle = ClientVehicle::factory()->create();
        $contact = Contact::factory()->create();

        $wo = new WorkOrder;
        $wo->forceFill([
            'tenant_id' => $this->tenant->id,
            'client_vehicle_id' => $clientVehicle->id,
            'contact_id' => $contact->id,
            'code' => 'FIELD-TEST-'.fake()->unique()->numerify('###'),
            'title' => 'Field cleanup test',
            'status' => WorkOrderStatusEnum::Draft,
            ...$overrides,
        ]);
        $wo->save();

        return $wo->fresh();
    }

    public function test_client_report_replaces_service_description(): void
    {
        $wo = $this->createWorkOrder(['client_report' => 'El cliente reporta ruido en el freno']);

        $this->assertEquals('El cliente reporta ruido en el freno', $wo->client_report);
        $this->assertDatabaseHas('work_orders', [
            'id' => $wo->id,
            'client_report' => 'El cliente reporta ruido en el freno',
        ]);
    }

    public function test_internal_notes_replaces_description(): void
    {
        $wo = $this->createWorkOrder(['internal_notes' => 'Revisar caliber del freno']);

        $this->assertEquals('Revisar caliber del freno', $wo->internal_notes);
    }

    public function test_fillable_includes_new_fields(): void
    {
        $wo = new WorkOrder;

        $this->assertContains('client_report', $wo->getFillable());
        $this->assertContains('internal_notes', $wo->getFillable());
        $this->assertContains('inspection_checklist', $wo->getFillable());
        $this->assertContains('inspection_completed_at', $wo->getFillable());
        $this->assertContains('inspection_completed_by', $wo->getFillable());
    }

    public function test_fillable_excludes_removed_fields(): void
    {
        $wo = new WorkOrder;

        $this->assertNotContains('location_id', $wo->getFillable());
    }

    public function test_inspection_checklist_cast_to_array(): void
    {
        $checklist = [
            'body' => ['front' => 'ok', 'rear' => 'scratch', 'left' => 'ok', 'right' => 'ok'],
            'glass' => ['windshield' => 'ok', 'rear_window' => 'ok', 'left_window' => 'ok', 'right_window' => 'crack'],
        ];

        $wo = $this->createWorkOrder(['inspection_checklist' => $checklist]);

        $this->assertIsArray($wo->inspection_checklist);
        $this->assertEquals('scratch', $wo->inspection_checklist['body']['rear']);
        $this->assertEquals('crack', $wo->inspection_checklist['glass']['right_window']);
    }

    public function test_timestamps_operativos_casteados_a_datetime(): void
    {
        $wo = $this->createWorkOrder([
            'estimated_completion_at' => now()->addDays(3),
            'actual_started_at' => now(),
            'actual_completed_at' => now()->addDays(2),
        ]);

        $this->assertInstanceOf(Carbon::class, $wo->estimated_completion_at);
        $this->assertInstanceOf(Carbon::class, $wo->actual_started_at);
        $this->assertInstanceOf(Carbon::class, $wo->actual_completed_at);
    }

    public function test_is_on_time_returns_correct_status(): void
    {
        $wo = $this->createWorkOrder(['estimated_completion_at' => now()->addDays(1)]);
        $this->assertEquals('on_time', $wo->isOnTime());

        $woAtRisk = $this->createWorkOrder(['estimated_completion_at' => now()->subHour()]);
        $this->assertEquals('at_risk', $woAtRisk->isOnTime());

        $woOverdue = $this->createWorkOrder(['estimated_completion_at' => now()->subHours(5)]);
        $this->assertEquals('overdue', $woOverdue->isOnTime());
    }

    public function test_has_completed_inspection_with_valid_checklist(): void
    {
        $wo = $this->createWorkOrder([
            'inspection_checklist' => [
                'body' => ['front' => 'ok', 'rear' => 'ok', 'left' => 'ok', 'right' => 'ok'],
                'glass' => ['windshield' => 'ok', 'rear_window' => 'ok', 'left_window' => 'ok', 'right_window' => 'ok'],
            ],
            'inspection_completed_at' => now(),
        ]);

        $this->assertTrue($wo->hasCompletedInspection());
    }

    public function test_has_completed_inspection_returns_false_without_body(): void
    {
        $wo = $this->createWorkOrder([
            'inspection_checklist' => [
                'glass' => ['windshield' => 'ok'],
            ],
            'inspection_completed_at' => now(),
        ]);

        $this->assertFalse($wo->hasCompletedInspection());
    }

    public function test_has_completed_inspection_returns_false_without_glass(): void
    {
        $wo = $this->createWorkOrder([
            'inspection_checklist' => [
                'body' => ['front' => 'ok'],
            ],
            'inspection_completed_at' => now(),
        ]);

        $this->assertFalse($wo->hasCompletedInspection());
    }

    public function test_has_completed_inspection_returns_false_without_timestamp(): void
    {
        $wo = $this->createWorkOrder([
            'inspection_checklist' => [
                'body' => ['front' => 'ok'],
                'glass' => ['windshield' => 'ok'],
            ],
        ]);

        $this->assertFalse($wo->hasCompletedInspection());
    }
}
