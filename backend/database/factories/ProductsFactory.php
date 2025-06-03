<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Products;
use App\Models\NameProduct;


class ProductsFactory extends Factory
{
    protected $model = Products::class;
    public function definition(): array
    {
        return [
            'idname' => NameProduct::get()-> random() ->id,
            'priceproduct' => fake()->numberBetween(100, 10000),
            'discount' => fake()->numberBetween(1, 100),
            'idcategory' => Category::get()-> random() ->id,
            'oder' => random_int(1,5)
        ];
    }
}
