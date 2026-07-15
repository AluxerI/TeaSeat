<?php

namespace Tests\Feature\Pricing;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductMeasurementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_product_can_be_added_in_sale_steps_and_backend_calculates_total(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create([
            'price' => 250,
            'stock_unit' => Product::STOCK_UNIT_GRAM,
            'sale_step' => 10,
            'price_unit_quantity' => 100,
            'weight_grams' => null,
            'is_available' => true,
            'total_quantity' => 1000,
        ]);
        $warehouse = Warehouse::factory()->create(['is_active' => true]);
        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 1000,
        ]);

        $response = $this->postJson('/api/cart/add', [
            'product_id' => $product->id,
            'quantity' => 110,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 110)
            ->assertJsonPath('data.items.0.stock_unit', Product::STOCK_UNIT_GRAM)
            ->assertJsonPath('data.items.0.sale_step', 10)
            ->assertJsonPath('data.items.0.price_unit_quantity', 100)
            ->assertJsonPath('data.items.0.total_price', 275);
    }

    public function test_weighted_product_rejects_quantity_outside_sale_step(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = Product::factory()->create([
            'price' => 250,
            'stock_unit' => Product::STOCK_UNIT_GRAM,
            'sale_step' => 10,
            'price_unit_quantity' => 100,
            'weight_grams' => null,
        ]);

        $response = $this->postJson('/api/cart/add', [
            'product_id' => $product->id,
            'quantity' => 105,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                "Количество товара «{$product->name}» должно быть кратно 10 г"
            );
    }
}
