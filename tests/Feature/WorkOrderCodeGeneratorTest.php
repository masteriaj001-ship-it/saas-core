<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\ClientVehicle;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Services\WorkOrderCodeGenerator;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private ClientVehicle $clientVehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();
        $this->clientVehicle = ClientVehicle::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_generates_first_code_as_wo_0001(): void
    {
        $generator = app(WorkOrderCodeGenerator::class);

        $code = $generator->next();

        $this->assertSame('WO-0001', $code);
    }

    public function test_generates_sequential_codes(): void
    {
        $generator = app(WorkOrderCodeGenerator::class);

        $this->assertSame('WO-0001', $generator->next());

        WorkOrder::create([
            'client_vehicle_id' => $this->clientVehicle->id,
            'code' => 'WO-0001',
            'title' => 'First WO',
            'status' => 'draft',
        ]);

        $this->assertSame('WO-0002', $generator->next());

        WorkOrder::create([
            'client_vehicle_id' => $this->clientVehicle->id,
            'code' => 'WO-0002',
            'title' => 'Second WO',
            'status' => 'draft',
        ]);

        $this->assertSame('WO-0003', $generator->next());
    }

    public function test_generates_code_after_last_existing(): void
    {
        WorkOrder::create([
            'client_vehicle_id' => $this->clientVehicle->id,
            'code' => 'WO-0042',
            'title' => 'Existing WO',
            'status' => 'draft',
        ]);

        $generator = app(WorkOrderCodeGenerator::class);

        $this->assertSame('WO-0043', $generator->next());
    }

    public function test_considers_soft_deleted_records(): void
    {
        $workOrder = WorkOrder::create([
            'client_vehicle_id' => $this->clientVehicle->id,
            'code' => 'WO-0010',
            'title' => 'To be deleted',
            'status' => 'draft',
        ]);
        $workOrder->delete();

        $generator = app(WorkOrderCodeGenerator::class);

        $this->assertSame('WO-0011', $generator->next());
    }
}
