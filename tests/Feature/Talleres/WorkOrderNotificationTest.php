<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use App\Modules\Talleres\Notifications\WorkOrderApprovedNotification;
use App\Modules\Talleres\Notifications\WorkOrderRejectedNotification;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WorkOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->seed(RolePermissionSeeder::class);

        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => WorkOrderStatusEnum::Quoted->value,
        ]);

        WorkOrderItem::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
        ]);
    }

    public function test_sends_approved_notification_to_tenant_users(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('owner');

        $editor = User::factory()->for($this->tenant)->create();
        $editor->assignRole('editor');

        Notification::fake();

        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);
        $url = URL::temporarySignedRoute(
            'quote.approval.approve',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );

        $this->post($url);

        Notification::assertSentTo(
            [$owner, $editor],
            WorkOrderApprovedNotification::class,
        );
    }

    public function test_sends_rejected_notification_with_reason(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('owner');

        Notification::fake();

        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);
        $url = URL::temporarySignedRoute(
            'quote.approval.reject',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );

        $this->post($url, [
            'reason' => 'El presupuesto supera mi capacidad de pago actual',
        ]);

        Notification::assertSentTo(
            $owner,
            WorkOrderRejectedNotification::class,
            fn (WorkOrderRejectedNotification $notification): bool => str_contains(
                $notification->toDatabase($owner)['body'] ?? '',
                'capacidad de pago',
            )
        );
    }

    public function test_only_notifies_users_from_same_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherOwner = User::factory()->for($otherTenant)->create();
        $otherOwner->assignRole('owner');

        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('owner');

        Notification::fake();

        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);
        $url = URL::temporarySignedRoute(
            'quote.approval.approve',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );

        $this->post($url);

        Notification::assertSentTo($owner, WorkOrderApprovedNotification::class);
        Notification::assertNotSentTo($otherOwner, WorkOrderApprovedNotification::class);
    }

    public function test_ignores_other_status_transitions(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('owner');

        Notification::fake();

        $this->workOrder->update(['status' => WorkOrderStatusEnum::InProgress->value]);

        Notification::assertNothingSent();
    }

    public function test_notification_has_correct_format(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('owner');

        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);
        $url = URL::temporarySignedRoute(
            'quote.approval.approve',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );

        $this->post($url);

        $notification = $owner->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame(WorkOrderApprovedNotification::class, $notification->type);

        $data = $notification->data;
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertStringContainsString('Aprobado', $data['title']);
        $this->assertStringContainsString($this->workOrder->code, $data['body']);
    }

    public function test_no_notification_when_no_eligible_users(): void
    {
        User::factory()->for($this->tenant)->create();

        Notification::fake();

        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);
        $url = URL::temporarySignedRoute(
            'quote.approval.approve',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );

        $this->post($url);

        Notification::assertNothingSent();
    }

    public function test_notification_persists_in_database(): void
    {
        $owner = User::factory()->for($this->tenant)->create();
        $owner->assignRole('owner');

        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);
        $url = URL::temporarySignedRoute(
            'quote.approval.approve',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );

        $this->post($url);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->id,
            'notifiable_type' => User::class,
        ]);
    }
}
