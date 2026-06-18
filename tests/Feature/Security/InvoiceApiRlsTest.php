<?php

declare(strict_types=1);

/**
 * RLS integration tests for invoice API and document_sequences.
 *
 * @see \Tests\Feature\Facturacion\Api\InvoiceApiTest
 */

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InvoiceApiRlsTest extends TestCase
{
    use RefreshDatabase;

    private TenantManager $tenantManager;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('database.connections.pgsql-rls')) {
            $this->markTestSkipped('pgsql-rls connection not configured.');
        }

        $this->tenantManager = app(TenantManager::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'RLS Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'RLS Tenant B']);
    }

    protected function tearDown(): void
    {
        $this->tenantManager->clearTenantContext();
        parent::tearDown();
    }

    public function test_cannot_read_other_tenant_invoice_via_rls(): void
    {
        DB::table('invoices')->insert([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $this->tenantA->id,
            'document_type' => 'invoice',
            'prefix' => 'FE',
            'sequence' => 1,
            'document_number' => 'FE-000001',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $invoices = DB::connection('pgsql-rls')
            ->table('invoices')
            ->where('document_number', 'FE-000001')
            ->get();

        $this->assertCount(0, $invoices, 'Tenant B should NOT see Tenant A\'s invoice via RLS.');
    }

    public function test_document_sequence_rls(): void
    {
        DB::table('document_sequences')->insert([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $this->tenantA->id,
            'type' => 'invoice',
            'prefix' => 'FE',
            'last_sequence' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $sequences = DB::connection('pgsql-rls')
            ->table('document_sequences')
            ->where('type', 'invoice')
            ->get();

        $this->assertCount(0, $sequences, 'Tenant B should NOT see Tenant A\'s document sequences via RLS.');
    }

    public function test_insert_without_context_fails(): void
    {
        $this->expectExceptionMessageMatches(
            '/tenant_context_missing|P0001|permission denied for table document_sequences/'
        );

        DB::connection('pgsql-rls')
            ->table('document_sequences')
            ->insert([
                'id' => DB::connection('pgsql-rls')->raw('gen_random_uuid()'),
                'tenant_id' => $this->tenantA->id,
                'type' => 'invoice',
                'prefix' => 'FE',
                'last_sequence' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
