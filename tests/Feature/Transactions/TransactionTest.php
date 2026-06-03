<?php

declare(strict_types=1);

namespace Tests\Feature\Transactions;

use App\Models\Contact;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\TenantManager;
use App\Services\Transactions\TransactionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private TenantManager $tenantManager;

    private TransactionService $service;

    private User $admin;

    private Contact $client;

    private Contact $supplier;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantManager = app(TenantManager::class);

        $this->tenant = Tenant::factory()->create();
        $this->tenantManager->setTenantContext($this->tenant->id);

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('owner');

        $this->client = Contact::factory()->client()->create(['tenant_id' => $this->tenant->id]);
        $this->supplier = Contact::factory()->supplier()->create(['tenant_id' => $this->tenant->id]);
        $this->item = Item::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service = app(TransactionService::class);

        $this->actingAs($this->admin);
    }

    public function test_can_create_sale_transaction(): void
    {
        $transaction = $this->service->createWithItems([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->client->id,
            'type' => 'sale',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ], [
            [
                'item_id' => $this->item->id,
                'quantity' => 2,
                'unit_price' => 50000,
                'tax_rate' => 19,
                'tax_amount' => 0,
                'total_item_amount' => 0,
            ],
        ]);

        $this->assertNotNull($transaction->id);
        $this->assertEquals('sale', $transaction->type);
        $this->assertEquals('draft', $transaction->status);
        $this->assertStringStartsWith('FAC-', $transaction->invoice_number);
        $this->assertEquals(100000, $transaction->subtotal);
        $this->assertEquals(19000, $transaction->total_tax);
        $this->assertEquals(119000, $transaction->total_amount);
        $this->assertCount(1, $transaction->items);
    }

    public function test_can_create_purchase_transaction(): void
    {
        $transaction = $this->service->createWithItems([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->supplier->id,
            'type' => 'purchase',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ], [
            [
                'item_id' => $this->item->id,
                'quantity' => 10,
                'unit_price' => 25000,
                'tax_rate' => 19,
                'tax_amount' => 0,
                'total_item_amount' => 0,
            ],
        ]);

        $this->assertEquals('purchase', $transaction->type);
        $this->assertStringStartsWith('OC-', $transaction->invoice_number);
        $this->assertEquals(250000, $transaction->subtotal);
        $this->assertEquals(47500, $transaction->total_tax);
    }

    public function test_invoice_number_counter_increments(): void
    {
        $data = [
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->client->id,
            'type' => 'sale',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ];

        $t1 = $this->service->createWithItems($data, [['item_id' => $this->item->id, 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0, 'tax_amount' => 0, 'total_item_amount' => 0]]);
        $t2 = $this->service->createWithItems($data, [['item_id' => $this->item->id, 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0, 'tax_amount' => 0, 'total_item_amount' => 0]]);
        $t3 = $this->service->createWithItems($data, [['item_id' => $this->item->id, 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0, 'tax_amount' => 0, 'total_item_amount' => 0]]);

        $this->assertEquals('FAC-00001', $t1->invoice_number);
        $this->assertEquals('FAC-00002', $t2->invoice_number);
        $this->assertEquals('FAC-00003', $t3->invoice_number);

        $this->tenant->refresh();
        $this->assertEquals(3, $this->tenant->settings['transactions']['sale_counter']);
    }

    public function test_can_issue_transaction(): void
    {
        $transaction = Transaction::factory()->sale()->draft()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->client->id,
            'created_by' => $this->admin->id,
        ]);

        $this->service->issue($transaction);

        $transaction->refresh();
        $this->assertEquals('issued', $transaction->status);
        $this->assertNotNull($transaction->cufe);
        $this->assertStringStartsWith('CUFE-', $transaction->cufe);
    }

    public function test_can_cancel_issued_transaction(): void
    {
        $transaction = Transaction::factory()->sale()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'status' => 'issued',
            'cufe' => 'CUFE-'.strtoupper((string) Str::uuid()),
        ]);

        $this->service->cancel($transaction);

        $transaction->refresh();
        $this->assertEquals('cancelled', $transaction->status);
    }

    public function test_cannot_issue_already_issued_transaction(): void
    {
        $this->expectException(\RuntimeException::class);

        $transaction = Transaction::factory()->sale()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->client->id,
            'created_by' => $this->admin->id,
            'status' => 'issued',
            'cufe' => 'CUFE-'.strtoupper((string) Str::uuid()),
        ]);

        $this->service->issue($transaction);
    }

    public function test_cannot_cancel_draft_transaction(): void
    {
        $this->expectException(\RuntimeException::class);

        $transaction = Transaction::factory()->sale()->draft()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->client->id,
            'created_by' => $this->admin->id,
        ]);

        $this->service->cancel($transaction);
    }

    public function test_tenant_isolation_prevents_cross_tenant_access(): void
    {
        $tenantA = $this->tenant;
        $clientA = $this->client;

        $tenantB = Tenant::factory()->create();
        $clientB = Contact::factory()->client()->create(['tenant_id' => $tenantB->id]);

        DB::statement('SELECT set_config(\'app.current_tenant_id\', ?, false)', [$tenantA->id]);
        Transaction::factory()->sale()->issued()->create([
            'tenant_id' => $tenantA->id,
            'contact_id' => $clientA->id,
            'created_by' => $this->admin->id,
        ]);

        DB::statement('SELECT set_config(\'app.current_tenant_id\', ?, false)', [$tenantB->id]);
        Transaction::factory()->sale()->issued()->create([
            'tenant_id' => $tenantB->id,
            'contact_id' => $clientB->id,
            'created_by' => $this->admin->id,
        ]);

        DB::statement('SELECT set_config(\'app.current_tenant_id\', ?, false)', [$tenantA->id]);
        $transactionsForA = Transaction::count();

        DB::statement('SELECT set_config(\'app.current_tenant_id\', ?, false)', [$tenantB->id]);
        $transactionsForB = Transaction::count();

        $this->assertEquals(1, $transactionsForA);
        $this->assertEquals(1, $transactionsForB);
    }

    public function test_recalculate_updates_totals_after_item_changes(): void
    {
        $transaction = Transaction::factory()->sale()->draft()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->client->id,
            'created_by' => $this->admin->id,
        ]);

        TransactionItem::create([
            'tenant_id' => $this->tenant->id,
            'transaction_id' => $transaction->id,
            'item_id' => $this->item->id,
            'quantity' => 3,
            'unit_price' => 100000,
            'tax_rate' => 19,
            'tax_amount' => 0,
            'total_item_amount' => 0,
        ]);

        $this->service->recalculateFromItems($transaction);
        $transaction->refresh();

        $this->assertEquals(300000, $transaction->subtotal);
        $this->assertEquals(57000, $transaction->total_tax);
        $this->assertEquals(357000, $transaction->total_amount);
    }
}
