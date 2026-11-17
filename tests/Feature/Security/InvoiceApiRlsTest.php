<?php

declare(strict_types=1);

/**
 * RLS integration tests for invoice tables and document_sequences.
 *
 * Direct SQL over the `pgsql-rls` connection (app_user, NOBYPASSRLS). Test
 * rows are inserted through the default connection and committed
 * (DatabaseTruncation, not a wrapping transaction), so the second session
 * can actually observe them: the negative control only means something
 * together with the positive control ("A sees A").
 *
 * Cases for `invoices`, `invoice_items` and `invoice_payments` stay skipped
 * until the gated RLS policies migration of SEC-004 is created and executed
 * with the owner's literal APROBADO. Omitted is not verified.
 *
 * @see \Tests\Feature\Facturacion\Api\InvoiceApiTest
 */

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InvoiceApiRlsTest extends TestCase
{
    use DatabaseTruncation;

    private TenantManager $tenantManager;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantManager = app(TenantManager::class);

        if (! config('database.connections.pgsql-rls')) {
            $this->markTestSkipped('pgsql-rls connection not configured.');
        }

        $this->tenantA = Tenant::factory()->create(['name' => 'RLS Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'RLS Tenant B']);
    }

    protected function tearDown(): void
    {
        if (isset($this->tenantManager)) {
            $this->tenantManager->clearTenantContext();
        }

        // DatabaseTruncation only truncates before each test, never after it.
        // This test commits rows through the default connection so the
        // pgsql-rls session can observe them (SEC-005 C2). Those committed
        // rows survive the refresh of the remaining suites, which only
        // migrate once per process, and collide on unique indexes.
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    /**
     * Control table with real policies: positive control (A sees A) and
     * negative control (B does not see A) over the same committed row.
     */
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

        $this->tenantManager->setTenantContext($this->tenantA->id);

        $own = DB::connection('pgsql-rls')
            ->table('document_sequences')
            ->where('type', 'invoice')
            ->get();

        $this->assertCount(
            1,
            $own,
            'Positive control: tenant A must see its own row through pgsql-rls.',
        );

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $foreign = DB::connection('pgsql-rls')
            ->table('document_sequences')
            ->where('type', 'invoice')
            ->get();

        $this->assertCount(
            0,
            $foreign,
            'Tenant B should NOT see Tenant A\'s document sequences via RLS.',
        );
    }

    public function test_insert_without_context_fails(): void
    {
        $this->expectExceptionMessageMatches(
            '/tenant_context_missing|P0001/',
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

    public function test_cannot_read_other_tenant_invoice_via_rls(): void
    {
        $this->markTestSkipped(
            'requiere políticas RLS de la migración gated (SEC-004).',
        );

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

        $this->tenantManager->setTenantContext($this->tenantA->id);

        $own = DB::connection('pgsql-rls')
            ->table('invoices')
            ->where('document_number', 'FE-000001')
            ->get();

        $this->assertCount(
            1,
            $own,
            'Positive control: tenant A must see its own invoice via RLS.',
        );

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $foreign = DB::connection('pgsql-rls')
            ->table('invoices')
            ->where('document_number', 'FE-000001')
            ->get();

        $this->assertCount(
            0,
            $foreign,
            'Tenant B should NOT see Tenant A\'s invoice via RLS.',
        );
    }

    public function test_cannot_read_other_tenant_invoice_items_via_rls(): void
    {
        $this->markTestSkipped(
            'requiere políticas RLS de la migración gated (SEC-004).',
        );

        $invoiceId = 'aaaaaaa1-0000-4000-8000-000000000001';

        DB::table('invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenantA->id,
            'document_type' => 'invoice',
            'prefix' => 'FE',
            'sequence' => 1,
            'document_number' => 'FE-000001',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoice_items')->insert([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $this->tenantA->id,
            'invoice_id' => $invoiceId,
            'description' => 'RLS item',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->setTenantContext($this->tenantA->id);

        $own = DB::connection('pgsql-rls')
            ->table('invoice_items')
            ->where('description', 'RLS item')
            ->get();

        $this->assertCount(
            1,
            $own,
            'Positive control: tenant A must see its own invoice item via RLS.',
        );

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $foreign = DB::connection('pgsql-rls')
            ->table('invoice_items')
            ->where('description', 'RLS item')
            ->get();

        $this->assertCount(
            0,
            $foreign,
            'Tenant B should NOT see Tenant A\'s invoice items via RLS.',
        );
    }

    public function test_cannot_read_other_tenant_invoice_payments_via_rls(): void
    {
        $this->markTestSkipped(
            'requiere políticas RLS de la migración gated (SEC-004).',
        );

        $invoiceId = 'aaaaaaa2-0000-4000-8000-000000000002';

        DB::table('invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenantA->id,
            'document_type' => 'invoice',
            'prefix' => 'FE',
            'sequence' => 2,
            'document_number' => 'FE-000002',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoice_payments')->insert([
            'id' => DB::raw('gen_random_uuid()'),
            'tenant_id' => $this->tenantA->id,
            'invoice_id' => $invoiceId,
            'payment_method' => 'cash',
            'amount' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenantManager->setTenantContext($this->tenantA->id);

        $own = DB::connection('pgsql-rls')
            ->table('invoice_payments')
            ->where('payment_method', 'cash')
            ->get();

        $this->assertCount(
            1,
            $own,
            'Positive control: tenant A must see its own invoice payment via RLS.',
        );

        $this->tenantManager->setTenantContext($this->tenantB->id);

        $foreign = DB::connection('pgsql-rls')
            ->table('invoice_payments')
            ->where('payment_method', 'cash')
            ->get();

        $this->assertCount(
            0,
            $foreign,
            'Tenant B should NOT see Tenant A\'s invoice payments via RLS.',
        );
    }
}
