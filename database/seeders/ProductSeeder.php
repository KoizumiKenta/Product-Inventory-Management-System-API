<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::exists()) {
            return;
        }

        $suppliers = Supplier::pluck('id')->all();

        foreach (Category::all() as $category) {
            $products = Product::factory()->count(9)->for($category)->create()
                ->concat(Product::factory()->count(2)->lowStock()->for($category)->create())
                ->concat(Product::factory()->count(1)->outOfStock()->for($category)->create());

            foreach ($products as $product) {
                $pivot = [];

                foreach (Arr::random($suppliers, random_int(1, min(3, count($suppliers)))) as $id) {
                    $pivot[$id] = ['unit_cost' => round($product->price * random_int(50, 80) / 100, 2)];
                }

                $product->suppliers()->attach($pivot);
            }
        }
    }
}
