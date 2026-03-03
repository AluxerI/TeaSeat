<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
   public function up(): void
    {
        Schema::create('category_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->string('disk', 50)->default('public');
            $table->boolean('is_main')->default(false);
            $table->boolean('is_background')->default(false);
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index(['category_id', 'is_main']);
            $table->index(['category_id', 'is_background']);
            
            // Убеждаемся что у категории не может быть два главных или два фоновых
            $table->unique(['category_id', 'is_main'], 'category_id_main_unique')
                  ->where('is_main', true);
            $table->unique(['category_id', 'is_background'], 'category_id_background_unique')
                  ->where('is_background', true);
        });
    }

    public function down(): void
    {
        // Удаляем файлы перед удалением таблицы
        if (app()->environment('local', 'development', 'testing')) {
            $images = DB::table('category_images')->get();
            foreach ($images as $image) {
                Storage::disk($image->disk)->delete($image->path);
            }
            Storage::disk('public')->deleteDirectory('category-images');
        }
        
        Schema::dropIfExists('category_images');
    }
};
