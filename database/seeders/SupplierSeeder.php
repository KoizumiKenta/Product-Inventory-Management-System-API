<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $missing = 8 - Supplier::count();

        if ($missing > 0) {
            Supplier::factory()->count($missing)->create();
        }
    }
}
