<?php

declare(strict_types=1);

namespace Tests\Feature\Caja;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Caja\Exceptions\TurnoCerradoException;
use App\Modules\Caja\Models\CashShift;
use App\Modules\Caja\Services\CashMovementService;
use App\Modules\Facturacion\Models\Invoice;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CajaIntegrationTest extends TestCase
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

    public function test_factura_confirmada_crea_movimiento_automatico(): void
    {
        $shift = CashShift::openShift($this->user, 100000);

        $invoice = Invoice::factory()->create([
            'total' => 25000,
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);

        $movement = CashMovementService::recordSale($invoice);

        $this->assertDatabaseHas('cash_movements', [
            'type' => 'sale',
            'amount' => 25000,
            'shift_id' => $shift->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_factura_cancelada_crea_reembolso(): void
    {
        $shift = CashShift::openShift($this->user, 100000);

        $invoice = Invoice::factory()->create([
            'total' => 20000,
            'payment_method' => 'card',
            'status' => 'cancelled',
        ]);

        $movement = CashMovementService::recordRefund($invoice);

        $this->assertDatabaseHas('cash_movements', [
            'type' => 'refund',
            'amount' => -20000,
            'shift_id' => $shift->id,
        ]);

        $shift->refresh();
        $this->assertEquals(80000.00, (float) $shift->expected_cash);
    }

    public function test_venta_sin_turno_abierto_lanza_excepcion(): void
    {
        $invoice = Invoice::factory()->create([
            'total' => 15000,
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);

        $this->expectException(TurnoCerradoException::class);

        CashMovementService::recordSale($invoice);
    }

    public function test_reembolso_sin_turno_abierto_lanza_excepcion(): void
    {
        $invoice = Invoice::factory()->create([
            'total' => 15000,
            'payment_method' => 'cash',
            'status' => 'cancelled',
        ]);

        $this->expectException(TurnoCerradoException::class);

        CashMovementService::recordRefund($invoice);
    }

    public function test_cierre_calcula_diferencia_correctamente(): void
    {
        $shift = CashShift::openShift($this->user, 100000);

        $invoice = Invoice::factory()->create([
            'total' => 30000,
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);

        CashMovementService::recordSale($invoice);

        $shift->close($this->user, 110000);

        $this->assertDatabaseHas('cash_shifts', [
            'id' => $shift->id,
            'actual_cash' => 110000,
            'status' => 'closed',
        ]);

        $shift->refresh();
        $this->assertEquals(-20000.00, (float) $shift->difference);
        $this->assertEquals(130000.00, (float) $shift->expected_cash);
    }

    public function test_open_shift_crea_income_movement(): void
    {
        $shift = CashMovementService::openShift($this->user, 500000);

        $this->assertDatabaseHas('cash_movements', [
            'type' => 'income',
            'amount' => 500000,
            'shift_id' => $shift->id,
            'payment_method' => 'cash',
        ]);
    }

    public function test_close_shift_crea_expense_movement(): void
    {
        $shift = CashShift::openShift($this->user, 100000);

        CashMovementService::closeShift($this->user, 120000, 'Cierre correcto');

        $this->assertDatabaseHas('cash_movements', [
            'type' => 'expense',
            'amount' => 120000,
            'shift_id' => $shift->id,
            'payment_method' => 'cash',
        ]);
    }

    public function test_rls_cash_shift_no_visible_en_otro_tenant(): void
    {
        $shift = CashShift::openShift($this->user, 100000);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->for($otherTenant)->create();

        $this->actingAs($otherUser);
        app(TenantManager::class)->setTenantContext($otherTenant->id);

        $shiftInOtherTenant = CashShift::query()->find($shift->id);

        $this->assertNull($shiftInOtherTenant);
    }
}
