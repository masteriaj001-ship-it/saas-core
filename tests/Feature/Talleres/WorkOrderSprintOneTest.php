<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderSprintOneTest extends TestCase
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

    public function test_work_order_accepts_mechanic_and_advisor(): void
    {
        $mechanic = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_type' => 'employee',
        ]);
        ContactRole::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $mechanic->id,
            'role_code' => 'mechanic',
        ]);
        $advisor = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_type' => 'employee',
        ]);
        ContactRole::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $advisor->id,
            'role_code' => 'service_advisor',
        ]);

        $workOrder = WorkOrder::factory()->create([
            'mechanic_id' => $mechanic->id,
            'advisor_id' => $advisor->id,
        ]);

        $this->assertInstanceOf(Contact::class, $workOrder->mechanic);
        $this->assertEquals($mechanic->id, $workOrder->mechanic->id);
        $this->assertEquals($advisor->id, $workOrder->advisor->id);
    }

    public function test_mechanic_advisor_must_belong_to_same_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherMechanic = Contact::factory()->create([
            'tenant_id' => $otherTenant->id,
            'contact_type' => 'employee',
        ]);
        ContactRole::factory()->create([
            'tenant_id' => $otherTenant->id,
            'contact_id' => $otherMechanic->id,
            'role_code' => 'mechanic',
        ]);

        $workOrder = WorkOrder::factory()->create([
            'mechanic_id' => $otherMechanic->id,
        ]);

        $this->assertNull($workOrder->mechanic);

        $mechanicWithoutScope = $workOrder->mechanic()
            ->withoutGlobalScope('tenant')
            ->first();
        $this->assertNotNull($mechanicWithoutScope);
        $this->assertEquals($otherTenant->id, $mechanicWithoutScope->tenant_id);
    }

    public function test_new_status_enum_values_exist(): void
    {
        $this->assertTrue(WorkOrderStatusEnum::tryFrom('waiting_parts') !== null);
        $this->assertTrue(WorkOrderStatusEnum::tryFrom('waiting_approval') !== null);
        $this->assertTrue(WorkOrderStatusEnum::tryFrom('paused') !== null);
        $this->assertTrue(WorkOrderStatusEnum::tryFrom('qc') !== null);

        $this->assertEquals('Esperando Repuestos', WorkOrderStatusEnum::WaitingParts->getLabel());
        $this->assertEquals('warning', WorkOrderStatusEnum::WaitingParts->getColor());
        $this->assertEquals('Esperando Aprobación', WorkOrderStatusEnum::WaitingApproval->getLabel());
        $this->assertEquals('info', WorkOrderStatusEnum::WaitingApproval->getColor());
        $this->assertEquals('Control de Calidad', WorkOrderStatusEnum::Qc->getLabel());
        $this->assertEquals('purple', WorkOrderStatusEnum::Qc->getColor());
        $this->assertEquals('Pausada', WorkOrderStatusEnum::Paused->getLabel());
        $this->assertEquals('gray', WorkOrderStatusEnum::Paused->getColor());
    }

    public function test_work_order_accepts_qc_fields(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'qc_passed' => false,
            'qc_notes' => 'Requiere ajuste de frenos.',
            'delivery_at' => now()->addDay(),
        ]);

        $fresh = $workOrder->fresh();

        $this->assertFalse($fresh->qc_passed);
        $this->assertEquals('Requiere ajuste de frenos.', $fresh->qc_notes);
        $this->assertNotNull($fresh->delivery_at);
    }

    public function test_work_order_accepts_approval_fields(): void
    {
        $approvalAt = now()->addHours(2);

        $workOrder = WorkOrder::factory()->create([
            'approval_channel' => 'whatsapp',
            'approval_at' => $approvalAt,
        ]);

        $fresh = $workOrder->fresh();

        $this->assertEquals('whatsapp', $fresh->approval_channel);
        $this->assertEquals(
            $approvalAt->format('Y-m-d H:i'),
            $fresh->approval_at->format('Y-m-d H:i'),
        );
    }
}
