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
            $table->string('username')->nullable()->unique()->after('opd_id');
            $table->string('phone', 30)->nullable()->after('email')->index();
            $table->string('jabatan')->nullable()->after('phone')->index();
            $table->timestamp('last_login_at')->nullable()->after('status')->index();

            $table->index(['status', 'last_login_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['jabatan']);
            $table->dropIndex(['last_login_at']);
            $table->dropIndex(['status', 'last_login_at']);
            $table->dropColumn(['username', 'phone', 'jabatan', 'last_login_at']);
        });
    }
};
