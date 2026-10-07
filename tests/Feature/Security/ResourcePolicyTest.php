<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Filament\Resources\BudgetResource\Pages\CreateBudget;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ResourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->owner = User::factory()->for($this->tenant)->create();
        $this->owner->assignRole('owner');

        $this->viewer = User::factory()->for($this->tenant)->create();
        $this->viewer->assignRole('viewer');

        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
    }

    private function actingAsTenantUser(User $user): void
    {
        $this->actingAs($user);
        Filament::setTenant($this->tenant);
    }

    public function test_cash_shift_and_client_vehicle_permissions_are_seeded(): void
    {
        foreach (['cash_shifts', 'client_vehicles'] as $model) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $this->assertTrue(
                    Permission::where('name', "{$action}_{$model}")->exists(),
                    "Missing permission {$action}_{$model}"
                );
            }
        }
    }

    public function test_viewer_cannot_create_budget(): void
    {
        $this->actingAsTenantUser($this->viewer);

        Livewire::test(CreateBudget::class)
            ->assertForbidden();
    }

    public function test_owner_can_create_budget(): void
    {
        $this->actingAsTenantUser($this->owner);

        Livewire::test(CreateBudget::class)
            ->assertSuccessful();
    }
}
