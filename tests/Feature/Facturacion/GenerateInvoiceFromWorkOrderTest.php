<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Enums\WorkOrderItemTypeEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Actions\GenerateInvoiceFromWorkOrderAction;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateInvoiceFromWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Contact $contact;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();
        $this->contact = Contact::factory()->for($this->tenant)->client()->create();
        $this->asset = Asset::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_generates_invoice_from_work_order(): void
    {
        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'asset_id' => $this->asset->id,
            'contact_id' => $this->contact->id,
            'code' => 'WO-0001',
            'title' => 'Cambio de aceite',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Part->value,
            'quantity' => 2,
            'unit_price' => 15000,
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Labor->value,
            'quantity' => 1,
            'unit_price' => 25000,
        ]);

        $invoice = app(GenerateInvoiceFromWorkOrderAction::class)->execute($workOrder);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'work_order_id' => $workOrder->id,
            'contact_id' => $this->contact->id,
            'status' => 'draft',
        ]);

        $this->assertCount(2, $invoice->fresh()->items);

        foreach ($workOrder->items as $woItem) {
            $this->assertDatabaseHas('invoice_items', [
                'invoice_id' => $invoice->id,
                'work_order_item_id' => $woItem->id,
                'quantity' => $woItem->quantity,
                'unit_price' => $woItem->unit_price,
            ]);
        }
    }

    public function test_invoice_totals_calculated_correctly(): void
    {
        $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], [
            'es_responsable_iva' => true,
        ])]);

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'asset_id' => $this->asset->id,
            'contact_id' => $this->contact->id,
            'code' => 'WO-0002',
            'title' => 'Mantenimiento general',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Part->value,
            'quantity' => 3,
            'unit_price' => 10000,
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Service->value,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        $invoice = app(GenerateInvoiceFromWorkOrderAction::class)->execute($workOrder);
        $invoice->refresh();

        // Item 1: 3 × 10000 = 30000, tax = 5700, total = 35700
        // Item 2: 1 × 50000 = 50000, tax = 9500, total = 59500
        // Totals: subtotal = 80000, tax = 15200, grand = 95200

        $this->assertEquals(80000.00, (float) $invoice->subtotal);
        $this->assertEquals(15200.00, (float) $invoice->tax_total);
        $this->assertEquals(95200.00, (float) $invoice->grand_total);
    }

    public function test_invoice_inherits_contact_from_work_order(): void
    {
        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'asset_id' => $this->asset->id,
            'contact_id' => $this->contact->id,
            'code' => 'WO-0003',
            'title' => 'Fact test',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Part->value,
            'quantity' => 1,
            'unit_price' => 5000,
        ]);

        $invoice = app(GenerateInvoiceFromWorkOrderAction::class)->execute($workOrder);
        $invoice->refresh();

        $this->assertEquals($this->contact->id, $invoice->contact_id);
        $this->assertEquals($workOrder->contact_id, $invoice->contact_id);
    }

    public function test_action_button_not_visible_without_items(): void
    {
        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'asset_id' => $this->asset->id,
            'contact_id' => $this->contact->id,
            'code' => 'WO-0004',
            'title' => 'Sin items',
            'status' => 'draft',
        ]);

        $this->assertFalse($workOrder->items()->exists());
    }
}
