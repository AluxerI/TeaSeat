<?php

namespace Database\Seeders;

use App\Models\GiftSizeProfile;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Database\Seeder;

class ProductSizeSeeder extends Seeder
{
    public function run(): void
    {
        $itemProfiles = GiftSizeProfile::query()
            ->where('kind', GiftSizeProfile::KIND_ITEM)
            ->where('is_active', true)
            ->orderBy('width_cells')
            ->orderBy('height_cells')
            ->get();

        if ($itemProfiles->isEmpty()) {
            $this->command?->warn('Нет активных профилей размеров товара (kind=item). Пропускаю ProductSizeSeeder.');

            return;
        }

        $roles = [
            ProductSize::ROLE_TEA,
            ProductSize::ROLE_SWEET,
            ProductSize::ROLE_GENERAL,
        ];

        $products = Product::query()
            ->where('is_available', true)
            ->orderBy('id')
            ->get();

        $created = 0;
        foreach ($products as $index => $product) {
            // Равномерно распределяем роли по доступному ассортименту.
            $role = $roles[$index % count($roles)];
            // Чередуем профили размеров, чтобы продвинутый конструктор имел разные габариты.
            $profile = $itemProfiles[$index % $itemProfiles->count()];

            $label = sprintf('%s (%s)', $product->name, $profile->name);

            ProductSize::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'product_quantity' => 1,
                ],
                [
                    'gift_size_profile_id' => $profile->id,
                    'label' => $label,
                    'constructor_role' => $role,
                    'is_active' => true,
                ]
            );

            $created++;
        }

        $this->command?->info(sprintf(
            'ProductSizeSeeder: создано/обновлено %d размеров для %d товаров.',
            $created,
            $products->count()
        ));
    }
}
