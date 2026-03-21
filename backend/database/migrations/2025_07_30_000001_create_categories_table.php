<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            
            // Поля для промо-карточки категории
            $table->string('promo_title')->nullable();
            $table->string('promo_subtitle')->nullable();
            $table->text('promo_description')->nullable();
            $table->string('promo_button_text')->nullable();
            $table->string('promo_button_link')->nullable();
            $table->json('promo_settings')->nullable();
            
            $table->timestamps();
            
            $table->index('name');
            $table->index('created_at'); // для сортировки
            $table->index(['name', 'created_at']); // составной для фильтров
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};