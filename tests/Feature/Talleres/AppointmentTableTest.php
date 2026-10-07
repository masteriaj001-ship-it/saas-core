<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Filament\Resources\AppointmentResource\Pages\ListAppointments;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Talleres\Models\Appointment;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AppointmentTableTest extends TestCase
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
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole('owner');
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);
    }

    public function test_sorting_by_relation_columns_does_not_error(): void
    {
        $mechanic = User::factory()->for($this->tenant)->create();

        Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'mechanic_id' => $mechanic->id,
        ]);
        Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'mechanic_id' => $mechanic->id,
        ]);

        Livewire::test(ListAppointments::class)
            ->sortTable('contact.name')
            ->assertSuccessful();
    }
}
