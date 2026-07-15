<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_delivery_hub')->default(false);
            $table->index(
                ['city', 'is_active', 'is_delivery_hub'],
                'warehouses_delivery_hub_index'
            );
        });

        // Для существующих тестовых данных назначаем по одной точке
        // консолидации в каждом городе. Администратор сможет изменить выбор.
        DB::statement(<<<'SQL'
            UPDATE warehouses
            SET is_delivery_hub = TRUE
            WHERE id IN (
                SELECT DISTINCT ON (city) id
                FROM warehouses
                WHERE city IS NOT NULL AND is_active = TRUE
                ORDER BY city,
                    CASE WHEN type = 'warehouse' THEN 0 ELSE 1 END,
                    id
            )
        SQL);
        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->string('type', 16)->default('external');
            $table->string('provider_code', 64)->nullable();
            $table->index(['type', 'is_active']);
            $table->index('provider_code');
        });

        DB::statement(<<<'SQL'
            UPDATE delivery_methods
            SET type = CASE
                WHEN LOWER(name) LIKE '%самовывоз%' THEN 'pickup'
                WHEN LOWER(name) LIKE '%экспресс%' THEN 'express'
                WHEN LOWER(name) LIKE '%курьер%' THEN 'courier'
                ELSE 'external'
            END
        SQL);
        DB::statement(<<<'SQL'
            UPDATE delivery_methods
            SET provider_code = 'russian_post'
            WHERE type = 'external' AND LOWER(name) LIKE '%почт%росси%'
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE delivery_methods
            ADD CONSTRAINT delivery_methods_type_check
            CHECK (type IN ('courier', 'express', 'pickup', 'external'))
        SQL);

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('destination_warehouse_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->foreignId('picker_id')
                ->nullable()
                ->after('destination_warehouse_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('courier_id')
                ->nullable()
                ->after('picker_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('picking_started_at')->nullable();
            $table->timestamp('ready_for_delivery_at')->nullable();
            $table->timestamp('courier_assigned_at')->nullable();
            $table->timestamp('courier_arrived_at')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->index(
                ['warehouse_id', 'status', 'picker_id'],
                'orders_picker_queue_index'
            );
            $table->index(
                ['warehouse_id', 'status', 'courier_id'],
                'orders_courier_queue_index'
            );
            $table->index(
                ['destination_warehouse_id', 'status'],
                'orders_transfer_destination_index'
            );
        });

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

        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
            DROP CONSTRAINT IF EXISTS inventory_movements_type_check
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
            ADD CONSTRAINT inventory_movements_type_check
            CHECK (type IN (
                'opening_balance',
                'online_reserve',
                'online_release',
                'online_sale',
                'online_return',
                'seller_reserve',
                'seller_release',
                'seller_sale',
                'restock',
                'adjustment',
                'transfer_in',
                'transfer_out'
            ))
        SQL);
    }

    public function down(): void
    {
        DB::statement("UPDATE inventory_movements SET type = 'adjustment' WHERE type = 'online_return'");
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
            DROP CONSTRAINT IF EXISTS inventory_movements_type_check
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE inventory_movements
            ADD CONSTRAINT inventory_movements_type_check
            CHECK (type IN (
                'opening_balance',
                'online_reserve',
                'online_release',
                'online_sale',
                'seller_reserve',
                'seller_release',
                'seller_sale',
                'restock',
                'adjustment',
                'transfer_in',
                'transfer_out'
            ))
        SQL);

        DB::statement("UPDATE orders SET status = 'processing' WHERE status = 'ready_for_delivery'");
        DB::statement("UPDATE orders SET status = 'shipped' WHERE status = 'awaiting_receipt'");
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_status_check
            CHECK (status IN (
                'cart',
                'pending',
                'confirmed',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
                'seller_review',
                'manager_review',
                'completed'
            ))
        SQL);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_picker_queue_index');
            $table->dropIndex('orders_courier_queue_index');
            $table->dropIndex('orders_transfer_destination_index');
            $table->dropConstrainedForeignId('courier_id');
            $table->dropConstrainedForeignId('picker_id');
            $table->dropConstrainedForeignId('destination_warehouse_id');
            $table->dropColumn([
                'picking_started_at',
                'ready_for_delivery_at',
                'courier_assigned_at',
                'courier_arrived_at',
                'received_at',
            ]);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE delivery_methods
            DROP CONSTRAINT IF EXISTS delivery_methods_type_check
        SQL);
        Schema::table('delivery_methods', function (Blueprint $table) {
            $table->dropIndex(['type', 'is_active']);
            $table->dropIndex(['provider_code']);
            $table->dropColumn(['type', 'provider_code']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndex('warehouses_delivery_hub_index');
            $table->dropColumn('is_delivery_hub');
        });
    }
};
