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
        Schema::table('users', function (Blueprint $table) {
            // $table->rememberToken();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email')->unique();
            $table->string('nik_name');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password');
            $table->dropColumn('email_verified_at');
            $table->dropColumn('email');
            $table->dropColumn('nik_name');
        });
    }
};
