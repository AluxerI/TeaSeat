<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->text('message')->nullable();
            $table->string('status', 16)->default('waiting');
            $table->text('manager_comment')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['manager_id', 'status']);
            $table->index(['order_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE order_requests
            ADD CONSTRAINT order_requests_type_check
            CHECK (type IN ('change_delivery', 'cancel_order', 'order_problem', 'other'))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE order_requests
            ADD CONSTRAINT order_requests_status_check
            CHECK (status IN ('waiting', 'in_review', 'resolved', 'rejected', 'withdrawn'))
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX order_requests_one_open_type_unique
            ON order_requests (order_id, type)
            WHERE status IN ('waiting', 'in_review')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_requests');
    }
};
