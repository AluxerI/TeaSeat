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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable()->after('name');
            $table->timestamps();
            // Поля для промо-карточки категории
            $table->string('promo_title')->nullable()->after('icon');
            $table->string('promo_subtitle')->nullable()->after('promo_title');
            $table->text('promo_description')->nullable()->after('promo_subtitle');
            $table->string('promo_button_text')->nullable()->after('promo_description');
            $table->string('promo_button_link')->nullable()->after('promo_button_text');
            $table->json('promo_settings')->nullable()->after('promo_button_link');
            
            // Индекс для поиска категорий с промо
            $table->index('promo_title', 'categories_promo_title_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
