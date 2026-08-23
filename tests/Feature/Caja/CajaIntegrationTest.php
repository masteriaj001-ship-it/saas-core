<?php

declare(strict_types=1);

namespace Tests\Feature\Caja;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Caja\Exceptions\TurnoCerradoException;
use App\Modules\Caja\Models\CashShift;
use App\Modules\Caja\Services\CashMovementService;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Talleres\Models\WorkOrder;
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
        $user = $this->user;
        $invoice = Invoice::factory()->create([
            'total' => 25000,
            'payment_method' => 'cash',
            'status' => 'confirmed',
            'work_order_id' => WorkOrder::factory()->create()->id,
        ]);

        $shift = CashShift::openShift($user, 100000);

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
        $user = $this->user;
        $invoice = Invoice::factory()->create([
            'total' => 20000,
            'payment_method' => 'card',
            'status' => 'cancelled',
        ]);

        $movement = CashMovementService::recordRefund($invoice);

        $this->assertDatabaseHas('cash_movements', [
            'type' => 'refund',
            'amount' => -20000,
        ]);
    }

    public function test_venta_sin_turno_abierto_lanza_excepcion(): void
    {
        $user = $this->user;
        $invoice = Invoice::factory()->create([
            'total' => 15000,
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);

        $this->expectException(TurnoCerradoException::class);

        CashMovementService::recordSale($invoice);
    }

    public function test_cierre_calcula_efectivo_esperado_correctamente(): void
    {
        $user = $this->user;

        $shift = CashShift::openShift($user, 100000);

        $invoice = Invoice::factory()->create([
            'total' => 30000,
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);

        CashMovementService::recordSale($invoice);

        $shift->close($user, 110000); // 100000 inicial + 30000 venta = 130000 esperado, pero se pone 110000

        $this->assertDatabaseHas('cash_shifts', [
            'id' => $shift->id,
            'difference' => 110000 - 130000, // -20000
            'actual_cash' => 110000,
        ]);
    }
}
