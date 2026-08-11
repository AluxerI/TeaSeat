<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_method_id')
                ->constrained('delivery_methods')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('time_from');
            $table->time('time_to');
            $table->unsignedSmallInteger('capacity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['delivery_method_id', 'weekday', 'time_from', 'time_to'],
                'delivery_time_slots_schedule_unique'
            );
            $table->index(
                ['delivery_method_id', 'weekday', 'is_active'],
                'delivery_time_slots_lookup_index'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE delivery_time_slots
            ADD CONSTRAINT delivery_time_slots_weekday_check
            CHECK (weekday BETWEEN 1 AND 7)
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE delivery_time_slots
            ADD CONSTRAINT delivery_time_slots_time_check
            CHECK (time_from < time_to)
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE delivery_time_slots
            ADD CONSTRAINT delivery_time_slots_capacity_check
            CHECK (capacity > 0)
        SQL);

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_time_slot_id')
                ->nullable()
                ->after('delivery_method_id')
                ->constrained('delivery_time_slots')
                ->restrictOnDelete();
            $table->date('scheduled_delivery_date')
                ->nullable()
                ->after('delivery_time_slot_id');
            $table->time('delivery_time_from')
                ->nullable()
                ->after('scheduled_delivery_date');
            $table->time('delivery_time_to')
                ->nullable()
                ->after('delivery_time_from');

            $table->index(
                ['delivery_time_slot_id', 'scheduled_delivery_date', 'status'],
                'orders_delivery_slot_capacity_index'
            );
            $table->index(
                ['scheduled_delivery_date', 'status'],
                'orders_scheduled_delivery_index'
            );
        });

        DB::statement(<<<'SQL'
            ALTER TABLE orders
            ADD CONSTRAINT orders_delivery_schedule_snapshot_check
            CHECK (
                (delivery_time_slot_id IS NULL
                    AND scheduled_delivery_date IS NULL
                    AND delivery_time_from IS NULL
                    AND delivery_time_to IS NULL)
                OR
                (delivery_time_slot_id IS NOT NULL
                    AND scheduled_delivery_date IS NOT NULL
                    AND delivery_time_from IS NOT NULL
                    AND delivery_time_to IS NOT NULL
                    AND delivery_time_from < delivery_time_to)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE orders
            DROP CONSTRAINT IF EXISTS orders_delivery_schedule_snapshot_check
        SQL);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_delivery_slot_capacity_index');
            $table->dropIndex('orders_scheduled_delivery_index');
            $table->dropConstrainedForeignId('delivery_time_slot_id');
            $table->dropColumn([
                'scheduled_delivery_date',
                'delivery_time_from',
                'delivery_time_to',
            ]);
        });

        Schema::dropIfExists('delivery_time_slots');
    }
};
