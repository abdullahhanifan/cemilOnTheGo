<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $buyPrice = fake()->numberBetween(5, 200) * 1000;

        return [
            'store_id' => Store::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'variant' => '',
            'sku' => null,
            'unit' => 'pcs',
            'buy_price' => $buyPrice,
            'sell_price' => (int) (round($buyPrice * 1.2 / 500) * 500),
            'buy_price_checked_at' => now(),
            'notes' => null,
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
