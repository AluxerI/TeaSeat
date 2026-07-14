<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->integer('physical_delta')->default(0);
            $table->integer('reserved_online_delta')->default(0);
            $table->integer('reserved_seller_delta')->default(0);
            $table->integer('physical_before');
            $table->integer('physical_after');
            $table->integer('reserved_online_before');
            $table->integer('reserved_online_after');
            $table->integer('reserved_seller_before');
            $table->integer('reserved_seller_after');
            $table->text('reason')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'product_id', 'created_at'], 'inventory_movements_stock_index');
            $table->index(['order_id', 'type']);
            $table->index(['type', 'created_at']);
        });

        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type IN ('opening_balance', 'online_reserve', 'online_release', 'online_sale', 'seller_reserve', 'seller_release', 'seller_sale', 'restock', 'adjustment', 'transfer_in', 'transfer_out'))");
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_physical_balances_check CHECK (physical_before >= 0 AND physical_after >= 0)');
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_online_balances_check CHECK (reserved_online_before >= 0 AND reserved_online_after >= 0)');
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_seller_balances_check CHECK (reserved_seller_before >= 0 AND reserved_seller_after >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
