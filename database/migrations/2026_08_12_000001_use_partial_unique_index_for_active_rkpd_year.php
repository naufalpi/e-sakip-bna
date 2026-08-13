<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $oldIndex = 'rkpd_periode_tahun_unique';

    private string $activeIndex = 'rkpd_periode_tahun_active_unique';

    public function up(): void
    {
        if (! Schema::hasTable('rkpd')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE rkpd DROP CONSTRAINT IF EXISTS {$this->oldIndex}");
            DB::statement("DROP INDEX IF EXISTS {$this->oldIndex}");
            DB::statement("DROP INDEX IF EXISTS {$this->activeIndex}");
            DB::statement("CREATE UNIQUE INDEX {$this->activeIndex} ON rkpd (periode_tahun_id, tahun) WHERE deleted_at IS NULL");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP INDEX IF EXISTS {$this->oldIndex}");
            DB::statement("DROP INDEX IF EXISTS {$this->activeIndex}");
            DB::statement("CREATE UNIQUE INDEX {$this->activeIndex} ON rkpd (periode_tahun_id, tahun) WHERE deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('rkpd')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$this->activeIndex}");
            DB::statement("ALTER TABLE rkpd DROP CONSTRAINT IF EXISTS {$this->oldIndex}");
            DB::statement("DROP INDEX IF EXISTS {$this->oldIndex}");
            DB::statement("ALTER TABLE rkpd ADD CONSTRAINT {$this->oldIndex} UNIQUE (periode_tahun_id, tahun)");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP INDEX IF EXISTS {$this->activeIndex}");
            DB::statement("CREATE UNIQUE INDEX {$this->oldIndex} ON rkpd (periode_tahun_id, tahun)");
        }
    }
};
