<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 80)->default('workflow')->index();
                $table->string('title');
                $table->text('message')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();

                $table->index(['user_id', 'read_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        if (Schema::hasTable('tindak_lanjut_rekomendasi') && ! Schema::hasColumn('tindak_lanjut_rekomendasi', 'status')) {
            Schema::table('tindak_lanjut_rekomendasi', function (Blueprint $table) {
                $table->string('status', 30)->default('draft')->after('status_tindak_lanjut')->index();
            });
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN old_values TYPE jsonb USING old_values::jsonb');
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN new_values TYPE jsonb USING new_values::jsonb');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN old_values TYPE json USING old_values::json');
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN new_values TYPE json USING new_values::json');
        }

        if (Schema::hasTable('tindak_lanjut_rekomendasi') && Schema::hasColumn('tindak_lanjut_rekomendasi', 'status')) {
            Schema::table('tindak_lanjut_rekomendasi', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        Schema::dropIfExists('notifications');
    }
};
