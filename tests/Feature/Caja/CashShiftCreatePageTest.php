<?php

declare(strict_types=1);

namespace Tests\Feature\Caja;

use App\Filament\Resources\CashShiftResource\Pages\CreateCashShift;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Caja\Models\CashShift;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CashShiftCreatePageTest extends TestCase
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

    public function test_create_cash_shift_page_owner_delegates_to_service(): void
    {
        Livewire::test(CreateCashShift::class)
            ->fillForm([
                'initial_amount' => 150000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cash_shifts', [
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
            'opened_by' => $this->user->id,
            'initial_amount' => 150000,
        ]);

        $shift = CashShift::query()->firstOrFail();

        $this->assertDatabaseHas('cash_movements', [
            'shift_id' => $shift->id,
            'type' => 'income',
            'amount' => 150000,
            'description' => 'Apertura de turno de caja',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_create_cash_shift_page_viewer_is_forbidden(): void
    {
        $viewer = User::factory()->for($this->tenant)->create();
        $viewer->assignRole('viewer');
        $this->actingAs($viewer);

        Livewire::test(CreateCashShift::class)
            ->assertForbidden();
    }

    public function test_status_field_is_not_present_on_create_form(): void
    {
        Livewire::test(CreateCashShift::class)
            ->assertFormFieldDoesNotExist('status');
    }

    public function test_notes_field_visible_for_owner(): void
    {
        Livewire::test(CreateCashShift::class)
            ->assertFormFieldVisible('notes');
    }

    public function test_notes_field_visible_for_admin(): void
    {
        $admin = User::factory()->for($this->tenant)->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(CreateCashShift::class)
            ->assertFormFieldVisible('notes');
    }

    public function test_notes_field_hidden_for_editor(): void
    {
        $editor = User::factory()->for($this->tenant)->create();
        $editor->assignRole('editor');
        $this->actingAs($editor);

        Livewire::test(CreateCashShift::class)
            ->assertFormFieldHidden('notes');
    }

    public function test_notes_are_persisted_when_created_via_page(): void
    {
        Livewire::test(CreateCashShift::class)
            ->fillForm([
                'initial_amount' => 150000,
                'notes' => 'Notas de apertura',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cash_shifts', [
            'tenant_id' => $this->tenant->id,
            'notes' => 'Notas de apertura',
        ]);
    }

    public function test_create_shows_form_error_when_shift_already_open(): void
    {
        CashShift::openShift($this->user, 100000);

        Livewire::test(CreateCashShift::class)
            ->fillForm([
                'initial_amount' => 150000,
            ])
            ->call('create')
            ->assertHasFormErrors(['initial_amount']);

        $this->assertDatabaseCount('cash_shifts', 1);
    }
}
