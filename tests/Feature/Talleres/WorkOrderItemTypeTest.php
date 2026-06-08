<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\WorkOrderItemTypeEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\WorkOrderItem;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderItemTypeTest extends TestCase
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

    public function test_work_order_item_default_type_is_part(): void
    {
        $item = WorkOrderItem::factory()->create();

        $this->assertEquals(WorkOrderItemTypeEnum::Part, $item->type);
    }

    public function test_work_order_item_accepts_all_types(): void
    {
        $part = WorkOrderItem::factory()->create(['type' => WorkOrderItemTypeEnum::Part->value]);
        $service = WorkOrderItem::factory()->create(['type' => WorkOrderItemTypeEnum::Service->value]);
        $labor = WorkOrderItem::factory()->create(['type' => WorkOrderItemTypeEnum::Labor->value]);

        $this->assertEquals(WorkOrderItemTypeEnum::Part, $part->fresh()->type);
        $this->assertEquals(WorkOrderItemTypeEnum::Service, $service->fresh()->type);
        $this->assertEquals(WorkOrderItemTypeEnum::Labor, $labor->fresh()->type);
    }

    public function test_work_order_item_type_enum_has_three_cases(): void
    {
        $cases = WorkOrderItemTypeEnum::cases();

        $this->assertCount(3, $cases);
        $this->assertTrue(WorkOrderItemTypeEnum::tryFrom('part') !== null);
        $this->assertTrue(WorkOrderItemTypeEnum::tryFrom('service') !== null);
        $this->assertTrue(WorkOrderItemTypeEnum::tryFrom('labor') !== null);
    }

    public function test_work_order_items_are_tenant_isolated(): void
    {
        $otherTenant = Tenant::factory()->create();

        app(TenantManager::class)->setTenantContext($otherTenant->id);
        $otherItem = WorkOrderItem::factory()->create();

        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $myItem = WorkOrderItem::factory()->create();

        $visible = WorkOrderItem::whereIn('id', [$otherItem->id, $myItem->id])->get();

        $this->assertCount(1, $visible);
        $this->assertEquals($myItem->id, $visible->first()->id);
    }
}
