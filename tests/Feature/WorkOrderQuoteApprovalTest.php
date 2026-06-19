<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WorkOrderStatusEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Actions\RequestQuoteApprovalAction;
use App\Modules\Talleres\Models\WorkOrder;
use App\Modules\Talleres\Models\WorkOrderItem;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WorkOrderQuoteApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->for($this->tenant)->create();
        $this->user->assignRole('owner');

        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => WorkOrderStatusEnum::Quoted->value,
        ]);

        WorkOrderItem::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
        ]);
    }

    public function test_sends_quote_and_generates_signed_url(): void
    {
        $this->actingAs($this->user);

        $action = app(RequestQuoteApprovalAction::class);
        $url = $action->execute($this->workOrder);

        $this->workOrder->refresh();
        $this->assertEquals(
            WorkOrderStatusEnum::WaitingApproval->value,
            $this->workOrder->status->value
        );

        $this->assertNotNull($url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
    }

    public function test_public_page_shows_quote_details(): void
    {
        $url = $this->generateApprovalUrl();
        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);

        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertSee($this->workOrder->code);
        $response->assertSee($this->workOrder->service_description);
        $response->assertSee('Aprobar');
        $response->assertSee('Rechazar');
    }

    public function test_public_page_rejects_invalid_signature(): void
    {
        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);

        $response = $this->get("/presupuesto/{$this->workOrder->id}?expires=9999999999&signature=bad");

        $response->assertStatus(403);
    }

    public function test_public_page_rejects_expired_signature(): void
    {
        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);

        $url = URL::temporarySignedRoute(
            'quote.approval.show',
            now()->subHour(),
            ['workOrder' => $this->workOrder->id]
        );

        $response = $this->get($url);
        $response->assertStatus(403);
    }

    public function test_client_can_approve_quote(): void
    {
        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);
        $url = URL::temporarySignedRoute(
            'quote.approval.approve',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );

        $response = $this->post($url);

        $this->workOrder->refresh();
        $this->assertEquals(
            WorkOrderStatusEnum::Approved->value,
            $this->workOrder->status->value
        );
        $this->assertNotNull($this->workOrder->approval_at);
        $this->assertEquals('web', $this->workOrder->approval_channel);
    }

    public function test_client_can_reject_quote_with_reason(): void
    {
        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);
        $url = URL::temporarySignedRoute(
            'quote.approval.reject',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );

        $response = $this->post($url, [
            'reason' => 'El presupuesto supera mi capacidad de pago actual',
        ]);

        $this->workOrder->refresh();
        $this->assertEquals(
            WorkOrderStatusEnum::Rejected->value,
            $this->workOrder->status->value
        );
        $this->assertIsArray($this->workOrder->metadata);
        $this->assertArrayHasKey('rejection_reason', $this->workOrder->metadata);
    }

    public function test_cannot_approve_twice(): void
    {
        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);
        $url = URL::temporarySignedRoute(
            'quote.approval.approve',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );

        $this->post($url);

        $response = $this->post($url);
        $response->assertStatus(409);
    }

    public function test_modifying_items_reverts_to_quoted(): void
    {
        $this->workOrder->update(['status' => WorkOrderStatusEnum::WaitingApproval->value]);

        $item = $this->workOrder->items()->first();
        $item->update(['quantity' => 99]);

        $this->workOrder->refresh();
        $this->assertEquals(
            WorkOrderStatusEnum::Quoted->value,
            $this->workOrder->status->value
        );
    }

    private function generateApprovalUrl(): string
    {
        return URL::temporarySignedRoute(
            'quote.approval.show',
            now()->addDays(7),
            ['workOrder' => $this->workOrder->id]
        );
    }
}
