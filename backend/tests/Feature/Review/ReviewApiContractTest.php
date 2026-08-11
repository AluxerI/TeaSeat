<?php

namespace Tests\Feature\Review;

use App\Models\Product;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReviewApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_named_routes_match_the_reviews_openapi_contract(): void
    {
        $openApi = file_get_contents(base_path('docs/openapi/reviews-api.yaml'));
        $this->assertIsString($openApi);

        foreach ($this->routes() as $name => [$method, $uri, $auth, $permission]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertInstanceOf(LaravelRoute::class, $route, "Нет маршрута {$name}");
            $this->assertSame($uri, $route->uri(), "Изменился URI {$name}");
            $this->assertContains($method, $route->methods(), "Изменился метод {$name}");

            $middleware = $route->gatherMiddleware();
            if ($auth) {
                $this->assertContains('auth:sanctum', $middleware, "Нет Sanctum у {$name}");
            } else {
                $this->assertNotContains('auth:sanctum', $middleware, "{$name} должен быть публичным");
            }
            if ($permission !== null) {
                $this->assertContains("permission:{$permission}", $middleware);
            }
            $this->assertSame(
                1,
                substr_count($openApi, "x-laravel-route-name: {$name}"),
                "Маршрут {$name} не зафиксирован ровно один раз"
            );
        }
    }

    public function test_customer_and_manager_routes_reject_anonymous_requests(): void
    {
        $product = Product::factory()->create();

        $this->getJson('/api/reviews/mine')->assertUnauthorized();
        $this->postJson("/api/products/{$product->id}/reviews", [])->assertUnauthorized();
        $this->getJson('/api/manager/reviews')->assertUnauthorized();
        $this->getJson('/api/manager/order-feedback')->assertUnauthorized();
    }

    /** @return array<string, array{string,string,bool,string|null}> */
    private function routes(): array
    {
        return [
            'product.reviews.index' => ['GET', 'api/products/{product}/reviews', false, null],
            'product.reviews.store' => ['POST', 'api/products/{product}/reviews', true, null],
            'reviews.mine' => ['GET', 'api/reviews/mine', true, null],
            'reviews.update' => ['PUT', 'api/reviews/{review}', true, null],
            'orders.feedback.show' => ['GET', 'api/orders/{order}/feedback', true, null],
            'orders.feedback.store' => ['POST', 'api/orders/{order}/feedback', true, null],
            'order-feedback.update' => ['PUT', 'api/order-feedback/{feedback}', true, null],
            'manager.reviews.index' => ['GET', 'api/manager/reviews', true, 'view reviews'],
            'manager.reviews.show' => ['GET', 'api/manager/reviews/{review}', true, 'view reviews'],
            'manager.reviews.hide' => ['POST', 'api/manager/reviews/{review}/hide', true, 'moderate reviews'],
            'manager.reviews.restore' => ['POST', 'api/manager/reviews/{review}/restore', true, 'moderate reviews'],
            'manager.reviews.reply' => ['PUT', 'api/manager/reviews/{review}/reply', true, 'moderate reviews'],
            'manager.order-feedback.index' => ['GET', 'api/manager/order-feedback', true, 'view reviews'],
            'manager.order-feedback.show' => ['GET', 'api/manager/order-feedback/{feedback}', true, 'view reviews'],
            'manager.order-feedback.hide' => ['POST', 'api/manager/order-feedback/{feedback}/hide', true, 'moderate reviews'],
            'manager.order-feedback.restore' => ['POST', 'api/manager/order-feedback/{feedback}/restore', true, 'moderate reviews'],
        ];
    }
}
