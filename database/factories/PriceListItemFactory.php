<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\Tenant;
use App\Modules\Inventario\Models\PriceList;
use App\Modules\Inventario\Models\PriceListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceListItemFactory extends Factory
{
    protected $model = PriceListItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'price_list_id' => PriceList::factory(),
            'item_id' => Item::factory(),
            'price' => fake()->randomFloat(2, 1000, 1000000),
            'min_quantity' => 1,
            'metadata' => '{}',
        ];
    }

    public function wholesale(): static
    {
        return $this->state(fn (array $attrs) => [
            'min_quantity' => fake()->numberBetween(5, 50),
        ]);
    }
}
