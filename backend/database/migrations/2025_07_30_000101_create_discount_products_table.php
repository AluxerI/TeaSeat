<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица для связи с товарами
        Schema::create('discount_products', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_used')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            
            $table->primary(['discount_id', 'product_id']);
        });

        // Таблица для связи с категориями
        Schema::create('discount_categories', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->primary(['discount_id', 'category_id']);
        });

        // Таблица для связи с подкатегориями
        Schema::create('discount_subcategories', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subcategory_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->primary(['discount_id', 'subcategory_id']);
        });

        // Таблица для связи с под-подкатегориями
        Schema::create('discount_sub_subcategories', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_subcategory_id')->constrained('sub_subcategories')->cascadeOnDelete();
            $table->timestamps();
            
            $table->primary(['discount_id', 'sub_subcategory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_sub_subcategories');
        Schema::dropIfExists('discount_subcategories');
        Schema::dropIfExists('discount_categories');
        Schema::dropIfExists('discount_products');
    }
};