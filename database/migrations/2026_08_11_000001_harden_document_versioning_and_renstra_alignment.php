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
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->foreignId('root_version_id')
                    ->nullable()
                    ->after('parent_version_id')
                    ->constrained($table)
                    ->nullOnDelete();
            });

            $this->backfillRootVersionIds($table);
            $this->normaliseVersionNumbers($table);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unique(['root_version_id', 'nomor_versi']);
            });

            // PostgreSQL enforces the single active version at the database
            // level. SQLite is used by the automated test suite and its
            // partial-index handling differs across supported versions, so
            // transaction locks in the service remain the portable guard.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement(sprintf(
                    'CREATE UNIQUE INDEX %s ON %s (root_version_id) WHERE is_active_version IS TRUE',
                    $table.'_one_active_version_per_root',
                    $table,
                ));
            }
        }

        Schema::table('renstra_opd', function (Blueprint $table): void {
            $table->boolean('perlu_penyesuaian_rpjmd')->default(false)->after('is_active_version')->index();
            $table->foreignId('rpjmd_perubahan_terbaru_id')
                ->nullable()
                ->after('perlu_penyesuaian_rpjmd')
                ->constrained('rpjmd')
                ->nullOnDelete();
            $table->timestamp('rpjmd_penyesuaian_terdeteksi_pada')
                ->nullable()
                ->after('rpjmd_perubahan_terbaru_id');
        });
    }

    public function down(): void
    {
        Schema::table('renstra_opd', function (Blueprint $table): void {
            $table->dropForeign(['rpjmd_perubahan_terbaru_id']);
            $table->dropColumn([
                'perlu_penyesuaian_rpjmd',
                'rpjmd_perubahan_terbaru_id',
                'rpjmd_penyesuaian_terdeteksi_pada',
            ]);
        });

        foreach (['rpjmd', 'renstra_opd'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement(sprintf('DROP INDEX IF EXISTS %s', $table.'_one_active_version_per_root'));
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropUnique(['root_version_id', 'nomor_versi']);
                $blueprint->dropForeign(['root_version_id']);
                $blueprint->dropColumn('root_version_id');
            });
        }
    }

    private function backfillRootVersionIds(string $table): void
    {
        DB::table($table)
            ->whereNull('parent_version_id')
            ->whereNull('root_version_id')
            ->update(['root_version_id' => DB::raw('id')]);

        do {
            $updated = DB::update(sprintf(
                'UPDATE %1$s AS child
                 SET root_version_id = parent.root_version_id
                 FROM %1$s AS parent
                 WHERE child.parent_version_id = parent.id
                   AND child.root_version_id IS NULL
                   AND parent.root_version_id IS NOT NULL',
                $table,
            ));
        } while ($updated > 0);

        DB::table($table)
            ->whereNull('root_version_id')
            ->update(['root_version_id' => DB::raw('id')]);
    }

    private function normaliseVersionNumbers(string $table): void
    {
        DB::statement(sprintf(
            'UPDATE %1$s AS document
             SET nomor_versi = ranked.nomor_versi
             FROM (
                 SELECT id,
                        ROW_NUMBER() OVER (
                            PARTITION BY root_version_id
                            ORDER BY nomor_versi ASC, created_at ASC, id ASC
                        ) AS nomor_versi
                 FROM %1$s
             ) AS ranked
             WHERE document.id = ranked.id',
            $table,
        ));
    }
};
