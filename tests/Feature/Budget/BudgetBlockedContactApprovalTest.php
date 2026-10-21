<?php

declare(strict_types=1);

namespace Tests\Feature\Budget;

use App\Enums\BudgetStatusEnum;
use App\Filament\Resources\BudgetResource\Pages\ViewBudget;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Budget\Models\Budget;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class BudgetBlockedContactApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->tenant = Tenant::factory()->create([
            'organization_id' => $org->id,
            'is_active' => true,
        ]);
        $this->user = User::factory()->for($this->tenant)->create();

        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole('owner');
        $this->actingAs($this->user);

        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        app(TenantManager::class)->clearTenantContext();
        parent::tearDown();
    }

    public function test_approving_sent_budget_for_blocked_contact_keeps_status_and_notifies(): void
    {
        $blockedUntil = now()->addDays(3);
        $contact = Contact::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
            'blocked_until' => $blockedUntil,
        ]);

        $budget = Budget::factory()->sent()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
        ]);

        $expectedMessage = sprintf(
            __('El contacto tiene un bloqueo activo hasta el %s. No se pueden crear órdenes para este contacto.'),
            $blockedUntil->format('Y-m-d H:i'),
        );

        Livewire::test(ViewBudget::class, ['record' => $budget->id])
            ->callAction('approve')
            ->assertNotified($expectedMessage);

        $budget->refresh();

        $this->assertSame(BudgetStatusEnum::Sent, $budget->status, 'El presupuesto no debe quedar en Approved si el contacto está bloqueado.');
        $this->assertNull($budget->approved_at);
        $this->assertNull($budget->converted_to_work_order_id);
        $this->assertDatabaseCount('work_orders', 0);
    }

    public function test_approving_sent_budget_for_unblocked_contact_converts_exactly_once(): void
    {
        $contact = Contact::factory()->client()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $budget = Budget::factory()->sent()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'vehicle_data' => ['plate' => 'ABC123', 'make' => 'Toyota', 'model' => 'Corolla'],
        ]);

        Livewire::test(ViewBudget::class, ['record' => $budget->id])
            ->callAction('approve');

        $budget->refresh();

        $this->assertSame(BudgetStatusEnum::Approved, $budget->status);
        $this->assertNotNull($budget->converted_to_work_order_id, 'La aprobación debe convertir a WorkOrder exactamente una vez (sin recursión del observer).');
        $this->assertDatabaseCount('work_orders', 1);
        $this->assertDatabaseCount('client_vehicles', 1);
    }
}
