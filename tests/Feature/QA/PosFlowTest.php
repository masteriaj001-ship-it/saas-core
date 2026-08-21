<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Filament\Pages\PosPage;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosFlowTest extends TestCase
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

    public function test_pos_shows_products_with_stock(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['stock' => 5, 'price' => 10000]);

        Livewire::test(PosPage::class)
            ->assertSee($item->name);
    }

    public function test_pos_shows_services_even_without_stock(): void
    {
        $item = Item::factory()->service()->for($this->tenant)->create(['stock' => 0, 'price' => 50000]);

        Livewire::test(PosPage::class)
            ->assertSee($item->name);
    }

    public function test_pos_hides_products_with_zero_stock(): void
    {
        $item = Item::factory()->spare()->for($this->tenant)->create(['stock' => 0]);

        Livewire::test(PosPage::class)
            ->assertDontSee($item->name);
    }

    public function test_pos_search_by_name(): void
    {
        Item::factory()->product()->for($this->tenant)->create(['name' => 'Filtro de aceite', 'stock' => 10]);
        Item::factory()->product()->for($this->tenant)->create(['name' => 'Pastilla de freno', 'stock' => 10]);

        Livewire::test(PosPage::class)
            ->set('search', 'aceite')
            ->assertSee('Filtro de aceite')
            ->assertDontSee('Pastilla de freno');
    }

    public function test_pos_search_by_sku(): void
    {
        Item::factory()->product()->for($this->tenant)->create(['sku' => 'REP-001', 'stock' => 10]);
        Item::factory()->product()->for($this->tenant)->create(['sku' => 'REP-002', 'stock' => 10]);

        Livewire::test(PosPage::class)
            ->set('search', 'REP-001')
            ->assertSee('REP-001')
            ->assertDontSee('REP-002');
    }

    public function test_pos_category_filter_spare(): void
    {
        Item::factory()->spare()->for($this->tenant)->create(['name' => 'Filtro', 'stock' => 5]);
        Item::factory()->product()->for($this->tenant)->create(['name' => 'Aceite', 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->set('selectedCategory', 'spare')
            ->assertSee('Filtro')
            ->assertDontSee('Aceite');
    }

    public function test_pos_category_filter_service(): void
    {
        Item::factory()->service()->for($this->tenant)->create(['name' => 'Cambio de aceite', 'stock' => 0]);
        Item::factory()->spare()->for($this->tenant)->create(['name' => 'Filtro', 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->set('selectedCategory', 'service')
            ->assertSee('Cambio de aceite')
            ->assertDontSee('Filtro');
    }

    public function test_pos_add_item_to_cart(): void
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

    public function test_pos_add_item_increments_quantity(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('addItem', $item->id)
            ->assertSet('cart.0.quantity', 2)
            ->assertSet('cart.0.subtotal', 20000.0);
    }

    public function test_pos_update_quantity(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('updateQuantity', 0, 3)
            ->assertSet('cart.0.quantity', 3)
            ->assertSet('cart.0.subtotal', 30000.0);
    }

    public function test_pos_remove_item(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('removeItem', 0)
            ->assertSet('cart', []);
    }

    public function test_pos_clear_cart(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('clearCart')
            ->assertSet('cart', []);
    }

    public function test_pos_cart_subtotal_calculation(): void
    {
        $item1 = Item::factory()->product()->for($this->tenant)->create(['price' => 20000, 'stock' => 10]);
        $item2 = Item::factory()->spare()->for($this->tenant)->create(['price' => 30000, 'stock' => 10]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item1->id)
            ->call('addItem', $item2->id)
            ->assertSet('cartSubtotal', 50000.0);
    }

    public function test_pos_change_calculation(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 25000, 'stock' => 10]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->set('amountReceived', 30000)
            ->assertSet('change', 5000.0);
    }

    public function test_pos_payment_method_card(): void
    {
        Livewire::test(PosPage::class)
            ->call('setPaymentMethod', 'card')
            ->assertSet('paymentMethod', 'card');
    }

    public function test_pos_opens_payment_modal(): void
    {
        $item = Item::factory()->product()->for($this->tenant)->create(['price' => 10000, 'stock' => 5]);

        Livewire::test(PosPage::class)
            ->call('addItem', $item->id)
            ->call('openPayment')
            ->assertSet('showPaymentModal', true);
    }

    public function test_pos_cannot_open_payment_empty_cart(): void
    {
        Livewire::test(PosPage::class)
            ->call('openPayment')
            ->assertSet('showPaymentModal', false);
    }

    public function test_pos_add_service_to_cart_no_stock_check(): void
    {
        $service = Item::factory()->service()->for($this->tenant)->create(['price' => 50000, 'stock' => 0]);

        Livewire::test(PosPage::class)
            ->call('addItem', $service->id)
            ->assertSet('cart.0.quantity', 1)
            ->assertSet('cart.0.price', 50000.0);
    }

    public function test_pos_service_unlimited_quantity(): void
    {
        $service = Item::factory()->service()->for($this->tenant)->create(['price' => 50000, 'stock' => 0]);

        Livewire::test(PosPage::class)
            ->call('addItem', $service->id)
            ->call('updateQuantity', 0, 100)
            ->assertSet('cart.0.quantity', 100)
            ->assertSet('cart.0.subtotal', 5000000.0);
    }

    public function test_pos_categories_count(): void
    {
        Item::factory()->spare()->for($this->tenant)->create(['stock' => 3]);
        Item::factory()->product()->for($this->tenant)->create(['stock' => 5]);
        Item::factory()->service()->for($this->tenant)->create(['stock' => 0]);

        $component = Livewire::test(PosPage::class);
        $categories = $component->get('categories');

        $this->assertIsArray($categories);
        $this->assertGreaterThanOrEqual(3, count($categories));
    }
}
