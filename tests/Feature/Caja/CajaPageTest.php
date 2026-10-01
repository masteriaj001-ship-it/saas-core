<?php

declare(strict_types=1);

namespace Tests\Feature\Caja;

use App\Filament\Pages\Caja\CajaPage;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Caja\Models\CashShift;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
