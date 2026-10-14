<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => Str::title(fake()->words(3, true)),
            'sku' => strtoupper(fake()->unique()->bothify('???-#####')),
            'description' => fake()->sentence(12),
            'price' => fake()->randomFloat(2, 1, 999),
            'stock_quantity' => fake()->numberBetween(Product::LOW_STOCK_THRESHOLD + 1, 500),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => fake()->numberBetween(1, Product::LOW_STOCK_THRESHOLD),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }
}
