<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $activeSubKegiatanIndex = 'renja_items_active_sub_kegiatan_unique';

    public function up(): void
    {
        $this->ensureActiveRenjaItemsAreUnique();

        $this->changeTargetColumnToText('renja_opd_items');
        $this->changeTargetColumnToText('rkpd_items');
        $this->createActiveSubKegiatanIndex();
    }

    public function down(): void
    {
        $this->ensureTargetColumnsCanBeNarrowed();
        $this->dropActiveSubKegiatanIndex();

        $this->changeTargetColumnToString('renja_opd_items');
        $this->changeTargetColumnToString('rkpd_items');
    }

    private function ensureActiveRenjaItemsAreUnique(): void
    {
        if (! Schema::hasTable('renja_opd_items')) {
            return;
        }

        $duplicates = DB::table('renja_opd_items')
            ->select('renja_opd_id', 'sub_kegiatan_pemerintahan_id', DB::raw('COUNT(*) AS duplicate_count'))
            ->whereNull('deleted_at')
            ->whereNotNull('sub_kegiatan_pemerintahan_id')
            ->groupBy('renja_opd_id', 'sub_kegiatan_pemerintahan_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('renja_opd_id')
            ->limit(10)
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $details = $duplicates
            ->map(fn ($row) => "RENJA {$row->renja_opd_id} / sub kegiatan {$row->sub_kegiatan_pemerintahan_id} ({$row->duplicate_count} baris)")
            ->implode('; ');

        throw new RuntimeException(
            'Migrasi dihentikan dengan aman karena ditemukan sub kegiatan RENJA aktif yang ganda. '.
            'Tidak ada data ganda yang dihapus otomatis. Rekonsiliasi baris berikut terlebih dahulu, lalu jalankan migrasi kembali: '.$details
        );
    }

    private function createActiveSubKegiatanIndex(): void
    {
        if (! Schema::hasTable('renja_opd_items')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement("DROP INDEX IF EXISTS {$this->activeSubKegiatanIndex}");
            DB::statement(
                "CREATE UNIQUE INDEX {$this->activeSubKegiatanIndex} ".
                'ON renja_opd_items (renja_opd_id, sub_kegiatan_pemerintahan_id) '.
                'WHERE deleted_at IS NULL AND sub_kegiatan_pemerintahan_id IS NOT NULL'
            );

            return;
        }

        throw new RuntimeException('Unique index sub kegiatan RENJA aktif hanya didukung pada PostgreSQL dan SQLite.');
    }

    private function dropActiveSubKegiatanIndex(): void
    {
        if (Schema::hasTable('renja_opd_items')) {
            DB::statement("DROP INDEX IF EXISTS {$this->activeSubKegiatanIndex}");
        }
    }

    private function changeTargetColumnToText(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'target_akhir_renstra')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN target_akhir_renstra TYPE TEXT USING target_akhir_renstra::text");

            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->text('target_akhir_renstra')->nullable()->change();
        });
    }

    private function ensureTargetColumnsCanBeNarrowed(): void
    {
        foreach (['renja_opd_items', 'rkpd_items'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'target_akhir_renstra')) {
                continue;
            }

            $hasLongValue = DB::table($tableName)
                ->whereNotNull('target_akhir_renstra')
                ->whereRaw('LENGTH(target_akhir_renstra) > 255')
                ->exists();

            if ($hasLongValue) {
                throw new RuntimeException(
                    "Rollback dihentikan karena {$tableName}.target_akhir_renstra memiliki data lebih dari 255 karakter. Tidak ada data yang dipotong."
                );
            }
        }
    }

    private function changeTargetColumnToString(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'target_akhir_renstra')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE {$tableName} ALTER COLUMN target_akhir_renstra TYPE VARCHAR(255) USING target_akhir_renstra::varchar(255)"
            );

            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('target_akhir_renstra')->nullable()->change();
        });
    }
};
