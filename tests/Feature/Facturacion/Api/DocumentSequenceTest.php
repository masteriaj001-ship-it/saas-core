<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Facturacion\Services\DocumentSequenceService;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSequenceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private DocumentSequenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->service = app(DocumentSequenceService::class);
    }

    public function test_invoice_sequence_increments(): void
    {
        $first = $this->service->nextSequence($this->tenant, 'invoice');
        $second = $this->service->nextSequence($this->tenant, 'invoice');

        $this->assertEquals(1, $first);
        $this->assertEquals(2, $second);

        $this->assertDatabaseHas('document_sequences', [
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'last_sequence' => 2,
        ]);
    }

    public function test_pos_sequence_independent(): void
    {
        $invoiceSeq = $this->service->nextSequence($this->tenant, 'invoice');
        $posSeq = $this->service->nextSequence($this->tenant, 'pos');

        $this->assertEquals(1, $invoiceSeq);
        $this->assertEquals(1, $posSeq);
    }

    public function test_sequence_per_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        $seqA = $this->service->nextSequence($this->tenant, 'invoice');
        app(TenantManager::class)->setTenantContext($otherTenant->id);
        $seqB = $this->service->nextSequence($otherTenant, 'invoice');

        $this->assertEquals(1, $seqA);
        $this->assertEquals(1, $seqB);
    }
}
