<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcategory_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->timestamps();
            
            // Добавляем индекс
            $table->index('name');
            $table->index('created_at'); // для сортировки
            $table->index(['subcategory_id', 'name']); // для фильтрации по подкатегории + поиск
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_subcategories');
    }
};