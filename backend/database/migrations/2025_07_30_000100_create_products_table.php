<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('ingredients')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->integer('weight_grams')->nullable();
            $table->integer('sold_count')->default(0);
            
            // ПОЛЯ ДЛЯ КЕШИРОВАНИЯ (добавляем)
            $table->boolean('is_available')->default(false)->index();
            $table->integer('total_quantity')->default(0);
            $table->json('cached_data')->nullable(); // Для дополнительных кешированных данных
            
            $table->timestamps();
            
            // Индексы для оптимизации
            $table->index('name');
            $table->index('price');
            $table->index('sold_count');
            $table->index('created_at');
            $table->index(['brand_id', 'price']);
            $table->index(['is_available', 'price']);
            $table->index('brand_id'); // отдельный индекс для связи
            $table->index('total_quantity'); // для фильтрации по количеству
            $table->index(['name', 'price']); // для поиска по имени + сортировка по цене
            $table->index(['brand_id', 'is_available']); // для фильтрации по бренду + наличие
            $table->index(['created_at', 'price']); // для сортировки по дате + цена
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};