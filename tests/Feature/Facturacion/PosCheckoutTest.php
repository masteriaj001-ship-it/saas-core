<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Exceptions\PaymentExceedsBalanceException;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Services\InvoiceCreationService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
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
    }

    public function test_creates_pos_invoice_with_single_item(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create([
            'price' => 50000,
            'stock' => 10,
        ]);

        $service = app(InvoiceCreationService::class);

        $invoice = $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [
                [
                    'description' => $item->name,
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'discount' => 0,
                ],
            ],
            'payment' => [
                'method' => PaymentMethodEnum::Cash->value,
                'amount' => 50000,
                'cash_received' => 60000,
            ],
        ]);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(InvoiceStatusEnum::Paid, $invoice->status);
        $this->assertEquals(50000, $invoice->grand_total);
        $this->assertEquals(0, $invoice->tax_total);
        $this->assertStringStartsWith('POS', $invoice->document_number);
        $this->assertCount(1, $invoice->items);
        $this->assertCount(1, $invoice->payments);
    }

    public function test_creates_pos_invoice_with_multiple_items(): void
    {
        $item1 = Item::factory()->spare()->for($this->tenant)->create(['price' => 30000, 'stock' => 5]);
        $item2 = Item::factory()->product()->for($this->tenant)->create(['price' => 15000, 'stock' => 20]);

        $service = app(InvoiceCreationService::class);

        $invoice = $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [
                ['description' => $item1->name, 'quantity' => 2, 'unit_price' => 30000, 'discount' => 0],
                ['description' => $item2->name, 'quantity' => 3, 'unit_price' => 15000, 'discount' => 0],
            ],
            'payment' => [
                'method' => PaymentMethodEnum::Cash->value,
                'amount' => 105000,
                'cash_received' => 110000,
            ],
        ]);

        $this->assertEquals(105000, $invoice->grand_total);
        $this->assertCount(2, $invoice->items);
        $this->assertEquals(105000, $invoice->amountPaid());
        $this->assertTrue($invoice->isPaid());
    }

    public function test_calculates_cash_change(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 25000, 'stock' => 10]);

        $service = app(InvoiceCreationService::class);

        $invoice = $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [
                ['description' => $item->name, 'quantity' => 1, 'unit_price' => 25000, 'discount' => 0],
            ],
            'payment' => [
                'method' => PaymentMethodEnum::Cash->value,
                'amount' => 25000,
                'cash_received' => 30000,
            ],
        ]);

        $payment = $invoice->payments->first();
        $this->assertEquals(30000, $payment->cash_received);
        $this->assertEquals(5000, $payment->change_due);
    }

    public function test_card_payment_no_change(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 40000, 'stock' => 10]);

        $service = app(InvoiceCreationService::class);

        $invoice = $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [
                ['description' => $item->name, 'quantity' => 1, 'unit_price' => 40000, 'discount' => 0],
            ],
            'payment' => [
                'method' => PaymentMethodEnum::Card->value,
                'amount' => 40000,
            ],
        ]);

        $payment = $invoice->payments->first();
        $this->assertEquals(PaymentMethodEnum::Card, $payment->payment_method);
        $this->assertNull($payment->cash_received);
        $this->assertNull($payment->change_due);
    }

    public function test_rejects_payment_exceeding_balance(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 20000, 'stock' => 10]);

        $service = app(InvoiceCreationService::class);

        $this->expectException(PaymentExceedsBalanceException::class);

        $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [
                ['description' => $item->name, 'quantity' => 1, 'unit_price' => 20000, 'discount' => 0],
            ],
            'payment' => [
                'method' => PaymentMethodEnum::Cash->value,
                'amount' => 25000,
                'cash_received' => 25000,
            ],
        ]);
    }

    public function test_creates_pos_invoice_without_payment(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 10]);

        $service = app(InvoiceCreationService::class);

        $invoice = $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [
                ['description' => $item->name, 'quantity' => 1, 'unit_price' => 10000, 'discount' => 0],
            ],
        ]);

        $this->assertEquals(InvoiceStatusEnum::Draft, $invoice->status);
        $this->assertEquals(10000, $invoice->balanceDue());
        $this->assertFalse($invoice->isPaid());
        $this->assertCount(0, $invoice->payments);
    }

    public function test_pos_document_uses_pos_sequence(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 10]);

        $service = app(InvoiceCreationService::class);

        $invoice1 = $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [['description' => 'Item A', 'quantity' => 1, 'unit_price' => 10000, 'discount' => 0]],
            'payment' => ['method' => 'cash', 'amount' => 10000, 'cash_received' => 10000],
        ]);

        $invoice2 = $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [['description' => 'Item B', 'quantity' => 1, 'unit_price' => 10000, 'discount' => 0]],
            'payment' => ['method' => 'cash', 'amount' => 10000, 'cash_received' => 10000],
        ]);

        $this->assertStringStartsWith('POS', $invoice1->document_number);
        $this->assertStringStartsWith('POS', $invoice2->document_number);
        $this->assertNotEquals($invoice1->document_number, $invoice2->document_number);
        $this->assertNotNull($invoice1->pos_sequence);
        $this->assertNull($invoice1->sequence);
    }

    public function test_transfer_payment(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 35000, 'stock' => 10]);

        $service = app(InvoiceCreationService::class);

        $invoice = $service->create($this->tenant, InvoiceDocumentTypeEnum::Pos, [
            'items' => [
                ['description' => $item->name, 'quantity' => 1, 'unit_price' => 35000, 'discount' => 0],
            ],
            'payment' => [
                'method' => PaymentMethodEnum::Transfer->value,
                'amount' => 35000,
                'reference' => 'TRX-12345',
            ],
        ]);

        $payment = $invoice->payments->first();
        $this->assertEquals(PaymentMethodEnum::Transfer, $payment->payment_method);
        $this->assertEquals('TRX-12345', $payment->reference);
        $this->assertTrue($invoice->isPaid());
    }
}
