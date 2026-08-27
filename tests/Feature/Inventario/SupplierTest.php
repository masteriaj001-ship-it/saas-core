<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Models\PurchaseOrder;
use App\Modules\Inventario\Models\Supplier;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_supplier_can_be_created(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_supplier_has_contact(): void
    {
        $contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
        ]);

        $this->assertNotNull($supplier->contact);
        $this->assertEquals($contact->id, $supplier->contact->id);
    }

    public function test_supplier_has_purchase_orders(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplier->id,
        ]);

        $this->assertCount(1, $supplier->purchaseOrders);
    }

    public function test_supplier_display_name(): void
    {
        $supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
            'trade_name' => 'AutoParts Colombia',
        ]);

        $this->assertEquals('AutoParts Colombia', $supplier->display_name);
    }
}
