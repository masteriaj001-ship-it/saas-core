<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Facturacion\Services\InvoiceCodeGenerator;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);
    }

    public function test_generates_sequential_document_number(): void
    {
        $generator = app(InvoiceCodeGenerator::class);

        $first = $generator->next($this->tenant->id);
        $this->assertEquals('FV', $first['prefix']);
        $this->assertEquals(1, $first['sequence']);
        $this->assertEquals('FV-000001', $first['document_number']);

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'invoice',
            'prefix' => $first['prefix'],
            'sequence' => $first['sequence'],
            'document_number' => $first['document_number'],
            'status' => 'draft',
        ]);

        $second = $generator->next($this->tenant->id);
        $this->assertEquals(2, $second['sequence']);
        $this->assertEquals('FV-000002', $second['document_number']);

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'invoice',
            'prefix' => $second['prefix'],
            'sequence' => $second['sequence'],
            'document_number' => $second['document_number'],
            'status' => 'draft',
        ]);

        $third = $generator->next($this->tenant->id);
        $this->assertEquals(3, $third['sequence']);
        $this->assertEquals('FV-000003', $third['document_number']);
    }

    public function test_sequence_is_per_tenant(): void
    {
        $generator = app(InvoiceCodeGenerator::class);

        $first = $generator->next($this->tenant->id);
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'invoice',
            'prefix' => $first['prefix'],
            'sequence' => $first['sequence'],
            'document_number' => $first['document_number'],
            'status' => 'draft',
        ]);

        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->for($tenantB)->create();
        $this->actingAs($userB);
        app(TenantManager::class)->setTenantContext($tenantB->id);

        $firstB = $generator->next($tenantB->id);
        $this->assertEquals(1, $firstB['sequence']);
        $this->assertEquals('FV-000001', $firstB['document_number']);
    }

    public function test_prefix_is_configurable(): void
    {
        $generator = app(InvoiceCodeGenerator::class);

        $result = $generator->next($this->tenant->id, 'NC');

        $this->assertEquals('NC', $result['prefix']);
        $this->assertEquals(1, $result['sequence']);
        $this->assertEquals('NC-000001', $result['document_number']);
    }
}
