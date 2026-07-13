<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rpjmd', function (Blueprint $table) {
            if (! Schema::hasColumn('rpjmd', 'struktur_tujuan_mode')) {
                $table->string('struktur_tujuan_mode', 50)
                    ->default('tujuan_lintas_misi')
                    ->after('status')
                    ->index();
            }

            if (! Schema::hasColumn('rpjmd', 'struktur_sasaran_mode')) {
                $table->string('struktur_sasaran_mode', 50)
                    ->default('sasaran_langsung_tujuan')
                    ->after('struktur_tujuan_mode')
                    ->index();
            }
        });

        if (! Schema::hasTable('tujuan_daerah_misi')) {
            Schema::create('tujuan_daerah_misi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tujuan_daerah_id')->constrained('tujuan_daerah')->cascadeOnDelete();
                $table->foreignId('rpjmd_misi_id')->constrained('rpjmd_misi')->cascadeOnDelete();
                $table->unsignedSmallInteger('urutan')->default(1);
                $table->timestamps();

                $table->unique(['tujuan_daerah_id', 'rpjmd_misi_id'], 'tujuan_daerah_misi_unique');
                $table->index(['rpjmd_misi_id', 'urutan']);
            });
        }

        if (! Schema::hasTable('sasaran_daerah_indikator_tujuan')) {
            Schema::create('sasaran_daerah_indikator_tujuan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sasaran_daerah_id')->constrained('sasaran_daerah')->cascadeOnDelete();
                $table->foreignId('indikator_tujuan_daerah_id')->constrained('indikator_tujuan_daerah')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['sasaran_daerah_id', 'indikator_tujuan_daerah_id'], 'sasaran_indikator_tujuan_unique');
                $table->index('indikator_tujuan_daerah_id', 'sasaran_indikator_tujuan_idx');
            });
        }

        $this->backfillTujuanMisiPivot();
    }

    public function down(): void
    {
        Schema::dropIfExists('sasaran_daerah_indikator_tujuan');
        Schema::dropIfExists('tujuan_daerah_misi');

        Schema::table('rpjmd', function (Blueprint $table) {
            if (Schema::hasColumn('rpjmd', 'struktur_sasaran_mode')) {
                $table->dropColumn('struktur_sasaran_mode');
            }

            if (Schema::hasColumn('rpjmd', 'struktur_tujuan_mode')) {
                $table->dropColumn('struktur_tujuan_mode');
            }
        });
    }

    private function backfillTujuanMisiPivot(): void
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'pgsql') {
            DB::statement(<<<'SQL'
                INSERT INTO tujuan_daerah_misi (tujuan_daerah_id, rpjmd_misi_id, urutan, created_at, updated_at)
                SELECT tujuan_daerah.id, tujuan_daerah.rpjmd_misi_id, 1, NOW(), NOW()
                FROM tujuan_daerah
                WHERE tujuan_daerah.rpjmd_misi_id IS NOT NULL
                ON CONFLICT (tujuan_daerah_id, rpjmd_misi_id) DO NOTHING
            SQL);

            DB::statement(<<<'SQL'
                INSERT INTO tujuan_daerah_misi (tujuan_daerah_id, rpjmd_misi_id, urutan, created_at, updated_at)
                SELECT tujuan_daerah.id, rpjmd_misi.id, rpjmd_misi.urutan, NOW(), NOW()
                FROM tujuan_daerah
                INNER JOIN rpjmd_misi ON rpjmd_misi.rpjmd_visi_id = tujuan_daerah.rpjmd_visi_id
                WHERE tujuan_daerah.rpjmd_misi_id IS NULL
                  AND NOT EXISTS (
                      SELECT 1
                      FROM tujuan_daerah_misi existing
                      WHERE existing.tujuan_daerah_id = tujuan_daerah.id
                  )
                ON CONFLICT (tujuan_daerah_id, rpjmd_misi_id) DO NOTHING
            SQL);

            return;
        }

        if ($connection === 'sqlite') {
            DB::statement(<<<'SQL'
                INSERT OR IGNORE INTO tujuan_daerah_misi (tujuan_daerah_id, rpjmd_misi_id, urutan, created_at, updated_at)
                SELECT tujuan_daerah.id, tujuan_daerah.rpjmd_misi_id, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                FROM tujuan_daerah
                WHERE tujuan_daerah.rpjmd_misi_id IS NOT NULL
            SQL);

            DB::statement(<<<'SQL'
                INSERT OR IGNORE INTO tujuan_daerah_misi (tujuan_daerah_id, rpjmd_misi_id, urutan, created_at, updated_at)
                SELECT tujuan_daerah.id, rpjmd_misi.id, rpjmd_misi.urutan, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                FROM tujuan_daerah
                INNER JOIN rpjmd_misi ON rpjmd_misi.rpjmd_visi_id = tujuan_daerah.rpjmd_visi_id
                WHERE tujuan_daerah.rpjmd_misi_id IS NULL
                  AND NOT EXISTS (
                      SELECT 1
                      FROM tujuan_daerah_misi existing
                      WHERE existing.tujuan_daerah_id = tujuan_daerah.id
                  )
            SQL);
        }
    }
};
