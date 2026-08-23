<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\Print;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Shared\Services\Print\TicketRenderer;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketRendererTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $contact = Contact::factory()->for($tenant)->client()->create();

        $this->actingAs($user);
        app(TenantManager::class)->setTenantContext($tenant->id);

        $this->invoice = Invoice::create([
            'tenant_id' => $tenant->id,
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

        $this->invoice->items()->create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $this->invoice->id,
            'description' => 'Pastilla de freno',
            'quantity' => 2,
            'unit_price' => 500.00,
            'tax_rate' => 19.00,
            'tax_amount' => 190.00,
            'subtotal' => 1000.00,
            'total' => 1190.00,
        ]);
    }

    public function test_render_produces_normalized_payload(): void
    {
        $payload = (new TicketRenderer)->render($this->invoice);

        $this->assertArrayHasKey('document_number', $payload);
        $this->assertArrayHasKey('issued_at', $payload);
        $this->assertArrayHasKey('items', $payload);
        $this->assertArrayHasKey('grand_total', $payload);

        $this->assertSame('PV-000001', $payload['document_number']);
        $this->assertSame(1190.00, $payload['grand_total']);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('Pastilla de freno', $payload['items'][0]['description']);
        $this->assertSame(2, $payload['items'][0]['quantity']);
        $this->assertSame(1190.00, $payload['items'][0]['total']);
    }
}
