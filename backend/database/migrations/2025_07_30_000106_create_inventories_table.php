<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->integer('quantity')->default(0);
            $table->decimal('weight_quantity', 10, 2)->nullable();
            $table->date('last_restock_date')->nullable();

            
            // 👈 Добавляем уникальность для пары товар-склад
            $table->unique(['product_id', 'warehouse_id'], 'inventories_product_warehouse_unique');
            
            $table->timestamps();
            
            // Индексы для оптимизации
            $table->index('quantity');
            $table->index('last_restock_date');
            $table->index(['product_id', 'quantity']);
            $table->index(['warehouse_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};