<?php

declare(strict_types=1);

namespace Tests\Feature\Budget;

use App\Enums\BudgetStatusEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Budget\Models\Budget;
use App\Modules\Budget\Services\BudgetConversionService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BudgetAppScopeTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_can_create_budget_as_draft(): void
    {
        $budget = Budget::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('budgets', [
            'id' => $budget->id,
            'status' => BudgetStatusEnum::Draft->value,
        ]);
        $this->assertEquals($this->tenant->id, $budget->tenant_id);
    }

    public function test_budget_can_transition_from_draft_to_sent(): void
    {
        $budget = Budget::factory()->create(['tenant_id' => $this->tenant->id]);

        $budget->update([
            'status' => BudgetStatusEnum::Sent,
            'sent_at' => now(),
        ]);

        $this->assertEquals(BudgetStatusEnum::Sent, $budget->fresh()->status);
        $this->assertNotNull($budget->fresh()->sent_at);
    }

    public function test_budget_converts_to_work_order_when_approved(): void
    {
        $budget = Budget::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => BudgetStatusEnum::Sent,
            'sent_at' => now(),
            'contact_phone' => '3001234567',
            'notes' => 'Mantenimiento general y revisión de frenos',
            'vehicle_data' => [
                'make' => 'Mazda',
                'model' => 'Mazda 3',
                'plate' => 'BGT-CONV-'.fake()->unique()->bothify('??##'),
                'year' => '2020',
                'color' => 'Rojo',
            ],
        ]);

        $workOrder = app(BudgetConversionService::class)->convert($budget);

        $budget->refresh();

        $this->assertNotNull($budget->converted_to_work_order_id, 'Budget should link to a WorkOrder after conversion.');
        $this->assertEquals($workOrder->id, $budget->converted_to_work_order_id);
        $this->assertEquals($this->tenant->id, $workOrder->tenant_id);
        $this->assertEquals('received', $workOrder->status->value);
    }

    public function test_budget_can_be_rejected(): void
    {
        $budget = Budget::factory()->sent()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $budget->update([
            'status' => BudgetStatusEnum::Rejected,
            'rejected_at' => now(),
            'responded_at' => now(),
            'rejection_reason' => 'Cliente encontró mejor precio',
        ]);

        $budget->refresh();

        $this->assertEquals(BudgetStatusEnum::Rejected, $budget->status);
        $this->assertNotNull($budget->rejected_at);
        $this->assertEquals('Cliente encontró mejor precio', $budget->rejection_reason);
    }

    public function test_budget_is_scoped_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherBudget = Budget::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $myBudgets = Budget::where('tenant_id', $this->tenant->id)->count();
        $allBudgets = Budget::count();

        $this->assertEquals(0, $myBudgets, 'Should not see other tenant budgets with explicit where.');
        $this->assertEquals(0, $allBudgets, 'Global scope should hide other tenant budgets.');
    }
}
