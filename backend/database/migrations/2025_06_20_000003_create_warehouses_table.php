<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('location')->nullable();
            $table->string('type', 16)->default('warehouse');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online_fulfillment_enabled')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('type');
            $table->index(['city', 'is_active', 'is_online_fulfillment_enabled'], 'warehouses_online_city_index');
        });

        DB::statement("ALTER TABLE warehouses ADD CONSTRAINT warehouses_type_check CHECK (type IN ('warehouse', 'store'))");

        Schema::create('user_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'warehouse_id']);
            $table->index(['user_id', 'is_active']);
            $table->index(['warehouse_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_warehouse');
        Schema::dropIfExists('warehouses');
    }
};
