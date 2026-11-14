<?php

declare(strict_types=1);

/**
 * Application-level tenant isolation for the invoice API.
 *
 * These tests run over the default `pgsql` connection and prove the request
 * middleware, Eloquent tenant scope and controller guards. They do NOT prove
 * that PostgreSQL RLS policies were applied — see InvoiceApiRlsTest for that.
 *
 * @see \Tests\Feature\Security\InvoiceApiRlsTest
 */

namespace Tests\Feature\Security;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InvoiceApiTenantIsolationAppScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    private string $tokenA;

    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['name' => 'App Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'App Tenant B']);

        $this->userA = User::factory()->for($this->tenantA)->create();
        $this->userB = User::factory()->for($this->tenantB)->create();

        $this->tokenA = $this->userA->createToken('sec004-app-scope')->plainTextToken;
        $this->tokenB = $this->userB->createToken('sec004-app-scope')->plainTextToken;
    }

    public function test_authenticated_token_establishes_tenant_context(): void
    {
        $invoiceA = $this->createInvoice($this->tenantA);
        $this->createInvoice($this->tenantB);

        $response = $this->withToken($this->tokenA)->getJson('/api/v1/invoices');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($invoiceA->id, $response->json('data.0.id'));
    }

    public function test_user_a_cannot_show_tenant_b_invoice(): void
    {
        $invoiceB = $this->createInvoice($this->tenantB);

        $this->withToken($this->tokenA)
            ->getJson("/api/v1/invoices/{$invoiceB->id}")
            ->assertStatus(404);
    }

    public function test_user_a_cannot_update_tenant_b_invoice(): void
    {
        $invoiceB = $this->createInvoice($this->tenantB, [
            'status' => 'draft',
            'notes' => 'original',
        ]);

        $this->withToken($this->tokenA)
            ->patchJson("/api/v1/invoices/{$invoiceB->id}", ['notes' => 'hacked'])
            ->assertStatus(404);

        $this->assertSame(
            'original',
            DB::table('invoices')->where('id', $invoiceB->id)->value('notes'),
        );
    }

    public function test_user_a_cannot_cancel_tenant_b_invoice(): void
    {
        $invoiceB = $this->createInvoice($this->tenantB, ['status' => 'issued']);

        $this->withToken($this->tokenA)
            ->postJson("/api/v1/invoices/{$invoiceB->id}/cancel", ['reason' => 'cross tenant'])
            ->assertStatus(404);

        $this->assertSame(
            'issued',
            DB::table('invoices')->where('id', $invoiceB->id)->value('status'),
        );
    }

    public function test_tenant_id_in_query_string_is_ignored(): void
    {
        $invoiceA = $this->createInvoice($this->tenantA);
        $this->createInvoice($this->tenantB);

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/invoices?tenant_id='.$this->tenantB->id);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($invoiceA->id, $response->json('data.0.id'));
    }

    public function test_tenant_header_is_ignored(): void
    {
        $invoiceA = $this->createInvoice($this->tenantA);
        $this->createInvoice($this->tenantB);

        $response = $this->withToken($this->tokenA)
            ->withHeader('X-Tenant-Id', $this->tenantB->id)
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($invoiceA->id, $response->json('data.0.id'));
    }

    public function test_tenant_id_in_payload_is_ignored(): void
    {
        $response = $this->withToken($this->tokenA)->postJson('/api/v1/invoices', [
            'tenant_id' => $this->tenantB->id,
            'contact_name' => 'Cliente Aislamiento',
            'items' => [
                ['description' => 'Trabajo', 'quantity' => 1, 'unit_price' => 10],
            ],
        ]);

        $response->assertStatus(201);

        $storedTenantId = DB::table('invoices')
            ->where('id', $response->json('data.id'))
            ->value('tenant_id');

        $this->assertSame($this->tenantA->id, $storedTenantId);
    }

    public function test_nested_item_tenant_id_is_ignored(): void
    {
        $response = $this->withToken($this->tokenA)->postJson('/api/v1/invoices', [
            'contact_name' => 'Cliente Items',
            'items' => [
                [
                    'description' => 'Trabajo',
                    'quantity' => 1,
                    'unit_price' => 10,
                    'tenant_id' => $this->tenantB->id,
                ],
            ],
        ]);

        $response->assertStatus(201);

        $itemTenantIds = DB::table('invoice_items')
            ->where('invoice_id', $response->json('data.id'))
            ->pluck('tenant_id')
            ->unique()
            ->all();

        $this->assertSame([$this->tenantA->id], $itemTenantIds);
    }

    public function test_superadmin_token_without_tenant_context_is_denied(): void
    {
        $superadmin = User::factory()->create([
            'tenant_id' => null,
            'is_superadmin' => true,
        ]);

        $token = $superadmin->createToken('sec004-superadmin')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/invoices')->assertStatus(403);
    }

    public function test_user_without_tenant_assignment_is_denied(): void
    {
        // The application cannot create this user: BelongsToTenant's creating
        // hook rejects a User with a null tenant_id when there is no tenant
        // context and no authenticated user (RuntimeException). The state is
        // therefore forced through SQL, which is the only way to exercise a
        // user whose tenant assignment was removed after creation.
        $tenant = Tenant::factory()->create(['name' => 'No Tenant Assignment']);
        $user = User::factory()->for($tenant)->create();
        DB::table('users')->where('id', $user->id)->update(['tenant_id' => null]);

        $token = $user->createToken('sec004-no-tenant')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/invoices')->assertStatus(403);
    }

    public function test_revoked_context_is_not_reused_across_requests(): void
    {
        $invoiceA = $this->createInvoice($this->tenantA);

        $this->withToken($this->tokenA)->getJson('/api/v1/invoices')->assertStatus(200);

        // A second request from tenant B must not inherit tenant A's context.
        // Production gets this for free: php-fpm serves every request from a
        // fresh process, so neither the auth guard nor the tenant context
        // survives between requests. Inside a single test process both do
        // survive, so they are reset here to emulate a fresh request:
        //   - forgetGuards() drops Sanctum's RequestGuard, which caches the
        //     resolved user for the lifetime of the guard instance;
        //   - clearTenantContext() drops the TenantManager singleton, whose
        //     tenant_id would otherwise keep filtering user B's tokenable
        //     lookup through the BelongsToTenant global scope.
        $this->app['auth']->forgetGuards();
        app(TenantManager::class)->clearTenantContext();

        $response = $this->withToken($this->tokenB)->getJson('/api/v1/invoices');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
        $this->assertNotSame($invoiceA->id, $response->json('data.0.id') ?? null);
    }

    private function createInvoice(Tenant $tenant, array $overrides = []): Invoice
    {
        $contact = Contact::factory()->client()->create(['tenant_id' => $tenant->id]);

        return Invoice::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'document_type' => 'invoice',
        ], $overrides));
    }
}
