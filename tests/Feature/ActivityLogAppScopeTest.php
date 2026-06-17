<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogAppScopeTest extends TestCase
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

    public function test_creating_model_logs_activity(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $workOrder->getMorphClass(),
            'subject_id' => $workOrder->id,
            'event' => 'created',
            'causer_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_updating_model_logs_attribute_changes(): void
    {
        $contact = Contact::factory()->create();

        $contact->update(['name' => 'Updated Name']);

        $activity = Activity::where('subject_type', $contact->getMorphClass())
            ->where('subject_id', $contact->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity, 'Update should be logged.');
        $this->assertArrayHasKey('old', $activity->attribute_changes ?? []);
        $this->assertArrayHasKey('attributes', $activity->attribute_changes ?? []);
    }

    public function test_deleting_model_logs_deletion(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $workOrder->delete();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $workOrder->getMorphClass(),
            'subject_id' => $workOrder->id,
            'event' => 'deleted',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_activity_log_has_causer(): void
    {
        $asset = Asset::factory()->create();

        $activity = Activity::where('subject_type', $asset->getMorphClass())
            ->where('subject_id', $asset->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->user->id, $activity->causer_id);
        $this->assertEquals($this->user->getMorphClass(), $activity->causer_type);
    }

    public function test_activity_log_is_scoped_to_correct_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->for($otherTenant)->create();

        app(TenantManager::class)->setTenantContext($otherTenant->id);
        $this->actingAs($otherUser);
        WorkOrder::factory()->create();

        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->actingAs($this->user);

        $otherTenantActivities = Activity::withoutTenantScope()
            ->where('tenant_id', $otherTenant->id)
            ->count();

        $myActivities = Activity::where('tenant_id', $this->tenant->id)->count();

        $this->assertGreaterThan(0, $otherTenantActivities, 'Other tenant should have activities.');
        $this->assertEquals(0, $myActivities, 'Current tenant should see zero activities from other tenant via global scope.');
    }
}
