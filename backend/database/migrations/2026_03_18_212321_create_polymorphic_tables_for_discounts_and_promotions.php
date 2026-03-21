<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Для скидок (если хотите отдельно от акций)
        Schema::create('discountables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->morphs('discountable'); // создаст discountable_type и discountable_id
            $table->timestamps();

            // Уникальность: одна скидка не может быть привязана к одной и той же сущности дважды
            $table->unique(['discount_id', 'discountable_type', 'discountable_id'], 'discountables_unique');
        });

        // Для акций (аналогично)
        Schema::create('promotionables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->morphs('promotionable');
            $table->timestamps();
            $table->unique(['promotion_id', 'promotionable_type', 'promotionable_id'], 'promotionables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discountables');
        Schema::dropIfExists('promotionables');
    }
};