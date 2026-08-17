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

class InvoiceTicketTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Contact $contact;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();
        $this->contact = Contact::factory()->for($this->tenant)->client()->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'document_type' => 'invoice',
            'prefix' => 'FV',
            'sequence' => 1,
            'document_number' => 'FV-000001',
            'status' => 'issued',
            'subtotal' => 1000.00,
            'tax_total' => 190.00,
            'grand_total' => 1190.00,
        ]);

        $this->invoice->items()->create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $this->invoice->id,
            'description' => 'Cambio de aceite',
            'quantity' => 1,
            'unit_price' => 150.00,
            'tax_rate' => 19.00,
            'tax_amount' => 28.50,
            'subtotal' => 150.00,
            'total' => 178.50,
        ]);
    }

    public function test_ticket_route_returns_200_for_own_tenant(): void
    {
        $response = $this->get(route('invoices.ticket', $this->invoice));

        $response->assertStatus(200);
    }

    public function test_ticket_shows_document_number_and_items(): void
    {
        $response = $this->get(route('invoices.ticket', $this->invoice));

        $response->assertSee('FV-000001');
        $response->assertSee('Cambio de aceite');
        $response->assertSee('1.190');
        $response->assertSee('window.print()');
    }

    public function test_ticket_route_returns_403_for_other_tenant(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->for($tenantB)->create();

        $this->actingAs($userB);

        $response = $this->get(route('invoices.ticket', $this->invoice));

        $response->assertStatus(403);
    }
}
