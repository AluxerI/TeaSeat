<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constructor_packaging_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32)->default('other');
            $table->string('image_path');
            $table->string('disk', 32)->default('public');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });

        Schema::table('product_sizes', function (Blueprint $table) {
            $table->foreignId('packaging_template_id')
                ->nullable()
                ->after('gift_size_profile_id')
                ->constrained('constructor_packaging_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_sizes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('packaging_template_id');
        });

        Schema::dropIfExists('constructor_packaging_templates');
    }
};
