<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion\Api;

use App\Enums\InvoiceDocumentTypeEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegimenTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'settings' => ['regimen' => 'no_declarante', 'es_responsable_iva' => false],
        ]);
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_regimen_default_on_tenant_creation(): void
    {
        $defaultTenant = Tenant::factory()->create(['settings' => []]);

        $this->assertEquals('declarante', $defaultTenant->regimen());
        $this->assertEquals(InvoiceDocumentTypeEnum::Invoice, $defaultTenant->documentTypeForRegimen());
    }

    public function test_change_regimen_logs_activity(): void
    {
        $originalRegimen = $this->tenant->regimen();
        $this->assertEquals('no_declarante', $originalRegimen);

        $this->tenant->settings = array_merge($this->tenant->settings ?? [], ['regimen' => 'declarante']);
        $this->tenant->save();
        $this->tenant->refresh();

        $this->assertEquals('declarante', $this->tenant->regimen());

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $this->tenant->getMorphClass(),
            'subject_id' => $this->tenant->id,
            'event' => 'updated',
        ]);
    }

    public function test_historical_documents_preserved_after_regimen_change(): void
    {
        $posInvoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'pos',
            'status' => 'issued',
        ]);

        $this->assertEquals('pos', $posInvoice->document_type->value);

        $this->tenant->settings = array_merge($this->tenant->settings ?? [], ['regimen' => 'declarante']);
        $this->tenant->save();
        $this->tenant->refresh();

        $this->assertEquals('declarante', $this->tenant->regimen());

        $posInvoice->refresh();
        $this->assertEquals(
            'pos',
            $posInvoice->document_type->value,
            'Historical POS document should remain pos after regimen change.'
        );

        $this->assertEquals(
            InvoiceDocumentTypeEnum::Invoice,
            $this->tenant->documentTypeForRegimen(),
            'New documents should default to invoice after regimen change.'
        );
    }
}
