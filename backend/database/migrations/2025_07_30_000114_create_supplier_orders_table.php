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
        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->date('scheduled_date');
            $table->date('delivery_date');
            $table->enum('status', ['consolidating', 'ordered', 'delivered'])->default('consolidating');
            $table->integer('total_quantity')->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->integer('customer_orders_count')->default(0);
            $table->timestamps();
            
            $table->index(['supplier_id', 'scheduled_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_orders');
    }
};
