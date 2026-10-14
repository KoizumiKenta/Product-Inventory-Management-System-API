<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_products_can_be_filtered_by_category(): void
    {
        $office = Category::factory()->create();
        $grocery = Category::factory()->create();

        Product::factory()->count(2)->for($office)->create();
        Product::factory()->for($grocery)->create();

        $this->getJson('/api/v1/products?category_id='.$office->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.category.id', $office->id);
    }

    public function test_products_can_be_filtered_by_price_range(): void
    {
        Product::factory()->create(['price' => 15]);
        Product::factory()->create(['price' => 80]);
        Product::factory()->create(['price' => 250]);

        $this->getJson('/api/v1/products?min_price=50&max_price=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price', '80.00');
    }

    public function test_products_can_be_filtered_by_stock_status(): void
    {
        Product::factory()->create(['stock_quantity' => 120]);
        Product::factory()->lowStock()->create();
        Product::factory()->outOfStock()->create();

        $this->getJson('/api/v1/products?stock_status=in_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stock_quantity', 120);

        $this->getJson('/api/v1/products?stock_status=low_stock')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stock_status', 'low_stock');

        $this->getJson('/api/v1/products?stock_status=out_of_stock')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stock_quantity', 0);
    }

    public function test_products_can_be_searched_by_name(): void
    {
        Product::factory()->create(['name' => 'Ergonomic Office Chair']);
        Product::factory()->create(['name' => 'Standing Desk']);

        $this->getJson('/api/v1/products?search=chair')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ergonomic Office Chair');
    }

    public function test_search_treats_wildcards_as_literal_characters(): void
    {
        Product::factory()->create(['name' => 'Standing Desk']);
        Product::factory()->create(['name' => 'Clearance 50% Off Lamp']);

        $this->getJson('/api/v1/products?search=%25')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Clearance 50% Off Lamp');
    }

    public function test_products_can_be_sorted_by_price_descending(): void
    {
        Product::factory()->create(['price' => 20]);
        Product::factory()->create(['price' => 500]);
        Product::factory()->create(['price' => 130]);

        $prices = $this->getJson('/api/v1/products?sort=-price')
            ->assertOk()
            ->json('data.*.price');

        $this->assertSame(['500.00', '130.00', '20.00'], $prices);
    }

    public function test_a_per_page_above_the_maximum_is_rejected(): void
    {
        $this->getJson('/api/v1/products?per_page=250')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }
}
