<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subcategory;
use Illuminate\Support\Facades\DB;

class SubcategorySeeder extends Seeder
{
    public function run()
    {

        Subcategory::factory()->count(100)-> create();
    }
}
