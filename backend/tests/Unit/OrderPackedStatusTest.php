<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderPackedStatusTest extends TestCase
{
    public function test_packed_is_a_non_terminal_internal_fulfillment_status(): void
    {
        $order = new Order(['status' => Order::STATUS_PACKED]);

        $this->assertSame('Собран', $order->status_name);
        $this->assertSame('Собран', Order::getStatusName(Order::STATUS_PACKED));
        $this->assertFalse($order->isCompleted());
    }
}
