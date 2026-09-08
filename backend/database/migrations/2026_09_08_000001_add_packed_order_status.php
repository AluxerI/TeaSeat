<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
        DB::statement(<<<'SQL'
            UPDATE orders
            SET status = 'packed', delivered_at = NULL
            WHERE parent_order_id IS NOT NULL AND status = 'delivered'
        SQL);
        DB::statement(<<<'SQL'
            UPDATE order_status_histories AS history
            SET from_status = 'packed'
            FROM orders
            WHERE history.order_id = orders.id
                AND orders.parent_order_id IS NOT NULL
                AND history.from_status = 'delivered'
        SQL);
        DB::statement(<<<'SQL'
            UPDATE order_status_histories AS history
            SET to_status = 'packed'
            FROM orders
            WHERE history.order_id = orders.id
                AND orders.parent_order_id IS NOT NULL
                AND history.to_status = 'delivered'
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_status_check
            CHECK (status IN (
                'cart',
                'pending',
                'confirmed',
                'processing',
                'ready_for_delivery',
                'shipped',
                'awaiting_receipt',
                'packed',
                'delivered',
                'cancelled',
                'seller_review',
                'manager_review',
                'completed'
            ))
        SQL);
    }

    public function down(): void
    {
        // До появления отдельного статуса packed складская готовность части
        // исторически хранилась как delivered.
        DB::statement(<<<'SQL'
            UPDATE orders
            SET status = 'delivered',
                delivered_at = COALESCE(delivered_at, received_at, ready_for_delivery_at)
            WHERE status = 'packed'
        SQL);
        DB::statement("UPDATE order_status_histories SET from_status = 'delivered' WHERE from_status = 'packed'");
        DB::statement("UPDATE order_status_histories SET to_status = 'delivered' WHERE to_status = 'packed'");
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_status_check
            CHECK (status IN (
                'cart',
                'pending',
                'confirmed',
                'processing',
                'ready_for_delivery',
                'shipped',
                'awaiting_receipt',
                'delivered',
                'cancelled',
                'seller_review',
                'manager_review',
                'completed'
            ))
        SQL);
    }
};
