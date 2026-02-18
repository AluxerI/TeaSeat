<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'path' => 'products/' . $this->faker->numberBetween(1, 100) . '/' . $this->faker->uuid . '.jpg',
            'disk' => 'public',
            'sort_order' => 0,
            'is_main' => false,
            'is_background' => false,
            'alt' => $this->faker->sentence(3),
            'title' => $this->faker->sentence(2),
        ];
    }

    /**
     * Состояние для главного изображения
     */
    public function main(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_main' => true,
                'is_background' => false,
                'sort_order' => 0,
            ];
        });
    }

    /**
     * Состояние для фонового изображения
     */
    public function background(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_main' => false,
                'is_background' => true,
                'sort_order' => 1, // Фоновое обычно после главного
            ];
        });
    }

    /**
     * Состояние для обычного изображения галереи
     */
    public function gallery(int $sortOrder = 2): static
    {
        return $this->state(function (array $attributes) use ($sortOrder) {
            return [
                'is_main' => false,
                'is_background' => false,
                'sort_order' => $sortOrder,
            ];
        });
    }
    
    /**
     * Добавляет изображения к товару
     */
    protected function addImagesToProduct(Product $product): void
    {
        // Проверяем, есть ли уже изображения
        if ($product->images()->count() > 0) {
            return;
        }

        $faker = \Faker\Factory::create();

        // Создаем главное изображение
        ProductImage::factory()
            ->main()
            ->create([
                'product_id' => $product->id,
                'path' => "products/{$product->id}/main.jpg",
                'alt' => "Главное изображение {$product->name}",
                'title' => $product->name,
            ]);

        // Создаем фоновое изображение
        ProductImage::factory()
            ->background()
            ->create([
                'product_id' => $product->id,
                'path' => "products/{$product->id}/background.jpg",
                'alt' => "Фоновое изображение {$product->name}",
                'title' => $product->name,
            ]);

        // Случайно добавляем дополнительные изображения в галерею
        $extraImagesCount = $faker->numberBetween(0, 5);
        for ($i = 0; $i < $extraImagesCount; $i++) {
            ProductImage::factory()
                ->gallery(2 + $i)
                ->create([
                    'product_id' => $product->id,
                    'path' => "products/{$product->id}/gallery-" . ($i + 1) . ".jpg",
                    'alt' => "Дополнительное изображение " . ($i + 1) . " товара {$product->name}",
                    'title' => $product->name,
                ]);
        }
    }
}