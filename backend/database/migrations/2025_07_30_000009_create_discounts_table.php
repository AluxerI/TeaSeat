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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Например, "Летняя распродажа"
            $table->string('code')->unique(); // Промокод (например, "SUMMER20")
            $table->enum('type', ['percentage', 'fixed']); // Тип скидки (% или фикс. сумма)
            $table->decimal('value', 10, 2); // Размер скидки (20% или 100 руб.)
            $table->boolean('is_global')->default(false); 
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
