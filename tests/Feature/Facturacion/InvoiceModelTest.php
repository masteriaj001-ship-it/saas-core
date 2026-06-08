<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Models\InvoiceItem;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceModelTest extends TestCase
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

    public function test_invoice_can_be_created(): void
    {
        $invoice = Invoice::create([
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

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'document_number' => 'FV-000001',
            'subtotal' => 1000.00,
            'grand_total' => 1190.00,
        ]);

        $this->assertEquals('FV-000001', $invoice->document_number);
        $this->assertEquals(InvoiceStatusEnum::Draft, $invoice->status);
        $this->assertEquals(InvoiceDocumentTypeEnum::Invoice, $invoice->document_type);
    }

    public function test_invoice_tenant_isolation(): void
    {
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'invoice',
            'prefix' => 'FV',
            'sequence' => 1,
            'document_number' => 'FV-000001',
            'status' => 'draft',
        ]);

        $tenantB = Tenant::factory()->create();
        app(TenantManager::class)->setTenantContext($tenantB->id);

        $invoices = Invoice::where('document_number', 'FV-000001')->get();

        $this->assertCount(0, $invoices);
    }

    public function test_invoice_item_belongs_to_invoice(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'invoice',
            'prefix' => 'FV',
            'sequence' => 1,
            'document_number' => 'FV-000001',
            'status' => 'draft',
        ]);

        $item = InvoiceItem::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'description' => 'Cambio de aceite',
            'quantity' => 1,
            'unit_price' => 150.00,
            'tax_rate' => 19.00,
            'tax_amount' => 28.50,
            'subtotal' => 150.00,
            'total' => 178.50,
        ]);

        $this->assertTrue($invoice->items()->where('id', $item->id)->exists());
        $this->assertEquals($invoice->id, $item->invoice_id);
        $this->assertEquals('Cambio de aceite', $item->description);
    }

    public function test_invoice_without_work_order_is_valid(): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'invoice',
            'prefix' => 'FV',
            'sequence' => 1,
            'document_number' => 'FV-000001',
            'status' => 'draft',
        ]);

        $this->assertNull($invoice->work_order_id);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_invoice_status_enum_has_four_cases(): void
    {
        $cases = InvoiceStatusEnum::cases();

        $this->assertCount(4, $cases);

        $this->assertTrue(InvoiceStatusEnum::tryFrom('draft') instanceof InvoiceStatusEnum);
        $this->assertTrue(InvoiceStatusEnum::tryFrom('issued') instanceof InvoiceStatusEnum);
        $this->assertTrue(InvoiceStatusEnum::tryFrom('paid') instanceof InvoiceStatusEnum);
        $this->assertTrue(InvoiceStatusEnum::tryFrom('cancelled') instanceof InvoiceStatusEnum);
        $this->assertNull(InvoiceStatusEnum::tryFrom('invalid'));
    }
}
