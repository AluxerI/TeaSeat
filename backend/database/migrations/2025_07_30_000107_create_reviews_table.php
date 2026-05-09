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
        Schema::create('reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating')->unsigned()->between(1, 5); // Оценка 1-5
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->primary(['product_id', 'user_id']);
            $table->index('rating'); // для фильтрации по оценке
            $table->index('created_at'); // для сортировки по дате
            $table->index(['product_id', 'rating']); // для отзывов на товар по оценке
            $table->index(['product_id', 'created_at']); // для отзывов на товар по дате
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
