<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sub_subcategoryProduct;
use Illuminate\Support\Facades\DB;

class CategoryProductsSeeder extends Seeder
{
    public function run()
    {

        Sub_subcategoryProduct::factory()->count(10)-> create();
    }
}
