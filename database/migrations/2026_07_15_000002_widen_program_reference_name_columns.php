<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->tables() as $table) {
            $this->alterColumn($table, 'text');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->tables() as $table) {
            $this->alterColumn($table, 'varchar(255)', 'left(nama, 255)');
        }
    }

    /**
     * @return array<int, string>
     */
    private function tables(): array
    {
        return [
            'bidang_urusan',
            'program_pemerintahan',
            'kegiatan_pemerintahan',
            'sub_kegiatan_pemerintahan',
        ];
    }

    private function alterColumn(string $table, string $type, ?string $using = null): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN nama TYPE {$type}".($using ? " USING {$using}" : ''));

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY nama {$type}");
        }
    }
};
