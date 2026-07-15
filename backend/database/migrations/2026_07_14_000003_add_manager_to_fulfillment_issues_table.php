<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_issues', function (Blueprint $table) {
            $table->foreignId('manager_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->index(
                ['manager_id', 'status'],
                'fulfillment_issues_manager_status_index'
            );
        });

        // До этого обновления владелец дела не сохранялся. Такие тестовые
        // записи безопаснее вернуть в очередь, чем оставить занятыми никем.
        DB::statement(<<<'SQL'
            UPDATE fulfillment_issues
            SET status = 'waiting'
            WHERE status IN ('in_review', 'closed') AND manager_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE fulfillment_issues
            ADD CONSTRAINT fulfillment_issues_manager_status_check
            CHECK (
                (status = 'waiting' AND manager_id IS NULL)
                OR (status IN ('in_review', 'closed') AND manager_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE fulfillment_issues
            DROP CONSTRAINT IF EXISTS fulfillment_issues_manager_status_check
        SQL);

        Schema::table('fulfillment_issues', function (Blueprint $table) {
            $table->dropIndex('fulfillment_issues_manager_status_index');
            $table->dropConstrainedForeignId('manager_id');
        });
    }
};
