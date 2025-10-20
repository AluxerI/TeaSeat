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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Например, "Основной склад", "Склад №2"
            $table->string('city')->nullable();
            $table->string('location')->nullable(); // Адрес
            $table->boolean('is_active')->default(true); 
            $table->boolean('is_supplier')->default(false);
            $table->string('supplier_name')->nullable();
            $table->text('supplier_contact')->nullable();
            $table->json('order_schedule')->nullable(); // {'days': [8,18,28], 'type': 'monthly'}
            $table->integer('lead_time_days')->default(7);
            $table->integer('min_order_quantity')->default(1);
            $table->integer('consolidation_period')->default(3);            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
