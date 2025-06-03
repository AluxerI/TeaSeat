<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NameProduct;
use Illuminate\Support\Facades\DB;

class NameProductSeeder extends Seeder
{
    public function run()
    {

        NameProduct::factory()->count(10)-> create();
        echo('created');
    }
}
