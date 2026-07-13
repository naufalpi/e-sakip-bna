<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->targetTables() as $tableName) {
            $this->changeTargetColumnToText($tableName);
        }
    }

    public function down(): void
    {
        foreach ($this->targetTables() as $tableName) {
            $this->changeTargetColumnToDecimal($tableName);
        }
    }

    /**
     * @return array<int, string>
     */
    private function targetTables(): array
    {
        return [
            'target_indikator_tujuan_daerah',
            'target_indikator_sasaran_daerah',
            'target_indikator_program_rpjmd',
        ];
    }

    private function changeTargetColumnToText(string $tableName): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN target TYPE TEXT USING target::text");

            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->text('target')->nullable()->change();
        });
    }

    private function changeTargetColumnToDecimal(string $tableName): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE {$tableName} ALTER COLUMN target TYPE NUMERIC(18, 4) USING ".
                'CASE '.
                "WHEN target IS NULL OR btrim(target) = '' THEN NULL ".
                "WHEN replace(target, ',', '.') ~ '^-?[0-9]+(\\.[0-9]+)?$' THEN replace(target, ',', '.')::numeric ".
                'ELSE NULL '.
                'END'
            );

            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->decimal('target', 18, 4)->nullable()->change();
        });
    }
};
