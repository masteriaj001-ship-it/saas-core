<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemStockTest extends TestCase
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

    public function test_spare_item_has_stock(): void
    {
        $item = Item::factory()->spare()->for($this->tenant)->create(['stock' => 10]);

        $this->assertTrue($item->hasStock(1));
        $this->assertTrue($item->hasStock(10));
    }

    public function test_spare_item_out_of_stock(): void
    {
        $item = Item::factory()->spare()->for($this->tenant)->create(['stock' => 0]);

        $this->assertFalse($item->hasStock(1));
    }

    public function test_spare_item_insufficient_stock(): void
    {
        $item = Item::factory()->spare()->for($this->tenant)->create(['stock' => 3]);

        $this->assertTrue($item->hasStock(2));
        $this->assertFalse($item->hasStock(5));
    }

    public function test_service_item_always_available(): void
    {
        $service = Item::factory()->service()->for($this->tenant)->create(['stock' => 0]);

        $this->assertEquals(0, $service->stock);
        $this->assertEquals('service', $service->item_type);
    }

    public function test_low_stock_factory_state(): void
    {
        $item = Item::factory()->lowStock()->for($this->tenant)->create();

        $this->assertLessThanOrEqual($item->min_stock, $item->stock);
    }

    public function test_stock_displayed_in_pos(): void
    {
        $item = Item::factory()->spare()->for($this->tenant)->create(['stock' => 15]);

        $this->assertEquals(15, $item->stock);
    }

    public function test_min_stock_field(): void
    {
        $item = Item::factory()->spare()->for($this->tenant)->create([
            'stock' => 20,
            'min_stock' => 10,
        ]);

        $this->assertEquals(10, $item->min_stock);
        $this->assertGreaterThan($item->min_stock, $item->stock);
    }

    public function test_item_price_is_set(): void
    {
        $item = Item::factory()->spare()->for($this->tenant)->create(['price' => 85000]);

        $this->assertEquals(85000, $item->price);
    }

    public function test_item_sku_is_unique_per_tenant(): void
    {
        Item::factory()->spare()->for($this->tenant)->create(['sku' => 'UNIQUE-001']);

        $this->expectException(QueryException::class);

        Item::factory()->spare()->for($this->tenant)->create(['sku' => 'UNIQUE-001']);
    }

    public function test_item_types_available(): void
    {
        $spare = Item::factory()->spare()->for($this->tenant)->create();
        $product = Item::factory()->product()->for($this->tenant)->create();
        $service = Item::factory()->service()->for($this->tenant)->create();

        $this->assertEquals('spare', $spare->item_type);
        $this->assertEquals('product', $product->item_type);
        $this->assertEquals('service', $service->item_type);
    }
}
