<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kegiatan_pemerintahan', 'periode_tahun_id')) {
            Schema::table('kegiatan_pemerintahan', function (Blueprint $table) {
                $table->foreignId('periode_tahun_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('periode_tahun')
                    ->restrictOnDelete();
            });

            $this->backfillPeriodeTahun('kegiatan_pemerintahan');
        }

        if (! Schema::hasColumn('sub_kegiatan_pemerintahan', 'periode_tahun_id')) {
            Schema::table('sub_kegiatan_pemerintahan', function (Blueprint $table) {
                $table->foreignId('periode_tahun_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('periode_tahun')
                    ->restrictOnDelete();
            });

            $this->backfillPeriodeTahun('sub_kegiatan_pemerintahan');
        }

        if (! $this->hasIndex('kegiatan_pemerintahan', 'kegiatan_pemerintahan_periode_program_kode_unique')) {
            Schema::table('kegiatan_pemerintahan', function (Blueprint $table) {
                if ($this->hasIndex('kegiatan_pemerintahan', 'kegiatan_pemerintahan_program_pemerintahan_id_kode_unique')) {
                    $table->dropUnique('kegiatan_pemerintahan_program_pemerintahan_id_kode_unique');
                }

                $table->unique(['periode_tahun_id', 'program_pemerintahan_id', 'kode'], 'kegiatan_pemerintahan_periode_program_kode_unique');
                $table->index(['periode_tahun_id', 'status'], 'kegiatan_pemerintahan_periode_status_index');
            });
        }

        if (! $this->hasIndex('sub_kegiatan_pemerintahan', 'sub_kegiatan_pemerintahan_periode_kegiatan_kode_unique')) {
            Schema::table('sub_kegiatan_pemerintahan', function (Blueprint $table) {
                if ($this->hasIndex('sub_kegiatan_pemerintahan', 'sub_kegiatan_pemerintahan_kegiatan_pemerintahan_id_kode_unique')) {
                    $table->dropUnique('sub_kegiatan_pemerintahan_kegiatan_pemerintahan_id_kode_unique');
                }

                $table->unique(['periode_tahun_id', 'kegiatan_pemerintahan_id', 'kode'], 'sub_kegiatan_pemerintahan_periode_kegiatan_kode_unique');
                $table->index(['periode_tahun_id', 'status'], 'sub_kegiatan_pemerintahan_periode_status_index');
            });
        }

        if (Schema::hasColumn('kegiatan_pemerintahan', 'periode_tahun_id')) {
            $this->setNotNull('kegiatan_pemerintahan', 'periode_tahun_id');
        }

        if (Schema::hasColumn('sub_kegiatan_pemerintahan', 'periode_tahun_id')) {
            $this->setNotNull('sub_kegiatan_pemerintahan', 'periode_tahun_id');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kegiatan_pemerintahan', 'periode_tahun_id')) {
            return;
        }

        Schema::table('sub_kegiatan_pemerintahan', function (Blueprint $table) {
            $table->dropUnique('sub_kegiatan_pemerintahan_periode_kegiatan_kode_unique');
            $table->dropIndex('sub_kegiatan_pemerintahan_periode_status_index');
            $table->unique(['kegiatan_pemerintahan_id', 'kode']);
        });

        Schema::table('kegiatan_pemerintahan', function (Blueprint $table) {
            $table->dropUnique('kegiatan_pemerintahan_periode_program_kode_unique');
            $table->dropIndex('kegiatan_pemerintahan_periode_status_index');
            $table->unique(['program_pemerintahan_id', 'kode']);
        });
        Schema::table('sub_kegiatan_pemerintahan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('periode_tahun_id');
        });

        Schema::table('kegiatan_pemerintahan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('periode_tahun_id');
        });

    }

    private function setNotNull(string $table, string $column): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} BIGINT UNSIGNED NOT NULL");
        }
    }

    private function backfillPeriodeTahun(string $table): void
    {
        if (! DB::table($table)->exists()) {
            return;
        }

        $periodeId = DB::table('periode_tahun')
            ->where('status', 'active')
            ->orderByDesc('tahun')
            ->value('id')
            ?? DB::table('periode_tahun')->orderByDesc('tahun')->value('id');

        if (! $periodeId) {
            throw new RuntimeException("Periode tahun harus tersedia sebelum menambah periode pada {$table}.");
        }

        DB::table($table)->update(['periode_tahun_id' => $periodeId]);
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $item) => ($item['name'] ?? null) === $index);
    }
};
