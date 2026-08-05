<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $uniqueIndex = 'opd_sub_kegiatan_active_master_unique';

    private string $lookupIndex = 'opd_sub_kegiatan_master_lookup_idx';

    public function up(): void
    {
        if (
            ! Schema::hasTable('opd_sub_kegiatan')
            || ! Schema::hasColumn('opd_sub_kegiatan', 'sub_kegiatan_pemerintahan_id')
            || ! Schema::hasColumn('opd_sub_kegiatan', 'deleted_at')
        ) {
            return;
        }

        if ($this->hasActiveDuplicates()) {
            Schema::table('opd_sub_kegiatan', function (Blueprint $table) {
                $table->index(['opd_kegiatan_id', 'sub_kegiatan_pemerintahan_id'], $this->lookupIndex);
            });

            return;
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX {$this->uniqueIndex} ON opd_sub_kegiatan (opd_kegiatan_id, sub_kegiatan_pemerintahan_id) WHERE deleted_at IS NULL AND sub_kegiatan_pemerintahan_id IS NOT NULL"
            );

            return;
        }

        Schema::table('opd_sub_kegiatan', function (Blueprint $table) {
            $table->unique(['opd_kegiatan_id', 'sub_kegiatan_pemerintahan_id'], $this->uniqueIndex);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('opd_sub_kegiatan')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement("DROP INDEX IF EXISTS {$this->uniqueIndex}");
            DB::statement("DROP INDEX IF EXISTS {$this->lookupIndex}");

            return;
        }

        Schema::table('opd_sub_kegiatan', function (Blueprint $table) {
            try {
                $table->dropUnique($this->uniqueIndex);
            } catch (Throwable) {
                //
            }

            try {
                $table->dropIndex($this->lookupIndex);
            } catch (Throwable) {
                //
            }
        });
    }

    private function hasActiveDuplicates(): bool
    {
        return DB::table('opd_sub_kegiatan')
            ->select('opd_kegiatan_id', 'sub_kegiatan_pemerintahan_id')
            ->whereNull('deleted_at')
            ->whereNotNull('sub_kegiatan_pemerintahan_id')
            ->groupBy('opd_kegiatan_id', 'sub_kegiatan_pemerintahan_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }
};
