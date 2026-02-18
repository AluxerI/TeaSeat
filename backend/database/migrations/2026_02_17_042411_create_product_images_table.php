<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;      
use Illuminate\Support\Facades\Storage; 

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('path');               // путь к файлу (относительно диска)
            $table->string('disk', 50)->default('public'); // файловая система (public, s3 и т.д.)
            $table->integer('sort_order')->default(0);
            $table->boolean('is_main')->default(false); // флаг главного изображения
            $table->boolean('is_background')->default(false);
            $table->string('alt')->nullable();    // альтернативный текст
            $table->string('title')->nullable();  // заголовок при наведении
            $table->timestamps();

            // для быстрого поиска главного фото по товару
            $table->index(['product_id', 'is_main']);
            $table->index(['product_id', 'is_background']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (app()->environment('local', 'development', 'testing')) {
        // Получаем все записи из таблицы перед её удалением
        $images = DB::table('product_images')->get();
        
        foreach ($images as $image) {
            Storage::disk($image->disk)->delete($image->path);
        }
        
        // Удаляем папку products полностью
        Storage::disk('public')->deleteDirectory('products');
        
        // Удаляем папку placeholders если есть
        Storage::disk('public')->deleteDirectory('placeholders');
    }
            Schema::dropIfExists('product_images');
    }
};
