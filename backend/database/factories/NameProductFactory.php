<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\NameProduct;

class NameProductFactory extends Factory
{
    protected $model = NameProduct::class;
    public function definition(): array
    {
        
        return [
            'nameitem' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->paragraph(),
            'imageitem' => $this->faker->imageUrl(400, 300, 'products', true)
            
        ];
    }
}



