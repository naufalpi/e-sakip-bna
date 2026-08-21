<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $oldActiveIndex = 'rkpd_periode_tahun_active_unique';

    private string $versionIndex = 'rkpd_periode_tahun_version_live_unique';

    public function up(): void
    {
        Schema::table('rkpd', function (Blueprint $table): void {
            $table->string('jenis_versi', 30)->default('awal')->index();
            $table->unsignedSmallInteger('nomor_versi')->default(1);
            $table->foreignId('parent_version_id')->nullable()->constrained('rkpd')->nullOnDelete();
            $table->foreignId('root_version_id')->nullable()->constrained('rkpd')->nullOnDelete();
            $table->boolean('is_active_version')->default(true)->index();
            $table->text('alasan_perubahan')->nullable();
            $table->string('dasar_perubahan')->nullable();
            $table->date('tanggal_berlaku')->nullable();
            $table->foreignId('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disahkan_pada')->nullable();
            $table->index(['root_version_id', 'nomor_versi'], 'rkpd_version_lineage_index');
        });

        // Existing production rows keep their IDs and every foreign-key relation.
        // Approved/locked rows already represent the established document; all
        // other rows remain editable as RKPD Awal.
        DB::table('rkpd')->update([
            'jenis_versi' => DB::raw("CASE WHEN status IN ('approved', 'locked') THEN 'ditetapkan' ELSE 'awal' END"),
            'nomor_versi' => DB::raw("CASE WHEN status IN ('approved', 'locked') THEN 2 ELSE 1 END"),
            'root_version_id' => DB::raw('id'),
            'is_active_version' => true,
        ]);

        $this->dropIndex($this->oldActiveIndex);
        $this->dropIndex('rkpd_periode_tahun_unique');

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON rkpd (periode_tahun_id, tahun, jenis_versi) WHERE deleted_at IS NULL',
                $this->versionIndex,
            ));
        }
    }

    public function down(): void
    {
        $this->dropIndex($this->versionIndex);

        Schema::table('rkpd', function (Blueprint $table): void {
            $table->dropIndex('rkpd_version_lineage_index');
            $table->dropForeign(['parent_version_id']);
            $table->dropForeign(['root_version_id']);
            $table->dropForeign(['disahkan_oleh']);
            $table->dropColumn([
                'jenis_versi',
                'nomor_versi',
                'parent_version_id',
                'root_version_id',
                'is_active_version',
                'alasan_perubahan',
                'dasar_perubahan',
                'tanggal_berlaku',
                'disahkan_oleh',
                'disahkan_pada',
            ]);
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON rkpd (periode_tahun_id, tahun) WHERE deleted_at IS NULL',
                $this->oldActiveIndex,
            ));
        }
    }

    private function dropIndex(string $index): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf('ALTER TABLE rkpd DROP CONSTRAINT IF EXISTS %s', $index));
        }

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(sprintf('DROP INDEX IF EXISTS %s', $index));
        }
    }
};
