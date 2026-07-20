<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_pemerintahan')) {
            return;
        }

        if (! Schema::hasColumn('program_pemerintahan', 'tahun_awal')) {
            Schema::table('program_pemerintahan', function (Blueprint $table) {
                $table->unsignedSmallInteger('tahun_awal')->nullable()->after('bidang_urusan_id');
            });
        }

        if (! Schema::hasColumn('program_pemerintahan', 'tahun_akhir')) {
            Schema::table('program_pemerintahan', function (Blueprint $table) {
                $table->unsignedSmallInteger('tahun_akhir')->nullable()->after('tahun_awal');
            });
        }

        $this->backfillRpjmdPeriod();
        $this->mergeDuplicatePrograms();

        if (Schema::hasColumn('program_pemerintahan', 'periode_tahun_id')) {
            Schema::table('program_pemerintahan', function (Blueprint $table) {
                if ($this->hasIndex('program_pemerintahan', 'program_pemerintahan_periode_bidang_kode_unique')) {
                    $table->dropUnique('program_pemerintahan_periode_bidang_kode_unique');
                }

                if ($this->hasIndex('program_pemerintahan', 'program_pemerintahan_periode_status_index')) {
                    $table->dropIndex('program_pemerintahan_periode_status_index');
                }

                $table->dropConstrainedForeignId('periode_tahun_id');
            });
        }

        $this->setNotNull('program_pemerintahan', 'tahun_awal');
        $this->setNotNull('program_pemerintahan', 'tahun_akhir');

        Schema::table('program_pemerintahan', function (Blueprint $table) {
            if (! $this->hasIndex('program_pemerintahan', 'program_pemerintahan_rpjmd_bidang_kode_unique')) {
                $table->unique(['tahun_awal', 'tahun_akhir', 'bidang_urusan_id', 'kode'], 'program_pemerintahan_rpjmd_bidang_kode_unique');
            }

            if (! $this->hasIndex('program_pemerintahan', 'program_pemerintahan_rpjmd_status_index')) {
                $table->index(['tahun_awal', 'tahun_akhir', 'status'], 'program_pemerintahan_rpjmd_status_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('program_pemerintahan') || Schema::hasColumn('program_pemerintahan', 'periode_tahun_id')) {
            return;
        }

        $periodeId = DB::table('periode_tahun')
            ->where('status', 'active')
            ->orderByDesc('tahun')
            ->value('id')
            ?? DB::table('periode_tahun')->orderByDesc('tahun')->value('id');

        if (! $periodeId) {
            throw new RuntimeException('Periode tahun harus tersedia sebelum rollback scope program RPJMD.');
        }

        Schema::table('program_pemerintahan', function (Blueprint $table) {
            if ($this->hasIndex('program_pemerintahan', 'program_pemerintahan_rpjmd_bidang_kode_unique')) {
                $table->dropUnique('program_pemerintahan_rpjmd_bidang_kode_unique');
            }

            if ($this->hasIndex('program_pemerintahan', 'program_pemerintahan_rpjmd_status_index')) {
                $table->dropIndex('program_pemerintahan_rpjmd_status_index');
            }

            $table->foreignId('periode_tahun_id')
                ->nullable()
                ->after('id')
                ->constrained('periode_tahun')
                ->restrictOnDelete();
        });

        $periodeByYear = DB::table('periode_tahun')->pluck('id', 'tahun');

        DB::table('program_pemerintahan')
            ->orderBy('id')
            ->select(['id', 'tahun_awal'])
            ->chunkById(200, function ($programs) use ($periodeByYear, $periodeId): void {
                foreach ($programs as $program) {
                    DB::table('program_pemerintahan')
                        ->where('id', $program->id)
                        ->update([
                            'periode_tahun_id' => $periodeByYear[$program->tahun_awal] ?? $periodeId,
                        ]);
                }
            });

        $this->setNotNull('program_pemerintahan', 'periode_tahun_id');

        Schema::table('program_pemerintahan', function (Blueprint $table) {
            $table->unique(['periode_tahun_id', 'bidang_urusan_id', 'kode'], 'program_pemerintahan_periode_bidang_kode_unique');
            $table->index(['periode_tahun_id', 'status'], 'program_pemerintahan_periode_status_index');
            $table->dropColumn(['tahun_awal', 'tahun_akhir']);
        });
    }

    private function backfillRpjmdPeriod(): void
    {
        $hasMissingRange = DB::table('program_pemerintahan')
            ->where(fn ($query) => $query->whereNull('tahun_awal')->orWhereNull('tahun_akhir'))
            ->exists();

        if (! $hasMissingRange) {
            return;
        }

        $defaultRange = $this->defaultRange();

        if (! Schema::hasColumn('program_pemerintahan', 'periode_tahun_id')) {
            DB::table('program_pemerintahan')
                ->where(fn ($query) => $query->whereNull('tahun_awal')->orWhereNull('tahun_akhir'))
                ->update([
                    'tahun_awal' => $defaultRange['tahun_awal'],
                    'tahun_akhir' => $defaultRange['tahun_akhir'],
                ]);

            return;
        }

        DB::table('program_pemerintahan')
            ->leftJoin('periode_tahun', 'periode_tahun.id', '=', 'program_pemerintahan.periode_tahun_id')
            ->orderBy('program_pemerintahan.id')
            ->select([
                'program_pemerintahan.id',
                'program_pemerintahan.tahun_awal',
                'program_pemerintahan.tahun_akhir',
                'periode_tahun.tahun as periode_tahun',
            ])
            ->chunkById(200, function ($programs) use ($defaultRange): void {
                foreach ($programs as $program) {
                    if ($program->tahun_awal && $program->tahun_akhir) {
                        continue;
                    }

                    $range = $program->periode_tahun
                        ? $this->rangeForYear((int) $program->periode_tahun)
                        : $defaultRange;

                    DB::table('program_pemerintahan')
                        ->where('id', $program->id)
                        ->update([
                            'tahun_awal' => $range['tahun_awal'],
                            'tahun_akhir' => $range['tahun_akhir'],
                        ]);
                }
            }, 'program_pemerintahan.id', 'id');
    }

    private function mergeDuplicatePrograms(): void
    {
        $duplicates = DB::table('program_pemerintahan')
            ->select([
                'tahun_awal',
                'tahun_akhir',
                'bidang_urusan_id',
                'kode',
                DB::raw('MIN(id) as keep_id'),
                DB::raw('COUNT(*) as total'),
            ])
            ->whereNull('deleted_at')
            ->groupBy('tahun_awal', 'tahun_akhir', 'bidang_urusan_id', 'kode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('program_pemerintahan')
                ->where('tahun_awal', $duplicate->tahun_awal)
                ->where('tahun_akhir', $duplicate->tahun_akhir)
                ->where('bidang_urusan_id', $duplicate->bidang_urusan_id)
                ->where('kode', $duplicate->kode)
                ->where('id', '<>', $duplicate->keep_id)
                ->orderBy('id')
                ->pluck('id')
                ->each(fn (int $duplicateId) => $this->mergeProgram((int) $duplicate->keep_id, $duplicateId));
        }
    }

    private function mergeProgram(int $keepProgramId, int $duplicateProgramId): void
    {
        DB::table('kegiatan_pemerintahan')
            ->where('program_pemerintahan_id', $duplicateProgramId)
            ->orderBy('id')
            ->get(['id', 'periode_tahun_id', 'kode'])
            ->each(function ($kegiatan) use ($keepProgramId): void {
                $existingKegiatanId = DB::table('kegiatan_pemerintahan')
                    ->where('program_pemerintahan_id', $keepProgramId)
                    ->where('periode_tahun_id', $kegiatan->periode_tahun_id)
                    ->where('kode', $kegiatan->kode)
                    ->whereNull('deleted_at')
                    ->value('id');

                if ($existingKegiatanId) {
                    $this->mergeKegiatan((int) $existingKegiatanId, (int) $kegiatan->id);

                    return;
                }

                DB::table('kegiatan_pemerintahan')
                    ->where('id', $kegiatan->id)
                    ->update(['program_pemerintahan_id' => $keepProgramId]);
            });

        DB::table('program_rpjmd')
            ->where('program_pemerintahan_id', $duplicateProgramId)
            ->update(['program_pemerintahan_id' => $keepProgramId]);

        if (Schema::hasTable('program_rpjmd_program_pemerintahan')) {
            DB::table('program_rpjmd_program_pemerintahan')
                ->where('program_pemerintahan_id', $duplicateProgramId)
                ->orderBy('id')
                ->get(['id', 'program_rpjmd_id'])
                ->each(function ($pivot) use ($keepProgramId): void {
                    $exists = DB::table('program_rpjmd_program_pemerintahan')
                        ->where('program_rpjmd_id', $pivot->program_rpjmd_id)
                        ->where('program_pemerintahan_id', $keepProgramId)
                        ->exists();

                    if ($exists) {
                        DB::table('program_rpjmd_program_pemerintahan')->where('id', $pivot->id)->delete();

                        return;
                    }

                    DB::table('program_rpjmd_program_pemerintahan')
                        ->where('id', $pivot->id)
                        ->update(['program_pemerintahan_id' => $keepProgramId]);
                });
        }

        DB::table('program_pemerintahan')->where('id', $duplicateProgramId)->delete();
    }

    private function mergeKegiatan(int $keepKegiatanId, int $duplicateKegiatanId): void
    {
        DB::table('sub_kegiatan_pemerintahan')
            ->where('kegiatan_pemerintahan_id', $duplicateKegiatanId)
            ->orderBy('id')
            ->get(['id', 'periode_tahun_id', 'kode'])
            ->each(function ($subKegiatan) use ($keepKegiatanId): void {
                $existingSubKegiatan = DB::table('sub_kegiatan_pemerintahan')
                    ->where('kegiatan_pemerintahan_id', $keepKegiatanId)
                    ->where('periode_tahun_id', $subKegiatan->periode_tahun_id)
                    ->where('kode', $subKegiatan->kode)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($existingSubKegiatan) {
                    DB::table('sub_kegiatan_pemerintahan')->where('id', $subKegiatan->id)->delete();

                    return;
                }

                DB::table('sub_kegiatan_pemerintahan')
                    ->where('id', $subKegiatan->id)
                    ->update(['kegiatan_pemerintahan_id' => $keepKegiatanId]);
            });

        DB::table('kegiatan_pemerintahan')->where('id', $duplicateKegiatanId)->delete();
    }

    /**
     * @return array{tahun_awal: int, tahun_akhir: int}
     */
    private function rangeForYear(int $year): array
    {
        $rpjmd = DB::table('rpjmd')
            ->where('tahun_awal', '<=', $year)
            ->where('tahun_akhir', '>=', $year)
            ->orderByDesc('tahun_awal')
            ->first(['tahun_awal', 'tahun_akhir']);

        if ($rpjmd) {
            return [
                'tahun_awal' => (int) $rpjmd->tahun_awal,
                'tahun_akhir' => (int) $rpjmd->tahun_akhir,
            ];
        }

        return [
            'tahun_awal' => $year,
            'tahun_akhir' => $year + 4,
        ];
    }

    /**
     * @return array{tahun_awal: int, tahun_akhir: int}
     */
    private function defaultRange(): array
    {
        $rpjmd = DB::table('rpjmd')
            ->orderByDesc('tahun_awal')
            ->first(['tahun_awal', 'tahun_akhir']);

        if ($rpjmd) {
            return [
                'tahun_awal' => (int) $rpjmd->tahun_awal,
                'tahun_akhir' => (int) $rpjmd->tahun_akhir,
            ];
        }

        $year = DB::table('periode_tahun')
            ->where('status', 'active')
            ->orderByDesc('tahun')
            ->value('tahun')
            ?? DB::table('periode_tahun')->orderByDesc('tahun')->value('tahun');

        if (! $year) {
            throw new RuntimeException('Periode tahun atau RPJMD harus tersedia untuk menentukan periode master program.');
        }

        return [
            'tahun_awal' => (int) $year,
            'tahun_akhir' => (int) $year + 4,
        ];
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $item) => ($item['name'] ?? null) === $index);
    }

    private function setNotNull(string $table, string $column): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} SMALLINT UNSIGNED NOT NULL");
        }
    }
};
