<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
                $table->string('stock_unit', 16)->default('piece');
                $table->integer('sale_step')->default(1);
                $table->integer('price_unit_quantity')->default(1);
                $table->decimal('unit_price', 10, 2); // Цена за price_unit_quantity
                $table->decimal('promotion_discount_percent', 5, 2)->default(0); // Скидка акции в %
                $table->decimal('personal_discount_percent', 5, 2)->default(0); // Персональная скидка в %
                $table->decimal('final_unit_price', 10, 2); // Итоговая цена за price_unit_quantity
                $table->decimal('total_price', 10, 2); // Итог по строке

                $table->timestamps();

                $table->index(['order_id', 'product_id']);
        });

        DB::statement("ALTER TABLE order_products ADD CONSTRAINT order_products_stock_unit_check CHECK (stock_unit IN ('piece', 'gram'))");
        DB::statement('ALTER TABLE order_products ADD CONSTRAINT order_products_sale_step_check CHECK (sale_step > 0)');
        DB::statement('ALTER TABLE order_products ADD CONSTRAINT order_products_price_unit_quantity_check CHECK (price_unit_quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};
