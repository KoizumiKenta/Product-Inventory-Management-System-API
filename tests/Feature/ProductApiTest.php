<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_index_returns_a_paginated_list_with_relations(): void
    {
        $suppliers = Supplier::factory()->count(2)->create();
        $products = Product::factory()->count(3)->create();

        foreach ($products as $product) {
            $product->suppliers()->attach($suppliers, ['unit_cost' => 12.50]);
        }

        $this->getJson('/api/v1/products?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'sku',
                        'description',
                        'price',
                        'stock_quantity',
                        'stock_status',
                        'category' => ['id', 'name', 'slug'],
                        'suppliers' => [['id', 'name', 'email', 'unit_cost']],
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_store_creates_a_product_and_attaches_suppliers(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::factory()->create();

        $response = $this->postJson('/api/v1/products', [
            'category_id' => $category->id,
            'name' => 'Mechanical Keyboard',
            'sku' => ' kb-9001 ',
            'description' => 'Hot swappable switches, 87 keys.',
            'price' => 349.9,
            'stock_quantity' => 40,
            'suppliers' => [
                ['id' => $supplier->id, 'unit_cost' => 210.50],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'KB-9001')
            ->assertJsonPath('data.price', '349.90')
            ->assertJsonPath('data.stock_status', 'in_stock')
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonCount(1, 'data.suppliers');

        $this->assertDatabaseHas('products', ['sku' => 'KB-9001', 'name' => 'Mechanical Keyboard']);
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $response->json('data.id'),
            'supplier_id' => $supplier->id,
        ]);
    }

    public function test_store_requires_the_core_fields(): void
    {
        $this->postJson('/api/v1/products', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'name', 'sku', 'price', 'stock_quantity']);
    }

    public function test_show_returns_a_single_product(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/v1/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', $product->sku)
            ->assertJsonPath('data.category.id', $product->category_id);
    }

    public function test_update_applies_a_partial_change(): void
    {
        $product = Product::factory()->create([
            'name' => 'Desk Lamp',
            'price' => 59,
            'stock_quantity' => 12,
        ]);

        $this->patchJson('/api/v1/products/'.$product->id, ['name' => 'Desk Lamp V2'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Desk Lamp V2')
            ->assertJsonPath('data.price', '59.00')
            ->assertJsonPath('data.stock_quantity', 12);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Desk Lamp V2']);
    }

    public function test_a_product_can_be_soft_deleted_and_restored(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson('/api/v1/products/'.$product->id)->assertNoContent();

        $this->assertSoftDeleted($product);
        $this->getJson('/api/v1/products/'.$product->id)->assertNotFound();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');

        $this->postJson('/api/v1/products/'.$product->id.'/restore')
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $this->assertNotSoftDeleted($product);
    }
}
