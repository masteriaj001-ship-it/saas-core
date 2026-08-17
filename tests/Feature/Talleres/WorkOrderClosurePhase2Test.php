<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderChecklistStatusEnum;
use App\Enums\WorkOrderMediaStageEnum;
use App\Enums\WorkOrderStatusEnum;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Tenant;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderChecklistItem;
use App\Modules\Talleres\Models\WorkOrderMedia;
use App\Modules\Talleres\Services\WorkOrderClosureService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class WorkOrderClosurePhase2Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private WorkOrder $workOrder;

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

        $asset = Asset::factory()->create();
        $contact = Contact::factory()->create();

        $this->workOrder = new WorkOrder;
        $this->workOrder->forceFill([
            'tenant_id' => $this->tenant->id,
            'asset_id' => $asset->id,
            'contact_id' => $contact->id,
            'code' => 'CLOSURE-P2-001',
            'title' => 'Phase 2 closure',
            'status' => WorkOrderStatusEnum::InProgress,
        ]);
        $this->workOrder->save();

        $this->service = app(WorkOrderClosureService::class);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();
        parent::tearDown();
    }

    private function addCompleteChecklist(): void
    {
        WorkOrderChecklistItem::factory()->ok()->create([
            'work_order_id' => $this->workOrder->id,
        ]);
        WorkOrderChecklistItem::factory()->done()->create([
            'work_order_id' => $this->workOrder->id,
        ]);
    }

    private function addBeforeAfterPhotos(): void
    {
        WorkOrderMedia::factory()->asBefore()->create([
            'work_order_id' => $this->workOrder->id,
        ]);
        WorkOrderMedia::factory()->create([
            'work_order_id' => $this->workOrder->id,
        ]);
    }

    // Test 1: work_done se bloquea con ítem pendiente en checklist final
    public function test_closing_requires_complete_checklist(): void
    {
        WorkOrderChecklistItem::factory()->create([
            'work_order_id' => $this->workOrder->id,
            'status' => WorkOrderChecklistStatusEnum::Pending,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checklist final');

        $this->service->transition($this->workOrder, WorkOrderStatusEnum::WorkDone);
    }

    // Test 2: work_done se bloquea sin fotos antes/después
    public function test_closing_requires_before_after_photos(): void
    {
        $this->addCompleteChecklist();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fotos antes y después');

        $this->service->transition($this->workOrder, WorkOrderStatusEnum::WorkDone);
    }

    // Test 3: happy path con checklist completa + fotos antes/después
    public function test_closing_valid_with_full_evidence(): void
    {
        $this->addCompleteChecklist();
        $this->addBeforeAfterPhotos();

        $updated = $this->service->transition($this->workOrder, WorkOrderStatusEnum::WorkDone);

        $this->assertEquals(WorkOrderStatusEnum::WorkDone, $updated->status);
        $this->assertDatabaseHas('work_order_activities', [
            'work_order_id' => $this->workOrder->id,
            'type' => 'status_change',
        ]);
    }

    // Test 4: legacy closure salta validaciones
    public function test_legacy_closure_skips_validation(): void
    {
        $this->workOrder->forceFill([
            'status' => WorkOrderStatusEnum::WorkDone,
            'settings' => ['is_legacy_closure' => true],
        ])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('legacy');

        $this->service->transition(
            $this->workOrder->fresh(),
            WorkOrderStatusEnum::Completed,
        );
    }

    // Test 5: stage se guarda y castea correctamente
    public function test_media_stage_stored_correctly(): void
    {
        $media = WorkOrderMedia::factory()->asBefore()->create([
            'work_order_id' => $this->workOrder->id,
        ]);

        $fresh = WorkOrderMedia::withoutTenantScope()
            ->where('id', $media->id)
            ->first();

        $this->assertEquals(WorkOrderMediaStageEnum::Before, $fresh->stage);
    }

    // Test 6: completed exige firma aunque haya checklist y fotos
    public function test_completed_requires_signature(): void
    {
        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingClient]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('firma');

        $this->service->transition(
            $this->workOrder->fresh(),
            WorkOrderStatusEnum::Completed,
        );
    }

    // Test 7: RLS aísla work_order_media con stage por tenant
    public function test_media_stage_rls_isolation(): void
    {
        if (! config('database.connections.pgsql-rls')) {
            $this->markTestSkipped('pgsql-rls connection not configured.');
        }

        $tenantB = Tenant::factory()->create(['name' => 'RLS Tenant B']);

        WorkOrderMedia::factory()->asBefore()->create([
            'work_order_id' => $this->workOrder->id,
        ]);

        DB::connection('pgsql-rls')->statement(
            "SELECT set_config('app.current_tenant_id', ?, false)", [$tenantB->id]
        );

        $rows = DB::connection('pgsql-rls')
            ->table('work_order_media')
            ->where('work_order_id', $this->workOrder->id)
            ->get();

        $this->assertCount(0, $rows, 'Tenant B should not see Tenant A media.');
    }
}
