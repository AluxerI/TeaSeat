<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            
            // Основная информация
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('value', 5, 2); // Процент скидки (0-100)
            
            // Тип скидки (универсальный)
            $table->enum('type', [
                'personal',      // Персональная скидка пользователя
                'first_order',   // Скидка на первый заказ
                'loyalty',       // Скидка лояльности
                'referral',      // Реферальная скидка
                'promotion',     // Обычная акция (замена promotions)
                'cart',          // Скидка на корзину
                'shipping'       // Скидка на доставку
            ])->default('promotion');
            
            // Временные ограничения
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Промокод (опционально)
            $table->string('code')->unique()->nullable();
            
            // Условия применения
            $table->boolean('is_global')->default(false); // На все товары
            $table->decimal('min_order_amount', 10, 2)->nullable(); // Мин. сумма заказа
            
            // Лимиты использования
            $table->integer('usage_limit')->nullable(); // Общий лимит
            $table->integer('used_count')->default(0);  // Сколько раз использована
            $table->integer('usage_per_user')->nullable(); // Лимит на пользователя
            
            // Кто создал (для админки)
            $table->foreignId('created_by')->nullable()->constrained('users');
            
            $table->softDeletes();
            $table->timestamps();
            
            // Индексы для быстрого поиска
            $table->index(['is_active', 'start_date', 'end_date']);
            $table->index('code');
            $table->index('type');
        });
        
        // Полиморфные связи (товары и категории) - заменяет все старые таблицы
        Schema::create('discountables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->morphs('discountable'); // product, category, subcategory, sub_subcategory
            $table->timestamps();
            // Уникальность: одна скидка не может быть привязана к одной и той же сущности дважды
            $table->unique(['discount_id', 'discountable_type', 'discountable_id'], 'discountables_unique');
            
            // Индексы для быстрого поиска
            $table->index('discountable_type');
            $table->index('discountable_id');
        });
        
        // Связь с пользователями (для персональных скидок)
        Schema::create('discount_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->integer('used_count')->default(0);
            $table->boolean('is_used')->default(false);
            $table->timestamp('activated_at')->useCurrent();
            $table->timestamps();
            
            $table->primary(['user_id', 'discount_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_user');
        Schema::dropIfExists('discountables');
        Schema::dropIfExists('discounts');
    }
};