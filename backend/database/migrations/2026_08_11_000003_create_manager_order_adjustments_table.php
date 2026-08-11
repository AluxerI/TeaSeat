<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fulfillment_issue_id')
                ->nullable()
                ->constrained('fulfillment_issues')
                ->nullOnDelete();
            $table->string('action', 32);
            $table->unsignedBigInteger('order_product_id')->nullable();
            $table->unsignedBigInteger('order_gift_id')->nullable();
            $table->text('reason');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
            $table->index(['manager_id', 'created_at']);
            $table->index(['fulfillment_issue_id', 'created_at'], 'manager_adjustments_issue_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE manager_order_adjustments
            ADD CONSTRAINT manager_order_adjustments_action_check
            CHECK (action IN (
                'quantity_changed',
                'product_added',
                'product_replaced',
                'product_removed',
                'gift_replaced',
                'gift_removed'
            ))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_order_adjustments');
    }
};
