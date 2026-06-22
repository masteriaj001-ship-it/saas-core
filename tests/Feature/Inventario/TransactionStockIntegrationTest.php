<?php

declare(strict_types=1);

namespace Tests\Feature\Inventario;

use App\Models\Contact;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Inventario\Models\StockMovement;
use App\Modules\Inventario\Models\Warehouse;
use App\Services\TenantManager;
use App\Services\Transactions\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TransactionStockIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Warehouse $warehouse;

    private Contact $contact;

    private TransactionService $transactionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed' => true]);
        $this->user = User::factory()->for($this->tenant)->create();
        $this->warehouse = Warehouse::factory()->default()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->contact = Contact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->user);
        app(TenantManager::class)->setTenantContext($this->tenant->id);

        $this->transactionService = app(TransactionService::class);
    }

    public function test_transaction_issue_creates_stock_movement(): void
    {
        $item = Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 50,
        ]);

        $transaction = $this->transactionService->createWithItems(
            [
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'contact_id' => $this->contact->id,
                'type' => 'sale',
                'status' => 'draft',
            ],
            [
                [
                    'tenant_id' => $this->tenant->id,
                    'item_id' => $item->id,
                    'quantity' => 3,
                    'unit_price' => 10000,
                    'subtotal' => 30000,
                    'total' => 30000,
                ],
            ]
        );

        $this->transactionService->issue($transaction);

        $this->assertTrue($transaction->fresh()->isIssued());

        $movement = StockMovement::where('item_id', $item->id)->first();

        $this->assertNotNull($movement, 'Stock movement should be created after issuing.');
        $this->assertEquals('exit', $movement->movement_type);
        $this->assertEquals(-3, $movement->quantity);
    }

    public function test_transaction_cancel_reverts_stock(): void
    {
        $item = Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 50,
        ]);

        $transaction = $this->transactionService->createWithItems(
            [
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'contact_id' => $this->contact->id,
                'type' => 'sale',
                'status' => 'draft',
            ],
            [
                [
                    'tenant_id' => $this->tenant->id,
                    'item_id' => $item->id,
                    'quantity' => 3,
                    'unit_price' => 10000,
                    'subtotal' => 30000,
                    'total' => 30000,
                ],
            ]
        );

        $this->transactionService->issue($transaction);
        $this->transactionService->cancel($transaction->fresh());

        $movements = StockMovement::where('item_id', $item->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $movements, 'Issue + cancel should create 2 movements.');
        $this->assertEquals('exit', $movements[0]->movement_type);
        $this->assertEquals(-3, $movements[0]->quantity);
        $this->assertEquals('entry', $movements[1]->movement_type);
        $this->assertEquals(3, $movements[1]->quantity);
    }

    public function test_service_items_do_not_create_stock_movements(): void
    {
        $service = Item::factory()->service()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $transaction = $this->transactionService->createWithItems(
            [
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'contact_id' => $this->contact->id,
                'type' => 'sale',
                'status' => 'draft',
            ],
            [
                [
                    'tenant_id' => $this->tenant->id,
                    'item_id' => $service->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                    'subtotal' => 50000,
                    'total' => 50000,
                ],
            ]
        );

        $this->transactionService->issue($transaction);

        $movements = StockMovement::where('item_id', $service->id)->count();

        $this->assertEquals(0, $movements, 'Service items should not create stock movements.');
    }

    public function test_purchase_transaction_entry_creates_stock_movement(): void
    {
        $item = Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id,
            'stock' => 0,
        ]);

        $transaction = $this->transactionService->createWithItems(
            [
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'contact_id' => $this->contact->id,
                'type' => 'purchase',
                'status' => 'draft',
            ],
            [
                [
                    'tenant_id' => $this->tenant->id,
                    'item_id' => $item->id,
                    'quantity' => 10,
                    'unit_price' => 5000,
                    'subtotal' => 50000,
                    'total' => 50000,
                ],
            ]
        );

        $this->transactionService->issue($transaction);

        $movement = StockMovement::where('item_id', $item->id)->first();

        $this->assertNotNull($movement, 'Purchase should create an entry movement.');
        $this->assertEquals('entry', $movement->movement_type);
        $this->assertEquals(10, $movement->quantity);
    }
}
