<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Enums\WorkOrderItemTypeEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Actions\GenerateInvoiceFromWorkOrderAction;
use App\Modules\Talleres\Models\Asset;
use App\Modules\Talleres\Models\WorkOrder;
use App\Services\TenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IvaConfigurableTest extends TestCase
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

    public function test_invoice_has_no_iva_when_tenant_not_responsible(): void
    {
        $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], [
            'es_responsable_iva' => false,
        ])]);

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'asset_id' => $this->asset->id,
            'contact_id' => $this->contact->id,
            'code' => 'WO-0005',
            'title' => 'Sin IVA',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Part->value,
            'quantity' => 2,
            'unit_price' => 10000,
        ]);

        $invoice = app(GenerateInvoiceFromWorkOrderAction::class)->execute($workOrder);
        $invoice->refresh();

        $this->assertEquals(0.00, (float) $invoice->tax_total);
        $this->assertEquals(20000.00, (float) $invoice->subtotal);
        $this->assertEquals(20000.00, (float) $invoice->grand_total);

        foreach ($invoice->items as $item) {
            $this->assertEquals(0.00, (float) $item->tax_rate);
            $this->assertEquals(0.00, (float) $item->tax_amount);
        }
    }

    public function test_invoice_has_iva_when_tenant_is_responsible(): void
    {
        $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], [
            'es_responsable_iva' => true,
        ])]);

        $workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'asset_id' => $this->asset->id,
            'contact_id' => $this->contact->id,
            'code' => 'WO-0006',
            'title' => 'Con IVA',
            'status' => 'draft',
        ]);

        $workOrder->items()->create([
            'type' => WorkOrderItemTypeEnum::Part->value,
            'quantity' => 2,
            'unit_price' => 10000,
        ]);

        $invoice = app(GenerateInvoiceFromWorkOrderAction::class)->execute($workOrder);
        $invoice->refresh();

        $subtotal = 20000.00;
        $tax = round($subtotal * 19 / 100, 2);

        $this->assertEquals($subtotal, (float) $invoice->subtotal);
        $this->assertEquals($tax, (float) $invoice->tax_total);
        $this->assertEquals($subtotal + $tax, (float) $invoice->grand_total);

        foreach ($invoice->items as $item) {
            $this->assertEquals(19.00, (float) $item->tax_rate);
            $this->assertEquals(round(10000 * 2 * 19 / 100, 2), (float) $item->tax_amount);
        }
    }

    public function test_tenant_iva_default_is_false(): void
    {
        $this->assertFalse($this->tenant->fresh()->esResponsableIva());
    }
}
