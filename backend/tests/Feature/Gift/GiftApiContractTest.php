<?php

namespace Tests\Feature\Gift;

use App\Models\GiftSizeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class GiftApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_gift_routes_match_the_openapi_contract(): void
    {
        $contract = Yaml::parseFile(base_path('docs/openapi/gifts-api.yaml'));

        $this->assertSame('3.1.0', $contract['openapi'] ?? null);
        $this->assertSame('/api', $contract['servers'][0]['url'] ?? null);
        $this->assertArrayHasKey(
            'sanctum',
            $contract['components']['securitySchemes'] ?? []
        );

        foreach ($this->contractRoutes() as $name => [$method, $uri, $permission]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertInstanceOf(
                LaravelRoute::class,
                $route,
                "Маршрут {$name} отсутствует"
            );
            $this->assertSame($uri, $route->uri(), "Изменился URI маршрута {$name}");
            $this->assertContains($method, $route->methods(), "Изменился метод маршрута {$name}");

            $middleware = $route->gatherMiddleware();
            $this->assertContains(
                'auth:sanctum',
                $middleware,
                "Маршрут {$name} больше не защищён Sanctum"
            );
            if ($permission !== null) {
                $this->assertContains(
                    "permission:{$permission}",
                    $middleware,
                    "Изменилось право маршрута {$name}"
                );
            }

            $path = substr($uri, 3);
            $operation = $contract['paths'][$path][strtolower($method)] ?? null;
            $this->assertIsArray(
                $operation,
                "Операция {$method} {$path} отсутствует в OpenAPI"
            );
            $this->assertSame(
                $name,
                $operation['x-laravel-route-name'] ?? null,
                "OpenAPI ссылается не на тот Laravel-маршрут для {$method} {$path}"
            );
            $this->assertNotEmpty(
                $operation['responses'] ?? [],
                "Для {$method} {$path} не описаны ответы"
            );
        }
    }

    public function test_simple_options_publish_box_counts_and_duplicate_rule(): void
    {
        $user = User::factory()->create();
        GiftSizeProfile::query()->create([
            'code' => 'contract-large-box',
            'name' => 'Большой набор',
            'kind' => GiftSizeProfile::KIND_BOX,
            'width_cells' => 4,
            'height_cells' => 3,
            'default_markup_amount' => 250,
            'simple_constructor_enabled' => true,
            'simple_tea_count' => 5,
            'simple_sweet_count' => 2,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/gift-constructor/simple/options')
            ->assertOk()
            ->assertJsonCount(1, 'data.boxes')
            ->assertJsonPath('data.boxes.0.simple_requirements.tea_count', 5)
            ->assertJsonPath('data.boxes.0.simple_requirements.sweet_count', 2)
            ->assertJsonPath('data.boxes.0.simple_requirements.total_items', 7)
            ->assertJsonPath(
                'data.boxes.0.simple_requirements.allow_duplicate_products',
                true
            )
            ->assertJsonPath(
                'data.selection_rules.allow_duplicate_products',
                true
            );
    }

    public function test_openapi_uses_plural_sweet_ids_and_dynamic_box_requirements(): void
    {
        $contract = Yaml::parseFile(base_path('docs/openapi/gifts-api.yaml'));
        $schemas = $contract['components']['schemas'] ?? [];
        $simpleRequest = $schemas['SimpleGiftCreateRequest'] ?? [];
        $properties = $simpleRequest['properties'] ?? [];

        $this->assertArrayHasKey('tea_product_size_ids', $properties);
        $this->assertArrayHasKey('sweet_product_size_ids', $properties);
        $this->assertArrayNotHasKey('sweet_product_size_id', $properties);
        $this->assertContains('sweet_product_size_ids', $simpleRequest['required'] ?? []);
        $this->assertTrue(
            $schemas['SimpleRequirements']['properties']['allow_duplicate_products']['const']
                ?? false
        );
    }

    public function test_customer_gift_routes_reject_requests_without_a_token(): void
    {
        $this->getJson('/api/gift-constructor/simple/options')->assertUnauthorized();
        $this->getJson('/api/gift-constructor/boxes/1/products')->assertUnauthorized();
        $this->getJson('/api/gifts')->assertUnauthorized();
        $this->postJson('/api/cart/gifts', [])->assertUnauthorized();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    private function contractRoutes(): array
    {
        return [
            'gift-constructor.boxes.products' => ['GET', 'api/gift-constructor/boxes/{box}/products', null],
            'gift-constructor.advanced.options' => ['GET', 'api/gift-constructor/advanced/options', null],
            'gift-constructor.advanced.validate' => ['POST', 'api/gift-constructor/advanced/validate-layout', null],
            'gift-constructor.advanced.quote' => ['POST', 'api/gift-constructor/advanced/quote', null],
            'gift-constructor.advanced.store' => ['POST', 'api/gift-constructor/advanced/gifts', null],
            'gift-constructor.simple.options' => ['GET', 'api/gift-constructor/simple/options', null],
            'gift-constructor.simple.quote' => ['POST', 'api/gift-constructor/simple/quote', null],
            'gift-constructor.simple.store' => ['POST', 'api/gift-constructor/simple/gifts', null],
            'gifts.index' => ['GET', 'api/gifts', null],
            'gifts.show' => ['GET', 'api/gifts/{gift}', null],
            'gifts.update' => ['PUT', 'api/gifts/{gift}', null],
            'gifts.destroy' => ['DELETE', 'api/gifts/{gift}', null],
            'cart.gifts.store' => ['POST', 'api/cart/gifts', null],
            'cart.gifts.update' => ['PUT', 'api/cart/gifts/{orderGift}', null],
            'cart.gifts.destroy' => ['DELETE', 'api/cart/gifts/{orderGift}', null],
            'picker.assembled-gifts.index' => ['GET', 'api/picker/assembled-gifts', 'view picking orders'],
            'picker.assembled-gifts.replenish' => ['POST', 'api/picker/assembled-gifts/{product}/replenish', 'manage own picking orders'],
        ];
    }
}
