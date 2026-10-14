<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    public function saved(Product $product): void
    {
        $this->bumpListingVersion();
    }

    public function deleted(Product $product): void
    {
        $this->bumpListingVersion();
    }

    public function restored(Product $product): void
    {
        $this->bumpListingVersion();
    }

    private function bumpListingVersion(): void
    {
        // add() only writes when the key is missing, so increment() always has something to bump
        Cache::add('products:version', 1);
        Cache::increment('products:version');
    }
}
