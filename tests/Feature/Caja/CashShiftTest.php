<?php

declare(strict_types=1);

namespace Tests\Feature\Caja;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Caja\Exceptions\TurnoCerradoException;
use App\Modules\Caja\Models\CashShift;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashShiftTest extends TestCase
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

    public function test_puede_abrir_turno_con_monto_inicial(): void
    {
        $shift = CashShift::openShift($this->user, 500000);

        $this->assertDatabaseHas('cash_shifts', [
            'id' => $shift->id,
            'status' => 'open',
            'initial_amount' => 500000,
        ]);
    }

    public function test_no_puede_abrir_dos_turnos_simultaneos(): void
    {
        CashShift::openShift($this->user, 100000);

        $this->expectException(TurnoCerradoException::class);
        CashShift::openShift($this->user, 200000);
    }

    public function test_turno_cerrado_no_puede_reabrirse(): void
    {
        $shift = CashShift::openShift($this->user, 100000);
        $shift->close($this->user, 100000);

        $this->expectException(TurnoCerradoException::class);
        $shift->reopen();
    }

    public function test_calcula_diferencia_al_cierre_correctamente(): void
    {
        $shift = CashShift::openShift($this->user, 100000);
        $shift->close($this->user, 120000);

        $this->assertDatabaseHas('cash_shifts', [
            'id' => $shift->id,
            'difference' => 20000,
            'status' => 'closed',
        ]);
    }

    public function test_rls_turno_no_visible_en_otro_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->for($otherTenant)->create();

        $shift = CashShift::openShift($this->user, 100000);

        $this->actingAs($otherUser);
        app(TenantManager::class)->setTenantContext($otherTenant->id);

        $shiftInOtherTenant = CashShift::query()->tenant()->find($shift->id);

        $this->assertNull($shiftInOtherTenant);
    }
}
