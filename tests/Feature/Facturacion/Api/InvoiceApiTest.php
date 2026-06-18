<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantDeclarante;

    private Tenant $tenantNoDeclarante;

    private User $userDeclarante;

    private User $userNoDeclarante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDeclarante = Tenant::factory()->create([
            'settings' => ['regimen' => 'declarante', 'es_responsable_iva' => true],
        ]);
        $this->userDeclarante = User::factory()->for($this->tenantDeclarante)->create();

        $this->tenantNoDeclarante = Tenant::factory()->create([
            'settings' => ['regimen' => 'no_declarante', 'es_responsable_iva' => false],
        ]);
        $this->userNoDeclarante = User::factory()->for($this->tenantNoDeclarante)->create();
    }

    public function test_create_invoice_as_declarante(): void
    {
        $this->actingAs($this->userDeclarante);
        app(TenantManager::class)->setTenantContext($this->tenantDeclarante->id);

        $response = $this->postJson('/api/v1/invoices', [
            'contact_name' => 'Cliente Test',
            'items' => [
                ['description' => 'Cambio de aceite', 'quantity' => 1, 'unit_price' => 150.00],
            ],
            'notes' => 'Factura de prueba',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'document_type', 'document_number', 'status', 'subtotal', 'tax_total', 'grand_total'],
            ]);

        $this->assertEquals('invoice', $response->json('data.document_type'));
        $this->assertEquals(150.00, (float) $response->json('data.subtotal'));
        $this->assertEquals(28.50, (float) $response->json('data.tax_total'));
        $this->assertEquals(178.50, (float) $response->json('data.grand_total'));
    }

    public function test_create_pos_as_no_declarante(): void
    {
        $this->actingAs($this->userNoDeclarante);
        app(TenantManager::class)->setTenantContext($this->tenantNoDeclarante->id);

        $response = $this->postJson('/api/v1/invoices', [
            'contact_name' => 'Cliente POS',
            'items' => [
                ['description' => 'Lavado general', 'quantity' => 1, 'unit_price' => 50.00],
            ],
        ]);

        $response->assertStatus(201);

        $this->assertEquals('pos', $response->json('data.document_type'));
        $this->assertEquals(0, (float) $response->json('data.tax_total'));
        $this->assertEquals(50.00, (float) $response->json('data.grand_total'));
    }

    public function test_list_invoices_paginated(): void
    {
        $this->actingAs($this->userDeclarante);
        app(TenantManager::class)->setTenantContext($this->tenantDeclarante->id);

        Invoice::factory()->count(3)->create([
            'tenant_id' => $this->tenantDeclarante->id,
            'document_type' => 'invoice',
        ]);

        $response = $this->getJson('/api/v1/invoices?per_page=2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(3, $response->json('meta')['total']);
    }

    public function test_get_single_invoice(): void
    {
        $this->actingAs($this->userDeclarante);
        app(TenantManager::class)->setTenantContext($this->tenantDeclarante->id);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenantDeclarante->id,
            'document_type' => 'invoice',
            'status' => 'issued',
        ]);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonPath('data.document_number', $invoice->document_number);
    }

    public function test_cancel_issued_invoice(): void
    {
        $this->actingAs($this->userDeclarante);
        app(TenantManager::class)->setTenantContext($this->tenantDeclarante->id);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenantDeclarante->id,
            'document_type' => 'invoice',
            'status' => 'issued',
        ]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/cancel", [
            'reason' => 'Error en datos del cliente',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $response->json('data.status'));
    }

    public function test_cannot_cancel_draft_invoice(): void
    {
        $this->actingAs($this->userDeclarante);
        app(TenantManager::class)->setTenantContext($this->tenantDeclarante->id);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenantDeclarante->id,
            'document_type' => 'invoice',
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/cancel", [
            'reason' => 'Probando',
        ]);

        $response->assertStatus(422);
    }
}
