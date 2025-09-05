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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('ingredients')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();   // а где категория?
            $table->decimal('price', 10, 2);
            $table->integer('weight_grams')->nullable(); // Вес в граммах
            $table->integer('sold_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
