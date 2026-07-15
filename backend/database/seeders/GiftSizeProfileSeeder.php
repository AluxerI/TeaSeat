<?php

namespace Database\Seeders;

use App\Models\GiftSizeProfile;
use Illuminate\Database\Seeder;

class GiftSizeProfileSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'item-1x1', 'name' => 'Товар 1 × 1', 'kind' => 'item', 'width_cells' => 1, 'height_cells' => 1, 'default_markup_amount' => 0],
            ['code' => 'item-2x1', 'name' => 'Товар 2 × 1', 'kind' => 'item', 'width_cells' => 2, 'height_cells' => 1, 'default_markup_amount' => 0],
            ['code' => 'item-2x2', 'name' => 'Товар 2 × 2', 'kind' => 'item', 'width_cells' => 2, 'height_cells' => 2, 'default_markup_amount' => 0],
            [
                'code' => 'box-simple-3x1',
                'name' => 'Маленький подарочный набор',
                'kind' => 'box',
                'width_cells' => 3,
                'height_cells' => 1,
                'default_markup_amount' => 150,
                'simple_constructor_enabled' => true,
                'simple_tea_count' => 2,
                'simple_sweet_count' => 1,
            ],
            [
                'code' => 'box-4x3',
                'name' => 'Большой подарочный набор',
                'kind' => 'box',
                'width_cells' => 4,
                'height_cells' => 3,
                'default_markup_amount' => 250,
                'simple_constructor_enabled' => true,
                'simple_tea_count' => 5,
                'simple_sweet_count' => 2,
            ],
        ] as $profile) {
            GiftSizeProfile::query()->updateOrCreate(
                ['code' => $profile['code']],
                $profile + ['can_rotate' => true, 'is_active' => true]
            );
        }
    }
}
