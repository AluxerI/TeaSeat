<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_size_profiles', function (Blueprint $table) {
            $table->boolean('advanced_constructor_enabled')
                ->default(true)
                ->after('default_markup_amount');
        });

        DB::table('gift_size_profiles')
            ->where('kind', '!=', 'box')
            ->update(['advanced_constructor_enabled' => false]);

        DB::statement(<<<'SQL'
            ALTER TABLE gift_size_profiles
            ADD CONSTRAINT gift_size_profiles_advanced_constructor_check
            CHECK (
                advanced_constructor_enabled = FALSE
                OR kind = 'box'
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE gift_size_profiles
            DROP CONSTRAINT gift_size_profiles_advanced_constructor_check
        SQL);

        Schema::table('gift_size_profiles', function (Blueprint $table) {
            $table->dropColumn('advanced_constructor_enabled');
        });
    }
};
