<?php

declare(strict_types=1);

namespace Tests\Feature\Superadmin;

use App\Filament\Superadmin\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Superadmin\Resources\TenantResource\Pages\EditTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantPosSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($this->superadmin);

        Filament::setCurrentPanel(app('filament')->getPanel('superadmin'));

        $seedTenant = Tenant::factory()->create();
        app(TenantManager::class)->setTenantContext($seedTenant->id);
        $this->seed(RolePermissionSeeder::class);
        app(TenantManager::class)->clearTenantContext();
    }

    public function test_create_tenant_persists_pos_hardware_settings(): void
    {
        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Taller POS',
                'slug' => 'taller-pos',
                'plan' => 'basic',
                'is_active' => true,
                'settings.pos_hardware.printer_driver' => 'esc_pos',
                'settings.pos_hardware.printer_host' => '192.168.1.50',
                'settings.pos_hardware.printer_port' => 9100,
                'settings.pos_hardware.cash_drawer_after_payment' => true,
                'settings.pos_hardware.cash_drawer_channel' => 2,
                'admin_name' => 'Admin POS',
                'admin_email' => 'admin@pos.com',
                'admin_password' => 'securePass1!',
                'admin_password_confirmation' => 'securePass1!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::where('slug', 'taller-pos')->first();

        $this->assertNotNull($tenant);
        $this->assertSame('esc_pos', $tenant->settings['pos_hardware']['printer_driver'] ?? null);
        $this->assertSame('192.168.1.50', $tenant->settings['pos_hardware']['printer_host'] ?? null);
        $this->assertSame(9100, $tenant->settings['pos_hardware']['printer_port'] ?? null);
        $this->assertTrue($tenant->settings['pos_hardware']['cash_drawer_after_payment'] ?? null);
        $this->assertSame(2, $tenant->settings['pos_hardware']['cash_drawer_channel'] ?? null);
        $this->assertSame('esc_pos', $tenant->posPrinterDriver());
        $this->assertSame('192.168.1.50', $tenant->posPrinterHost());
        $this->assertSame(9100, $tenant->posPrinterPort());
        $this->assertTrue($tenant->posCashDrawerEnabled());
    }

    public function test_edit_tenant_updates_pos_hardware_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertSame('window_print', $tenant->posPrinterDriver());

        Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
            ->fillForm([
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'settings.pos_hardware.printer_driver' => 'esc_pos',
                'settings.pos_hardware.printer_host' => '10.0.0.5',
                'settings.pos_hardware.printer_port' => 9101,
                'settings.pos_hardware.cash_drawer_after_payment' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $tenant->fresh();

        $this->assertSame('esc_pos', $fresh->settings['pos_hardware']['printer_driver'] ?? null);
        $this->assertSame('10.0.0.5', $fresh->settings['pos_hardware']['printer_host'] ?? null);
        $this->assertSame(9101, $fresh->settings['pos_hardware']['printer_port'] ?? null);
        $this->assertFalse($fresh->settings['pos_hardware']['cash_drawer_after_payment'] ?? null);
    }
}
