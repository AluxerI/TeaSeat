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
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
