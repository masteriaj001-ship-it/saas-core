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

class InvoicePdfTest extends TestCase
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
    }

    public function test_pdf_route_returns_200_for_own_tenant(): void
    {
        $response = $this->get(route('invoices.pdf', $this->invoice));

        $response->assertStatus(200);
    }

    public function test_pdf_route_returns_403_for_other_tenant(): void
    {
        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->for($tenantB)->create();

        $this->actingAs($userB);

        $response = $this->get(route('invoices.pdf', $this->invoice));

        $response->assertStatus(403);
    }

    public function test_pdf_response_is_pdf_content_type(): void
    {
        $response = $this->get(route('invoices.pdf', $this->invoice));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }
}
