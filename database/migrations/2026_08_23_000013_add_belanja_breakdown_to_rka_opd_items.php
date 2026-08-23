<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rka_opd_items', function (Blueprint $table): void {
            $table->decimal('pagu_belanja_operasi_usulan', 20, 2)->default(0)->after('pagu_usulan');
            $table->decimal('pagu_belanja_modal_usulan', 20, 2)->default(0)->after('pagu_belanja_operasi_usulan');
            $table->decimal('pagu_belanja_tidak_terduga_usulan', 20, 2)->default(0)->after('pagu_belanja_modal_usulan');
            $table->decimal('pagu_belanja_transfer_usulan', 20, 2)->default(0)->after('pagu_belanja_tidak_terduga_usulan');
            $table->decimal('pagu_belanja_operasi_hasil_verifikasi', 20, 2)->default(0)->after('pagu_hasil_verifikasi');
            $table->decimal('pagu_belanja_modal_hasil_verifikasi', 20, 2)->default(0)->after('pagu_belanja_operasi_hasil_verifikasi');
            $table->decimal('pagu_belanja_tidak_terduga_hasil_verifikasi', 20, 2)->default(0)->after('pagu_belanja_modal_hasil_verifikasi');
            $table->decimal('pagu_belanja_transfer_hasil_verifikasi', 20, 2)->default(0)->after('pagu_belanja_tidak_terduga_hasil_verifikasi');
        });

        $columns = [
            'operasi' => 'operasi',
            'modal' => 'modal',
            'tidak_terduga' => 'tidak_terduga',
            'transfer' => 'transfer',
        ];

        foreach ($columns as $legacyType => $columnType) {
            DB::table('rka_opd_items')
                ->where('jenis_belanja', $legacyType)
                ->update([
                    "pagu_belanja_{$columnType}_usulan" => DB::raw('pagu_usulan'),
                    "pagu_belanja_{$columnType}_hasil_verifikasi" => DB::raw('pagu_hasil_verifikasi'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('rka_opd_items', function (Blueprint $table): void {
            $table->dropColumn([
                'pagu_belanja_operasi_usulan',
                'pagu_belanja_modal_usulan',
                'pagu_belanja_tidak_terduga_usulan',
                'pagu_belanja_transfer_usulan',
                'pagu_belanja_operasi_hasil_verifikasi',
                'pagu_belanja_modal_hasil_verifikasi',
                'pagu_belanja_tidak_terduga_hasil_verifikasi',
                'pagu_belanja_transfer_hasil_verifikasi',
            ]);
        });
    }
};
