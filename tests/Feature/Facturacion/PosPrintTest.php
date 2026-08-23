<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosPrintTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'settings' => [
                'pos_hardware' => [
                    'printer_driver' => 'window_print',
                ],
            ],
        ]);
        $this->user = User::factory()->for($this->tenant)->create();
        $contact = Contact::factory()->for($this->tenant)->client()->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $contact->id,
            'document_type' => 'pos',
            'prefix' => 'PV',
            'sequence' => 1,
            'document_number' => 'PV-000001',
            'status' => 'paid',
            'subtotal' => 1000.00,
            'tax_total' => 190.00,
            'grand_total' => 1190.00,
            'issued_at' => now(),
        ]);
    }

    public function test_print_endpoint_returns_ok_for_own_tenant(): void
    {
        $response = $this->post(route('pos.print'), [
            'invoice_id' => $this->invoice->id,
        ]);

        $response->assertStatus(200);
    }

    public function test_print_endpoint_returns_403_for_other_tenant(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->for($tenantB)->create();

        $this->actingAs($userB);

        $response = $this->post(route('pos.print'), [
            'invoice_id' => $this->invoice->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_print_endpoint_returns_404_for_missing_invoice(): void
    {
        $response = $this->post(route('pos.print'), [
            'invoice_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(404);
    }
}
