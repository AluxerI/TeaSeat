<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->string('name', 120);
            $table->foreignId('last_warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->foreignId('personal_access_token_id')
                ->nullable()
                ->constrained('personal_access_tokens')
                ->nullOnDelete();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_uuid']);
            $table->index(['user_id', 'revoked_at']);
            $table->index(['last_warehouse_id', 'last_sync_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('client_order_id')->nullable()->after('checkout_idempotency_key');
            $table->unsignedInteger('seller_revision')->default(0)->after('client_order_id');
            $table->char('last_payload_hash', 64)->nullable()->after('seller_revision');
            $table->foreignId('seller_device_id')
                ->nullable()
                ->after('last_payload_hash')
                ->constrained('staff_devices')
                ->nullOnDelete();
            $table->boolean('was_edited')->default(false)->after('seller_device_id');
            $table->timestamp('seller_occurred_at')->nullable()->after('was_edited');
            $table->timestamp('seller_synced_at')->nullable()->after('seller_occurred_at');
            $table->timestamp('seller_reviewed_at')->nullable()->after('seller_synced_at');
            $table->timestamp('seller_escalated_at')->nullable()->after('seller_reviewed_at');
            $table->timestamp('seller_completed_at')->nullable()->after('seller_escalated_at');
            $table->timestamp('seller_discount_usage_consumed_at')
                ->nullable()
                ->after('seller_completed_at');

            $table->unique(
                ['user_id', 'client_order_id'],
                'orders_user_client_order_unique'
            );
            $table->index(['sales_channel', 'user_id', 'created_at'], 'orders_seller_owner_index');
            $table->index(['seller_device_id', 'seller_synced_at'], 'orders_seller_device_index');
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
                'shipped',
                'delivered',
                'cancelled',
                'seller_review',
                'manager_review',
                'completed'
            ))
        SQL);

        Schema::create('fulfillment_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_order_id')
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->string('reason', 48);
            $table->unsignedInteger('shortage_quantity');
            $table->unsignedInteger('reserved_online_before');
            $table->unsignedInteger('reserved_seller_before');
            $table->string('status', 24)->default('waiting');
            $table->timestamps();

            $table->unique(
                ['source_order_id', 'product_id', 'reason'],
                'fulfillment_issues_source_product_reason_unique'
            );
            $table->index(['status', 'created_at']);
            $table->index(['warehouse_id', 'status']);
            $table->index(['product_id', 'status']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE fulfillment_issues
            ADD CONSTRAINT fulfillment_issues_reason_check
            CHECK (reason IN ('online_reservation_conflict', 'physical_stock_discrepancy'))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE fulfillment_issues
            ADD CONSTRAINT fulfillment_issues_status_check
            CHECK (status IN ('waiting', 'in_review', 'closed'))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE fulfillment_issues
            ADD CONSTRAINT fulfillment_issues_shortage_check
            CHECK (shortage_quantity > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_issues');

        DB::statement("UPDATE orders SET status = 'cancelled' WHERE status IN ('seller_review', 'manager_review', 'completed')");
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_status_check
            CHECK (status IN ('cart', 'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'))
        SQL);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_user_client_order_unique');
            $table->dropIndex('orders_seller_owner_index');
            $table->dropIndex('orders_seller_device_index');
            $table->dropConstrainedForeignId('seller_device_id');
            $table->dropColumn([
                'client_order_id',
                'seller_revision',
                'last_payload_hash',
                'was_edited',
                'seller_occurred_at',
                'seller_synced_at',
                'seller_reviewed_at',
                'seller_escalated_at',
                'seller_completed_at',
                'seller_discount_usage_consumed_at',
            ]);
        });

        Schema::dropIfExists('staff_devices');
    }
};
