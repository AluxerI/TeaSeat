<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
        $table->id();
        
        // Основная информация (совместимо с вашим сервисом)
        $table->string('name');
        $table->string('description')->nullable();
        
        // Для товарных акций (как в вашем сервисе)
        $table->decimal('discount_percent', 5, 2)->default(0); // Процент скидки
        $table->boolean('is_active')->default(true);
        $table->timestamp('start_date')->nullable();
        $table->timestamp('end_date')->nullable();
        
        // Для промокодов (новый функционал)
        $table->string('code')->unique()->nullable(); // Промокод
        $table->enum('type', ['product', 'cart', 'shipping'])->default('product'); // Тип акции
        $table->decimal('min_order_amount', 10, 2)->nullable(); // Мин. сумма заказа
        $table->integer('usage_limit')->nullable(); // Лимит использований
        $table->integer('used_count')->default(0);
        
        // Мягкое удаление
        $table->softDeletes();
        $table->timestamps();
        
        // Индексы
        $table->index(['is_active', 'start_date', 'end_date']);
        $table->index('code');
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
