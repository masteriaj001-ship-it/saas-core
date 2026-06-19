<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Budget\Models\Budget;
use App\Modules\Budget\Models\BudgetItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetItemFactory extends Factory
{
    protected $model = BudgetItem::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'description' => fake()->sentence(3),
            'quantity' => fake()->randomFloat(2, 1, 5),
            'unit_price' => fake()->randomFloat(2, 10000, 200000),
            'discount' => 0,
            'tax_rate' => 0,
            'subtotal' => 0,
            'total' => 0,
            'sort_order' => 0,
        ];
    }
}
