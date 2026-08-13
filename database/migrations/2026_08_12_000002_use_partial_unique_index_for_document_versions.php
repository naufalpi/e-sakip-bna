<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rpjmd', 'renstra_opd'] as $table) {
            if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
                $this->dropIndex($table, $table.'_root_version_id_nomor_versi_unique');

                DB::statement(sprintf(
                    'CREATE UNIQUE INDEX %s ON %s (root_version_id, nomor_versi) WHERE deleted_at IS NULL',
                    $table.'_root_version_id_nomor_versi_live_unique',
                    $table,
                ));
            } else {
                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->dropUnique($table.'_root_version_id_nomor_versi_unique');
                });

                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->unique(['root_version_id', 'nomor_versi'], $table.'_root_version_id_nomor_versi_live_unique');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['rpjmd', 'renstra_opd'] as $table) {
            if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
                $this->dropIndex($table, $table.'_root_version_id_nomor_versi_live_unique');
            } else {
                Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                    $blueprint->dropUnique($table.'_root_version_id_nomor_versi_live_unique');
                });
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->unique(['root_version_id', 'nomor_versi'], $table.'_root_version_id_nomor_versi_unique');
            });
        }
    }

    private function dropIndex(string $table, string $index): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $index));
        }

        DB::statement(sprintf('DROP INDEX IF EXISTS %s', $index));
    }
};
