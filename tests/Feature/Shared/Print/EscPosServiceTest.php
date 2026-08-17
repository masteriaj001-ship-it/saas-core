<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\Print;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Shared\Services\Print\EscPosService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscPosServiceTest extends TestCase
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

    public function test_build_starts_with_printer_init_command(): void
    {
        $bytes = (new EscPosService)->build($this->invoice);

        $this->assertStringStartsWith("\x1d\x40", $bytes);
    }

    public function test_build_contains_document_number_and_item(): void
    {
        $bytes = (new EscPosService)->build($this->invoice);

        $this->assertStringContainsString('PV-000001', $bytes);
        $this->assertStringContainsString('Pastilla de freno', $bytes);
    }

    public function test_cash_drawer_command_uses_selected_channel(): void
    {
        $service = new EscPosService;

        $this->assertStringContainsString("\x1bp\x02\x19\x19", $service->cashDrawerPulse(2));
        $this->assertStringContainsString("\x1bp\x00\x19\x19", $service->cashDrawerPulse(0));
    }

    public function test_send_to_unreachable_host_returns_false(): void
    {
        $service = new EscPosService;

        $result = $service->send("\x1d\x40", '127.0.0.1', 1);

        $this->assertFalse($result);
    }
}