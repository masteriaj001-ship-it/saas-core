<?php

declare(strict_types=1);

namespace Tests\Feature\Caja;

use App\Filament\Resources\CashShiftResource\Pages\ListCashShifts;
use App\Filament\Resources\CashShiftResource\Pages\ViewCashShift;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Caja\Models\CashShift;
use App\Services\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashShiftTableTest extends TestCase
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
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);
    }

    public function test_list_renders_computed_columns(): void
    {
        $shift = CashShift::openShift($this->user, 200000);

        Livewire::test(ListCashShifts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$shift]);
    }

    public function test_view_renders_computed_entries(): void
    {
        $shift = CashShift::openShift($this->user, 200000);

        Livewire::test(ViewCashShift::class, [
            'record' => $shift->getKey(),
        ])->assertSuccessful();
    }
}
