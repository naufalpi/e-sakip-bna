<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $lineageIndex = 'renja_opd_version_lineage_live_unique';

    public function up(): void
    {
        Schema::table('renja_opd', function (Blueprint $table): void {
            $table->string('jenis_versi', 30)->default('awal')->index();
            $table->unsignedSmallInteger('nomor_versi')->default(1);
            $table->foreignId('parent_version_id')->nullable()->constrained('renja_opd')->nullOnDelete();
            $table->foreignId('root_version_id')->nullable()->constrained('renja_opd')->nullOnDelete();
            $table->boolean('is_active_version')->default(true)->index();
            $table->text('alasan_perubahan')->nullable();
            $table->string('dasar_perubahan')->nullable();
            $table->date('tanggal_berlaku')->nullable();
            $table->foreignId('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disahkan_pada')->nullable();
            $table->index(['root_version_id', 'nomor_versi'], 'renja_opd_version_lineage_index');
            $table->index(['opd_id', 'tahun', 'jenis_versi'], 'renja_opd_opd_year_version_index');
        });

        // Existing production rows retain their primary keys and all relations.
        // Approved/locked data already represents the established RENJA, while
        // all other rows remain the editable RENJA Awal.
        DB::table('renja_opd')->update([
            'jenis_versi' => DB::raw("CASE WHEN status IN ('approved', 'locked') THEN 'ditetapkan' ELSE 'awal' END"),
            'nomor_versi' => DB::raw("CASE WHEN status IN ('approved', 'locked') THEN 2 ELSE 1 END"),
            'root_version_id' => DB::raw('id'),
            'is_active_version' => true,
            'disahkan_pada' => DB::raw("CASE WHEN status IN ('approved', 'locked') THEN updated_at ELSE NULL END"),
        ]);

        // A lineage-based constraint is safe even if historical production data
        // happens to contain more than one RENJA for the same OPD and year.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s ON renja_opd (root_version_id, nomor_versi) WHERE deleted_at IS NULL',
                $this->lineageIndex,
            ));
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(sprintf('DROP INDEX IF EXISTS %s', $this->lineageIndex));
        }

        Schema::table('renja_opd', function (Blueprint $table): void {
            $table->dropIndex('renja_opd_version_lineage_index');
            $table->dropIndex('renja_opd_opd_year_version_index');
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
    }
};
