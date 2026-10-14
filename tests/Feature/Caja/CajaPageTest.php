<?php

declare(strict_types=1);

namespace Tests\Feature\Caja;

use App\Filament\Pages\Caja\CajaPage;
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

class CajaPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);
    }

    public function test_load_shift_data_formats_decimal_cast_amounts(): void
    {
        $shift = CashShift::openShift($this->user, 200000)->fresh();

        // decimal:2 cast returns string on fresh DB read (production strict_types context).
        $this->assertIsString($shift->initial_amount);

        $page = new CajaPage;
        $page->currentShift = $shift;
        $page->loadShiftData();

        $this->assertTrue($page->cards['turno_abierto']);
        $this->assertSame('200.000,00', $page->cards['monto_inicial']);
    }

    public function test_owner_opens_shift_with_notes_from_dashboard(): void
    {
        $this->user->assignRole('owner');

        Livewire::test(CajaPage::class)
            ->assertSee('Notas')
            ->set('initialAmount', 150000)
            ->set('openNotes', 'Apertura con notas desde dashboard')
            ->call('openShift');

        $this->assertDatabaseHas('cash_shifts', [
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
            'notes' => 'Apertura con notas desde dashboard',
        ]);
    }

    public function test_notes_field_hidden_for_editor_on_open_form(): void
    {
        $editor = User::factory()->for($this->tenant)->create();
        $editor->assignRole('editor');
        $this->actingAs($editor);

        Livewire::test(CajaPage::class)
            ->assertDontSee('Notas')
            ->set('initialAmount', 100000)
            ->call('openShift');

        $this->assertDatabaseHas('cash_shifts', [
            'tenant_id' => $this->tenant->id,
            'status' => 'open',
            'notes' => null,
        ]);
    }
}
