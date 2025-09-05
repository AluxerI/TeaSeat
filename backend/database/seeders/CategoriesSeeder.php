<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Sub_subcategory;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        Category::factory()->count(8)->create();
        Subcategory::factory()->count(11)->create();
        Sub_subcategory::factory()->count(120)->create();
    }
}
