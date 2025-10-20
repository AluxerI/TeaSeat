<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->constrained()->onDelete('cascade');

                // Цены на момент заказа (для истории)
                $table->integer('quantity');
                $table->decimal('unit_price', 10, 2); // Базовая цена
                $table->decimal('promotion_discount_percent', 5, 2)->default(0); // Скидка акции в %
                $table->decimal('personal_discount_percent', 5, 2)->default(0); // Персональная скидка в %
                $table->decimal('final_unit_price', 10, 2); // Итоговая цена за единицу
                $table->decimal('total_price', 10, 2); // Итоговая цена (quantity * final_unit_price)

                $table->timestamps();

                $table->index(['order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};
