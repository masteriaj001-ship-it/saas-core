<?php

declare(strict_types=1);

/**
 * Application-level tenant isolation for the auxiliary web endpoints
 * (invoice PDF, invoice ticket and POS print).
 *
 * Runs over the default `pgsql` connection: proves the route middleware and
 * controller guards. It does NOT prove that PostgreSQL RLS policies were
 * applied.
 */

namespace Tests\Feature\Security;

use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Models\Invoice;
use App\Modules\Plataforma\Services\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WebInvoiceTenantIsolationAppScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $superadmin;

    private Invoice $invoiceA;

    private Invoice $invoiceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->tenantA = Tenant::factory()->create([
            'name' => 'Web Tenant A',
            'onboarding_completed' => true,
            'is_active' => true,
        ]);
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Web Tenant B',
            'onboarding_completed' => true,
            'is_active' => true,
        ]);

        $this->userA = User::factory()->for($this->tenantA)->create();
        $this->superadmin = User::factory()->create([
            'tenant_id' => null,
            'is_superadmin' => true,
        ]);

        $this->invoiceA = $this->createInvoice($this->tenantA, ['status' => 'issued']);
        $this->invoiceB = $this->createInvoice($this->tenantB, ['status' => 'issued']);
    }

    // ── PDF ────────────────────────────────────────────────────────

    public function test_user_can_download_own_invoice_pdf(): void
    {
        $this->actingAs($this->userA);

        $this->get("/invoices/{$this->invoiceA->id}/pdf")->assertStatus(200);
    }

    public function test_user_cannot_download_foreign_invoice_pdf(): void
    {
        $this->actingAs($this->userA);

        $this->get("/invoices/{$this->invoiceB->id}/pdf")->assertStatus(404);
    }

    // ── Ticket ─────────────────────────────────────────────────────

    public function test_user_can_view_own_invoice_ticket(): void
    {
        $this->actingAs($this->userA);

        $this->get("/invoices/{$this->invoiceA->id}/ticket")->assertStatus(200);
    }

    public function test_user_cannot_view_foreign_invoice_ticket(): void
    {
        $this->actingAs($this->userA);

        $this->get("/invoices/{$this->invoiceB->id}/ticket")->assertStatus(404);
    }

    // ── POS print ──────────────────────────────────────────────────

    public function test_user_can_print_own_invoice(): void
    {
        $this->actingAs($this->userA);

        $this->postJson('/pos/print', ['invoice_id' => $this->invoiceA->id])
            ->assertStatus(200)
            ->assertJsonPath('ok', true);
    }

    public function test_user_cannot_print_foreign_invoice(): void
    {
        $this->actingAs($this->userA);

        $this->postJson('/pos/print', ['invoice_id' => $this->invoiceB->id])
            ->assertStatus(404);

        $this->assertSame(
            'issued',
            DB::table('invoices')->where('id', $this->invoiceB->id)->value('status'),
        );
    }

    public function test_user_cannot_print_foreign_invoice_by_unknown_id(): void
    {
        $this->actingAs($this->userA);

        $this->postJson('/pos/print', ['invoice_id' => '00000000-0000-0000-0000-000000000000'])
            ->assertStatus(404);
    }

    public function test_malformed_invoice_id_returns_404_instead_of_500(): void
    {
        $this->actingAs($this->userA);

        $this->postJson('/pos/print', ['invoice_id' => 'not-a-uuid'])
            ->assertStatus(404);
    }

    public function test_non_scalar_invoice_id_returns_404_instead_of_500(): void
    {
        $this->actingAs($this->userA);

        $this->postJson('/pos/print', ['invoice_id' => ['not-a-uuid']])
            ->assertStatus(404);
    }

    // ── Superadmin ─────────────────────────────────────────────────

    public function test_superadmin_without_impersonation_is_denied(): void
    {
        $this->actingAs($this->superadmin);

        $this->get("/invoices/{$this->invoiceA->id}/pdf")->assertStatus(403);
    }

    public function test_superadmin_with_impersonation_session_can_access(): void
    {
        $this->actingAs($this->superadmin);

        app(ImpersonationService::class)->start($this->superadmin, $this->tenantA);

        $this->get("/invoices/{$this->invoiceA->id}/pdf")->assertStatus(200);
    }

    public function test_impersonated_superadmin_still_cannot_reach_other_tenant(): void
    {
        $this->actingAs($this->superadmin);

        app(ImpersonationService::class)->start($this->superadmin, $this->tenantA);

        $this->get("/invoices/{$this->invoiceB->id}/pdf")->assertStatus(404);
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
