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
        Schema::create('delivery_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Курьер", "Самовывоз", "Почта России"
            $table->string('description')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->integer('estimated_days_min')->nullable(); // Мин дней доставки
            $table->integer('estimated_days_max')->nullable(); // Макс дней доставки
            $table->boolean('is_active')->default(true);
            $table->json('available_cities')->nullable(); // Города где доступен метод
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_methods');
    }
};
