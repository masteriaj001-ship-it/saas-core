<?php

declare(strict_types=1);

namespace Tests\Feature\Facturacion;

use App\Filament\Pages\PosPage;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosPageTest extends TestCase
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
        Filament::setCurrentPanel(app('filament')->getPanel('admin'));
        Filament::setTenant($this->tenant);
    }

    public function test_pos_page_renders(): void
    {
        Livewire::test(PosPage::class)
            ->assertStatus(200);
    }

    public function test_pos_page_shows_items_in_stock(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['stock' => 5, 'price' => 10000]);

        Livewire::test(PosPage::class)
            ->assertSee($item->name)
            ->assertSee('$10.000');
    }

    public function test_pos_page_hides_out_of_stock_items(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['stock' => 0]);

        Livewire::test(PosPage::class)
            ->assertDontSee($item->name);
    }

    public function test_pos_page_search_filters_items(): void
    {
        Item::factory()->product()->for($this->tenant)->create(['name' => 'Filtro de aceite', 'stock' => 10]);
        Item::factory()->product()->for($this->tenant)->create(['name' => 'Pastilla de freno', 'stock' => 10]);

        Livewire::test(PosPage::class)
            ->set('search', 'aceite')
            ->assertSee('Filtro de aceite')
            ->assertDontSee('Pastilla de freno');
    }

    public function test_pos_page_category_filter(): void
    {
        Item::factory()->spare()->for($this->tenant)->create(['name' => 'Filtro', 'stock' => 5]);
        Item::factory()->product()->for($this->tenant)->create(['name' => 'Aceite', 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->set('selectedCategory', 'spare')
            ->assertSee('Filtro')
            ->assertDontSee('Aceite');
    }

    public function test_pos_page_add_item_to_cart(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 25000, 'stock' => 10]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->assertSet('cart', [
                [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'price' => 25000.0,
                    'quantity' => 1,
                    'subtotal' => 25000.0,
                ],
            ]);
    }

    public function test_pos_page_add_item_increments_quantity(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('addItem', $item->id)
            ->assertSet('cart.0.quantity', 2)
            ->assertSet('cart.0.subtotal', 20000.0);
    }

    public function test_pos_page_remove_item_from_cart(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('removeItem', 0)
            ->assertSet('cart', []);
    }

    public function test_pos_page_update_quantity(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('updateQuantity', 0, 3)
            ->assertSet('cart.0.quantity', 3)
            ->assertSet('cart.0.subtotal', 30000.0);
    }

    public function test_pos_page_rejects_quantity_exceeding_stock(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 2]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('updateQuantity', 0, 5)
            ->assertSet('cart.0.quantity', 1);
    }

    public function test_pos_page_calculates_cart_subtotal(): void
    {
        $item1 = Item::factory()->product()->for($this->tenant)->create(['price' => 20000, 'stock' => 10]);
        $item2 = Item::factory()->spare()->for($this->tenant)->create(['price' => 30000, 'stock' => 10]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item1->id)
            ->call('addItem', $item2->id)
            ->assertSet('cartSubtotal', 50000.0);
    }

    public function test_pos_page_clear_cart(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('clearCart')
            ->assertSet('cart', []);
    }

    public function test_pos_page_set_payment_method(): void
    {
        Livewire::test(PosPage::class)
            ->call('setPaymentMethod', 'card')
            ->assertSet('paymentMethod', 'card');
    }

    public function test_pos_page_opens_payment_modal(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('openPayment')
            ->assertSet('showPaymentModal', true);
    }

    public function test_pos_page_cannot_open_payment_with_empty_cart(): void
    {
        Livewire::test(PosPage::class)
            ->call('openPayment')
            ->assertSet('showPaymentModal', false);
    }

    public function test_pos_page_calculates_change(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 25000, 'stock' => 10]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->set('amountReceived', 30000)
            ->assertSet('change', 5000.0);
    }

    public function test_pos_page_checkout_creates_invoice(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 15000, 'stock' => 10]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->set('paymentMethod', 'cash')
            ->set('amountReceived', 15000)
            ->call('checkout')
            ->assertSet('cart', [])
            ->assertSet('showPaymentModal', false);

        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $this->tenant->id,
            'document_type' => 'pos',
            'status' => 'paid',
            'grand_total' => 15000,
        ]);
    }

    public function test_pos_page_categories_property(): void
    {
        Item::factory()->spare()->for($this->tenant)->create(['stock' => 3]);
        Item::factory()->product()->for($this->tenant)->create(['stock' => 5]);

        $component = Livewire::test(PosPage::class);
        $categories = $component->get('categories');

        $this->assertIsArray($categories);
        $this->assertGreaterThanOrEqual(2, count($categories));

        $spareCat = collect($categories)->firstWhere('key', 'spare');
        $this->assertNotNull($spareCat);
        $this->assertEquals('Repuestos', $spareCat['label']);
        $this->assertEquals(1, $spareCat['total']);
    }


}
