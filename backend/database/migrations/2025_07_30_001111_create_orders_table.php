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
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        
        // Статусы заказа
        $table->enum('status', [
            'cart', 
            'pending', 
            'confirmed',
            'processing', 
            'shipped', 
            'delivered', 
            'cancelled'
        ])->default('cart');
        
        // Суммы (сохраняем расчеты из вашего сервиса)
        $table->decimal('products_total', 10, 2)->default(0); // Сумма товаров без скидок
        $table->decimal('promotion_discount', 10, 2)->default(0); // Скидка от акций на товары
        $table->decimal('personal_discount', 10, 2)->default(0); // Скидка от персональных скидок
        $table->decimal('cart_discount', 10, 2)->default(0); // Скидка от промокодов на заказ
        $table->decimal('shipping_cost', 10, 2)->default(0);
        $table->decimal('final_total', 10, 2)->default(0); // Итоговая сумма
        
        // Адреса
        $table->foreignId('shipping_address_id')->nullable()->constrained('address_client');
        $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
        
        // Промокоды и скидки
        $table->foreignId('promotion_id')->nullable()->constrained()->onDelete('set null'); // Промокод на заказ
        $table->string('applied_promotion_code')->nullable(); // Примененный промокод
        
        // Информация о доставке
        $table->foreignId('delivery_method_id')->nullable()->constrained('delivery_methods');
        $table->enum('payment_method', ['cash', 'card', 'online'])->nullable();
        $table->string('tracking_number')->nullable();
        $table->text('customer_notes')->nullable();
        $table->text('internal_notes')->nullable();

        //Заказ от склада на склад
         $table->foreignId('parent_order_id')
            ->nullable()
            ->constrained('orders')
            ->onDelete('cascade');

        // Для поставщиков    
        $table->foreignId('supplier_order_id')->nullable()->constrained('supplier_orders');
        $table->boolean('is_supplier_order')->default(false);
        
        // Временные метки
        $table->timestamp('confirmed_at')->nullable();
        $table->timestamp('paid_at')->nullable();
        $table->timestamp('shipped_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('cancelled_at')->nullable();
        
        // Мягкое удаление
        $table->softDeletes();
        $table->timestamps();
        
        // Индексы
        $table->index(['user_id', 'status']);
        $table->index('status');
        $table->index('warehouse_id');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
