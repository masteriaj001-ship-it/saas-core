<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Exceptions\PaymentExceedsBalanceException;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Models\InvoicePayment;
use App\Modules\Facturacion\Services\InvoiceCreationService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'invoice',
            'prefix' => 'FV',
            'sequence' => 1,
            'document_number' => 'FV-000001',
            'status' => 'draft',
            'subtotal' => 1000.00,
            'tax_total' => 190.00,
            'grand_total' => 1190.00,
        ]);
    }

    public function test_payment_can_be_registered(): void
    {
        $service = app(InvoiceCreationService::class);

        $payment = $service->registerPayment($this->invoice, [
            'method' => PaymentMethodEnum::Cash->value,
            'amount' => 1190.00,
        ]);

        $this->assertDatabaseHas('invoice_payments', [
            'id' => $payment->id,
            'invoice_id' => $this->invoice->id,
            'payment_method' => PaymentMethodEnum::Cash->value,
            'amount' => 1190.00,
        ]);

        $this->assertEquals(0.00, $this->invoice->fresh()->balanceDue());
    }

    public function test_partial_payment_keeps_invoice_issued(): void
    {
        $service = app(InvoiceCreationService::class);

        $service->registerPayment($this->invoice, [
            'method' => PaymentMethodEnum::Cash->value,
            'amount' => 500.00,
        ]);

        $this->assertEquals(690.00, $this->invoice->fresh()->balanceDue());
        $this->assertEquals(InvoiceStatusEnum::Draft, $this->invoice->fresh()->status);
    }

    public function test_payment_exceeding_balance_throws(): void
    {
        $service = app(InvoiceCreationService::class);

        $this->expectException(PaymentExceedsBalanceException::class);

        $service->registerPayment($this->invoice, [
            'method' => PaymentMethodEnum::Card->value,
            'amount' => 1200.00,
        ]);
    }

    public function test_cash_payment_calculates_change(): void
    {
        $service = app(InvoiceCreationService::class);

        $payment = $service->registerPayment($this->invoice, [
            'method' => PaymentMethodEnum::Cash->value,
            'amount' => 1190.00,
            'cash_received' => 2000.00,
        ]);

        $this->assertEquals(810.00, $payment->change_due);
        $this->assertEquals(2000.00, $payment->cash_received);
    }

    public function test_non_cash_payment_has_no_change(): void
    {
        $service = app(InvoiceCreationService::class);

        $service->registerPayment($this->invoice, [
            'method' => PaymentMethodEnum::Card->value,
            'amount' => 1190.00,
        ]);

        $payment = $this->invoice->fresh()->payments()->first();

        $this->assertNull($payment->cash_received);
        $this->assertNull($payment->change_due);
    }

    public function test_pos_invoice_with_payment_is_paid(): void
    {
        $service = app(InvoiceCreationService::class);

        $invoice = $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [
                ['description' => 'Servicio de cambio de aceite', 'quantity' => 1, 'unit_price' => 150.00],
                ['description' => 'Filtro de aceite', 'quantity' => 1, 'unit_price' => 30.00],
            ],
            'payment' => [
                'method' => PaymentMethodEnum::Cash->value,
                'amount' => 180.00,
                'cash_received' => 200.00,
            ],
        ]);

        $this->assertEquals(InvoiceStatusEnum::Paid, $invoice->status);
        $this->assertEquals('POS', $invoice->prefix);
        $this->assertNotNull($invoice->pos_sequence);
        $this->assertEquals(180.00, $invoice->payments->first()->amount);
        $this->assertEquals(20.00, $invoice->payments->first()->change_due);
        $this->assertEquals(0.00, $invoice->fresh()->balanceDue());
    }

    public function test_payments_relation_scoped_to_tenant(): void
    {
        InvoicePayment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $this->invoice->id,
            'payment_method' => PaymentMethodEnum::Cash->value,
            'amount' => 100.00,
            'paid_at' => now(),
        ]);

        $tenantB = Tenant::factory()->create();
        app(TenantManager::class)->setTenantContext($tenantB->id);

        $payments = InvoicePayment::where('invoice_id', $this->invoice->id)->get();

        $this->assertCount(0, $payments);
    }
}
