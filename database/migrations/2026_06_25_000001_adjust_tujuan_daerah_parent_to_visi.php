<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tujuan_daerah', 'rpjmd_visi_id')) {
            Schema::table('tujuan_daerah', function (Blueprint $table) {
                $table->foreignId('rpjmd_visi_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('rpjmd_visi')
                    ->cascadeOnDelete();

                $table->index(['rpjmd_visi_id', 'urutan']);
            });
        }

        $this->backfillVisiParent();
        $this->makeMisiParentOptional();
    }

    public function down(): void
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'pgsql') {
            DB::statement('ALTER TABLE tujuan_daerah DROP CONSTRAINT IF EXISTS tujuan_daerah_rpjmd_misi_id_foreign');
            DB::statement('ALTER TABLE tujuan_daerah ADD CONSTRAINT tujuan_daerah_rpjmd_misi_id_foreign FOREIGN KEY (rpjmd_misi_id) REFERENCES rpjmd_misi(id) ON DELETE CASCADE');
        }

        if (Schema::hasColumn('tujuan_daerah', 'rpjmd_visi_id')) {
            Schema::table('tujuan_daerah', function (Blueprint $table) {
                $table->dropForeign(['rpjmd_visi_id']);
                $table->dropIndex(['rpjmd_visi_id', 'urutan']);
                $table->dropColumn('rpjmd_visi_id');
            });
        }
    }

    private function backfillVisiParent(): void
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'pgsql') {
            DB::statement(<<<'SQL'
                UPDATE tujuan_daerah
                SET rpjmd_visi_id = rpjmd_misi.rpjmd_visi_id
                FROM rpjmd_misi
                WHERE tujuan_daerah.rpjmd_misi_id = rpjmd_misi.id
                  AND tujuan_daerah.rpjmd_visi_id IS NULL
                  AND rpjmd_misi.rpjmd_visi_id IS NOT NULL
            SQL);

            return;
        }

        if ($connection === 'sqlite') {
            DB::statement(<<<'SQL'
                UPDATE tujuan_daerah
                SET rpjmd_visi_id = (
                    SELECT rpjmd_misi.rpjmd_visi_id
                    FROM rpjmd_misi
                    WHERE rpjmd_misi.id = tujuan_daerah.rpjmd_misi_id
                )
                WHERE rpjmd_visi_id IS NULL
            SQL);
        }
    }

    private function makeMisiParentOptional(): void
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE tujuan_daerah ALTER COLUMN rpjmd_misi_id DROP NOT NULL');
        DB::statement('ALTER TABLE tujuan_daerah DROP CONSTRAINT IF EXISTS tujuan_daerah_rpjmd_misi_id_foreign');
        DB::statement('ALTER TABLE tujuan_daerah ADD CONSTRAINT tujuan_daerah_rpjmd_misi_id_foreign FOREIGN KEY (rpjmd_misi_id) REFERENCES rpjmd_misi(id) ON DELETE SET NULL');
    }
};
