<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'status' => Order::STATUS_CART,
            'products_total' => 0,
            'promotion_discount' => 0,
            'personal_discount' => 0,
            'cart_discount' => 0,
            'shipping_cost' => 0,
            'shipping_discount' => 0,
            'final_total' => 0,
            'pricing_snapshot' => null,
        ];
    }

    public function cart()
    {
        return $this->state([
            'status' => Order::STATUS_CART,
        ]);
    }

    public function pending()
    {
        return $this->state([
            'status' => Order::STATUS_PENDING,
        ]);
    }

    public function completed()
    {
        return $this->state([
            'status' => Order::STATUS_DELIVERED,
        ]);
    }
}
