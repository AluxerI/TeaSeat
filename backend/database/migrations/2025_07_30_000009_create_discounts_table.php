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
            
            // Параметры скидки
            $table->string('name');
            $table->decimal('value', 5, 2);
            $table->boolean('is_global')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            
            // Тип скидки
            $table->enum('type', [
                'personal', 
                'first_order', 
                'loyalty', 
                'referral',
                'category',
                'subcategory',
                'sub_subcategory'
            ])->default('personal');
            
            // Дополнительные параметры
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->integer('usage_limit')->default(1);
            
            $table->timestamps(); // создаст created_at и updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};