<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->decimal('value', 10, 2)->change();
            $table->string('value_type', 16)->default('percent')->after('value');
            $table->dropUnique('discounts_code_unique');
        });
        DB::statement("UPDATE discounts SET code = NULL WHERE code IS NOT NULL AND BTRIM(code) = ''");
        DB::statement("ALTER TABLE discounts ADD CONSTRAINT discounts_value_type_check CHECK (value_type IN ('percent', 'fixed'))");
        DB::statement("ALTER TABLE discounts ADD CONSTRAINT discounts_value_check CHECK (value >= 0 AND (value_type <> 'percent' OR value <= 100))");
        DB::statement("ALTER TABLE discounts ADD CONSTRAINT discounts_code_not_blank_check CHECK (code IS NULL OR BTRIM(code) <> '')");
        DB::statement('CREATE UNIQUE INDEX discounts_code_lower_unique ON discounts (LOWER(code)) WHERE code IS NOT NULL');

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_discount', 10, 2)->default(0)->after('shipping_cost');
            $table->json('pricing_snapshot')->nullable()->after('final_total');
            $table->timestamp('discount_usage_released_at')->nullable()->after('cancelled_at');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->foreignId('promotion_discount_id')
                ->nullable()
                ->after('unit_price')
                ->constrained('discounts')
                ->nullOnDelete();
            $table->foreignId('selected_discount_id')
                ->nullable()
                ->after('promotion_discount_id')
                ->constrained('discounts')
                ->nullOnDelete();
            $table->decimal('promotion_discount_amount', 10, 2)
                ->default(0)
                ->after('personal_discount_percent');
            $table->decimal('selected_discount_amount', 10, 2)
                ->default(0)
                ->after('promotion_discount_amount');
            $table->json('pricing_snapshot')->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_discount_id');
            $table->dropConstrainedForeignId('selected_discount_id');
            $table->dropColumn([
                'promotion_discount_amount',
                'selected_discount_amount',
                'pricing_snapshot',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_discount',
                'pricing_snapshot',
                'discount_usage_released_at',
            ]);
        });

        DB::statement('ALTER TABLE discounts DROP CONSTRAINT IF EXISTS discounts_value_check');
        DB::statement('ALTER TABLE discounts DROP CONSTRAINT IF EXISTS discounts_value_type_check');
        DB::statement('ALTER TABLE discounts DROP CONSTRAINT IF EXISTS discounts_code_not_blank_check');
        DB::statement('DROP INDEX IF EXISTS discounts_code_lower_unique');
        Schema::table('discounts', function (Blueprint $table) {
            $table->unique('code');
            $table->dropColumn('value_type');
        });
    }
};
