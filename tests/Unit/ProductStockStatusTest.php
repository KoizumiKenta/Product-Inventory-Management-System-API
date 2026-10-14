<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductStockStatusTest extends TestCase
{
    public function test_zero_stock_is_out_of_stock(): void
    {
        $this->assertSame('out_of_stock', $this->productWithStock(0)->stock_status);
    }

    public function test_stock_up_to_the_threshold_is_low(): void
    {
        $this->assertSame('low_stock', $this->productWithStock(1)->stock_status);
        $this->assertSame('low_stock', $this->productWithStock(Product::LOW_STOCK_THRESHOLD)->stock_status);
    }

    public function test_stock_above_the_threshold_is_in_stock(): void
    {
        $this->assertSame('in_stock', $this->productWithStock(Product::LOW_STOCK_THRESHOLD + 1)->stock_status);
    }

    private function productWithStock(int $quantity): Product
    {
        return new Product(['stock_quantity' => $quantity]);
    }
}
