<?php

namespace Tests\Feature\Gift;

use App\Models\GiftSizeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GiftBoxImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_constructor_options_expose_the_box_image_url(): void
    {
        $box = GiftSizeProfile::query()->create([
            'code' => 'box-with-image',
            'name' => 'Коробка с изображением',
            'kind' => GiftSizeProfile::KIND_BOX,
            'image_path' => 'gift-boxes/box.webp',
            'width_cells' => 3,
            'height_cells' => 2,
            'default_markup_amount' => 100,
            'is_active' => true,
        ]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/gift-constructor/advanced/options')
            ->assertOk()
            ->assertJsonCount(1, 'data.boxes');

        $this->assertSame($box->id, $response->json('data.boxes.0.id'));
        $this->assertStringEndsWith(
            '/storage/gift-boxes/box.webp',
            $response->json('data.boxes.0.image_url')
        );
    }

    public function test_replacing_and_deleting_a_box_image_removes_old_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gift-boxes/old.webp', 'old');
        Storage::disk('public')->put('gift-boxes/new.webp', 'new');

        $box = GiftSizeProfile::query()->create([
            'code' => 'box-file-cleanup',
            'name' => 'Коробка с очисткой файлов',
            'kind' => GiftSizeProfile::KIND_BOX,
            'image_path' => 'gift-boxes/old.webp',
            'width_cells' => 2,
            'height_cells' => 2,
            'default_markup_amount' => 0,
            'is_active' => true,
        ]);

        $box->update(['image_path' => 'gift-boxes/new.webp']);

        Storage::disk('public')->assertMissing('gift-boxes/old.webp');
        Storage::disk('public')->assertExists('gift-boxes/new.webp');

        $box->delete();

        Storage::disk('public')->assertMissing('gift-boxes/new.webp');
    }

    public function test_item_profiles_never_publish_a_box_image_url(): void
    {
        $profile = GiftSizeProfile::query()->create([
            'code' => 'item-with-stale-image',
            'name' => 'Размер товара',
            'kind' => GiftSizeProfile::KIND_ITEM,
            'image_path' => 'gift-boxes/stale.webp',
            'width_cells' => 1,
            'height_cells' => 1,
            'is_active' => true,
        ]);

        $this->assertNull($profile->image_path);
        $this->assertNull($profile->image_url);
    }
}
