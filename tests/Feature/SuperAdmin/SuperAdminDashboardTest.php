<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Filament\Superadmin\Widgets\ChurnRiskWidget;
use App\Filament\Superadmin\Widgets\RecentActivityWidget;
use App\Filament\Superadmin\Widgets\TenantStatsWidget;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->superadmin = User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($this->superadmin);

        Filament::setCurrentPanel(app('filament')->getPanel('superadmin'));
    }

    public function test_dashboard_shows_total_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        Livewire::test(TenantStatsWidget::class)
            ->assertSuccessful();
    }

    public function test_dashboard_shows_churn_risk_tenants(): void
    {
        Tenant::factory()->count(2)->create();

        Livewire::test(ChurnRiskWidget::class)
            ->assertSuccessful();
    }

    public function test_recent_activity_ordered_by_date(): void
    {
        Tenant::factory()->create(['name' => 'Taller Nuevo', 'created_at' => now()]);
        Tenant::factory()->create(['name' => 'Taller Viejo', 'created_at' => now()->subMonth()]);

        Livewire::test(RecentActivityWidget::class)
            ->assertSuccessful();
    }
}
