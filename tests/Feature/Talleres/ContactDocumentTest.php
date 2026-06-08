<?php

declare(strict_types=1);

namespace Tests\Feature\Talleres;

use App\Enums\DocumentTypeEnum;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactDocumentTest extends TestCase
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

    public function test_contact_accepts_document_fields(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'contact_type' => 'client',
            'name' => 'Juan Pérez',
            'document_type' => 'CC',
            'document_number' => '1234567890',
            'city' => 'Bogotá',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'document_type' => 'CC',
            'document_number' => '1234567890',
            'city' => 'Bogotá',
        ]);

        $this->assertEquals('CC', $contact->document_type->value);
        $this->assertEquals('1234567890', $contact->document_number);
    }

    public function test_document_type_enum_has_five_cases(): void
    {
        $cases = DocumentTypeEnum::cases();

        $this->assertCount(5, $cases);

        $this->assertTrue(DocumentTypeEnum::tryFrom('CC') instanceof DocumentTypeEnum);
        $this->assertTrue(DocumentTypeEnum::tryFrom('NIT') instanceof DocumentTypeEnum);
        $this->assertTrue(DocumentTypeEnum::tryFrom('CE') instanceof DocumentTypeEnum);
        $this->assertTrue(DocumentTypeEnum::tryFrom('PAS') instanceof DocumentTypeEnum);
        $this->assertTrue(DocumentTypeEnum::tryFrom('TI') instanceof DocumentTypeEnum);
        $this->assertNull(DocumentTypeEnum::tryFrom('INVALID'));
    }

    public function test_contact_document_number_index_exists(): void
    {
        $indexes = DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'contacts'
              AND indexname = 'idx_contacts_document'
        ");

        $this->assertCount(1, $indexes);

        $indexDef = $indexes[0]->indexdef;
        $this->assertStringContainsString('tenant_id', $indexDef);
        $this->assertStringContainsString('document_number', $indexDef);
        $this->assertStringContainsString('WHERE', $indexDef);
    }
}
