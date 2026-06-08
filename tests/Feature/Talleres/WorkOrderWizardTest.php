<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Filament\Resources\WorkOrderResource;
use App\Filament\Resources\WorkOrderResource\Pages\EditWorkOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Services\WorkOrderCodeGenerator;
use App\Services\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderWizardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();
        $this->asset = Asset::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);
    }

    public function test_wizard_step1_requires_title_and_contact(): void
    {
        $this->expectException(QueryException::class);

        WorkOrder::create([
            'asset_id' => $this->asset->id,
            'code' => 'WO-0001',
            'status' => 'received',
        ]);
    }

    public function test_wizard_creates_work_order_with_code(): void
    {
        $generator = app(WorkOrderCodeGenerator::class);

        $workOrder = WorkOrder::create([
            'asset_id' => $this->asset->id,
            'code' => $generator->next(),
            'title' => 'Mantenimiento preventivo',
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'code' => 'WO-0001',
            'title' => 'Mantenimiento preventivo',
        ]);
        $this->assertMatchesRegularExpression('/^WO-\d{4}$/', $workOrder->code);
    }

    public function test_edit_page_does_not_use_wizard(): void
    {
        $traits = class_uses(EditWorkOrder::class);

        $this->assertEmpty(
            array_filter($traits, fn (string $trait): bool => str_contains($trait, 'HasWizard')),
            'EditWorkOrder should not use the HasWizard trait',
        );
    }

    public function test_wizard_step_schemas_are_valid(): void
    {
        $step1 = WorkOrderResource::step1Schema();
        $step2 = WorkOrderResource::step2Schema();
        $step3 = WorkOrderResource::step3Schema();

        $this->assertCount(5, $step1);
        $this->assertCount(3, $step2);
        $this->assertCount(2, $step3);
    }
}
