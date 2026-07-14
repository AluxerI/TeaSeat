<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            // 👈 Добавляем ID как первичный ключ
            $table->id();
            
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            // Храним в базовой единице товара: штуках или целых граммах.
            $table->integer('quantity')->default(0);
            $table->integer('reserved_online_quantity')->default(0);
            $table->integer('reserved_seller_quantity')->default(0);
            $table->date('last_restock_date')->nullable();

            
            // 👈 Добавляем уникальность для пары товар-склад
            $table->unique(['product_id', 'warehouse_id'], 'inventories_product_warehouse_unique');
            
            $table->timestamps();
            
            // Индексы для оптимизации
            $table->index('quantity');
            $table->index('last_restock_date');
            $table->index(['product_id', 'quantity']);
            $table->index(['warehouse_id', 'quantity']);
            $table->index(
                ['warehouse_id', 'product_id', 'reserved_online_quantity', 'reserved_seller_quantity'],
                'inventories_availability_index'
            );
        });

        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_quantity_check CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_reserved_online_check CHECK (reserved_online_quantity >= 0)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_reserved_seller_check CHECK (reserved_seller_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
