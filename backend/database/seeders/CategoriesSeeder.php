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
        // Для каждой подкатегории — 3 под-подкатегории
        Sub_subcategory::factory()->count(3)->create();
    }
}
