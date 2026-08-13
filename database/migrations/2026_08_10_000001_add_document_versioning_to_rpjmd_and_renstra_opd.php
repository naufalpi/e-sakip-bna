<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rpjmd', 'renstra_opd'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->string('jenis_versi', 20)->default('murni')->index();
                $table->unsignedSmallInteger('nomor_versi')->default(1);
                $table->foreignId('parent_version_id')->nullable()->constrained($tableName)->nullOnDelete();
                $table->boolean('is_active_version')->default(true)->index();
                $table->text('alasan_perubahan')->nullable();
                $table->string('dasar_perubahan')->nullable();
                $table->date('tanggal_berlaku')->nullable();
                $table->foreignId('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('disahkan_pada')->nullable();
                $table->index(['parent_version_id', 'is_active_version']);
            });

            DB::table($tableName)->update([
                'jenis_versi' => 'murni',
                'nomor_versi' => 1,
                'is_active_version' => true,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['rpjmd', 'renstra_opd'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['parent_version_id', 'is_active_version']);
                $table->dropIndex(['jenis_versi']);
                $table->dropForeign(['parent_version_id']);
                $table->dropForeign(['disahkan_oleh']);
                $table->dropColumn([
                    'jenis_versi',
                    'nomor_versi',
                    'parent_version_id',
                    'is_active_version',
                    'alasan_perubahan',
                    'dasar_perubahan',
                    'tanggal_berlaku',
                    'disahkan_oleh',
                    'disahkan_pada',
                ]);
            });
        }
    }
};
