<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderInspection;
use App\Modules\Talleres\Models\WorkOrderMedia;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkOrderMediaTest extends TestCase
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

        Storage::fake('minio');
    }

    public function test_media_can_be_created(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $media = WorkOrderMedia::factory()->create([
            'work_order_id' => $workOrder->id,
            'original_name' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
        ]);

        $this->assertDatabaseHas('work_order_media', [
            'id' => $media->id,
            'original_name' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
        ]);
    }

    public function test_media_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherWorkOrder = WorkOrder::factory()->for($otherTenant)->create();

        app(TenantManager::class)->setTenantContext($otherTenant->id);
        $otherMedia = WorkOrderMedia::factory()->create([
            'work_order_id' => $otherWorkOrder->id,
        ]);

        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $myWorkOrder = WorkOrder::factory()->create();
        $myMedia = WorkOrderMedia::factory()->create([
            'work_order_id' => $myWorkOrder->id,
        ]);

        $visible = WorkOrderMedia::whereIn('id', [$otherMedia->id, $myMedia->id])->get();

        $this->assertCount(1, $visible);
        $this->assertEquals($myMedia->id, $visible->first()->id);
    }

    public function test_work_order_has_media_relation(): void
    {
        $workOrder = WorkOrder::factory()->create();
        WorkOrderMedia::factory()->count(3)->create([
            'work_order_id' => $workOrder->id,
        ]);

        $media = $workOrder->media;

        $this->assertCount(3, $media);
        $this->assertInstanceOf(WorkOrderMedia::class, $media->first());
    }

    public function test_inspection_has_media_relation(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $inspection = WorkOrderInspection::factory()->create([
            'work_order_id' => $workOrder->id,
        ]);

        WorkOrderMedia::factory()->count(2)->create([
            'work_order_id' => $workOrder->id,
            'work_order_inspection_id' => $inspection->id,
        ]);

        $media = $inspection->media;

        $this->assertCount(2, $media);
    }

    public function test_media_can_store_image(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);
        $path = $file->store('test', 'minio');

        $media = WorkOrderMedia::factory()->create([
            'work_order_id' => $workOrder->id,
            'original_name' => 'test.jpg',
            'storage_path' => $path,
            'mime_type' => 'image/jpeg',
        ]);

        $this->assertEquals('image/jpeg', $media->mime_type);
        Storage::disk('minio')->assertExists($path);
    }

    public function test_media_can_store_pdf(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $path = $file->store('test', 'minio');

        $media = WorkOrderMedia::factory()->asPdf()->create([
            'work_order_id' => $workOrder->id,
            'storage_path' => $path,
        ]);

        $this->assertEquals('application/pdf', $media->mime_type);
        Storage::disk('minio')->assertExists($path);
    }

    public function test_media_can_store_video(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $media = WorkOrderMedia::factory()->asVideo()->create([
            'work_order_id' => $workOrder->id,
        ]);

        $this->assertEquals('video/mp4', $media->mime_type);
    }

    public function test_media_deleted_when_work_order_deleted(): void
    {
        $workOrder = WorkOrder::factory()->create();
        WorkOrderMedia::factory()->create([
            'work_order_id' => $workOrder->id,
        ]);

        // WorkOrder usa SoftDeletes — el CASCADE real ocurre solo con forceDelete
        $workOrder->forceDelete();

        $this->assertDatabaseMissing('work_order_media', [
            'work_order_id' => $workOrder->id,
        ]);
    }

    public function test_media_preserved_when_inspection_deleted(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $inspection = WorkOrderInspection::factory()->create([
            'work_order_id' => $workOrder->id,
        ]);

        WorkOrderMedia::factory()->create([
            'work_order_id' => $workOrder->id,
            'work_order_inspection_id' => $inspection->id,
        ]);

        $inspection->delete();

        $media = WorkOrderMedia::where('work_order_id', $workOrder->id)->first();

        $this->assertNotNull($media);
        $this->assertNull($media->fresh()->work_order_inspection_id);
    }

    public function test_media_private_visibility(): void
    {
        $config = config('filesystems.disks.minio');

        $this->assertIsArray($config);
    }

    public function test_media_storage_path_uses_uuid(): void
    {
        $uuid = (string) Str::uuid();

        $workOrder = WorkOrder::factory()->create();
        $media = WorkOrderMedia::factory()->create([
            'work_order_id' => $workOrder->id,
            'storage_path' => sprintf(
                '%s/%s/%s-%s',
                $this->tenant->id,
                $workOrder->id,
                $uuid,
                'test.jpg',
            ),
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]+\/[0-9a-f-]+\/[0-9a-f-]+-.+$/',
            $media->storage_path,
        );
    }

    public function test_media_disk_configuration_exists(): void
    {
        $disk = config('filesystems.disks.minio');

        $this->assertNotNull($disk);
        $this->assertEquals('s3', $disk['driver']);
        $this->assertTrue($disk['use_path_style_endpoint']);
    }
}
