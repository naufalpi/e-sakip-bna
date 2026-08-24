<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rka_opd_items', function (Blueprint $table) {
            $table->decimal('pagu_rka', 20, 2)->default(0);
            $table->decimal('pagu_belanja_operasi', 20, 2)->default(0);
            $table->decimal('pagu_belanja_modal', 20, 2)->default(0);
            $table->decimal('pagu_belanja_tidak_terduga', 20, 2)->default(0);
            $table->decimal('pagu_belanja_transfer', 20, 2)->default(0);
        });

        // Pertahankan nilai yang sebelumnya efektif di preview/export dan DPA.
        DB::statement(<<<'SQL'
            UPDATE rka_opd_items
            SET
                pagu_rka = CASE
                    WHEN EXISTS (
                        SELECT 1 FROM rka_opd
                        WHERE rka_opd.id = rka_opd_items.rka_opd_id
                          AND rka_opd.status IN ('verified', 'approved', 'locked')
                    ) THEN pagu_hasil_verifikasi
                    ELSE pagu_usulan
                END,
                pagu_belanja_operasi = CASE
                    WHEN EXISTS (
                        SELECT 1 FROM rka_opd
                        WHERE rka_opd.id = rka_opd_items.rka_opd_id
                          AND rka_opd.status IN ('verified', 'approved', 'locked')
                    ) THEN pagu_belanja_operasi_hasil_verifikasi
                    ELSE pagu_belanja_operasi_usulan
                END,
                pagu_belanja_modal = CASE
                    WHEN EXISTS (
                        SELECT 1 FROM rka_opd
                        WHERE rka_opd.id = rka_opd_items.rka_opd_id
                          AND rka_opd.status IN ('verified', 'approved', 'locked')
                    ) THEN pagu_belanja_modal_hasil_verifikasi
                    ELSE pagu_belanja_modal_usulan
                END,
                pagu_belanja_tidak_terduga = CASE
                    WHEN EXISTS (
                        SELECT 1 FROM rka_opd
                        WHERE rka_opd.id = rka_opd_items.rka_opd_id
                          AND rka_opd.status IN ('verified', 'approved', 'locked')
                    ) THEN pagu_belanja_tidak_terduga_hasil_verifikasi
                    ELSE pagu_belanja_tidak_terduga_usulan
                END,
                pagu_belanja_transfer = CASE
                    WHEN EXISTS (
                        SELECT 1 FROM rka_opd
                        WHERE rka_opd.id = rka_opd_items.rka_opd_id
                          AND rka_opd.status IN ('verified', 'approved', 'locked')
                    ) THEN pagu_belanja_transfer_hasil_verifikasi
                    ELSE pagu_belanja_transfer_usulan
                END
            SQL);

        DB::table('permissions')
            ->where('name', 'rka.verify')
            ->update(['label' => 'Persetujuan RKA OPD', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'rka.verify')
            ->update(['label' => 'Verifikasi RKA OPD', 'updated_at' => now()]);

        Schema::table('rka_opd_items', function (Blueprint $table) {
            $table->dropColumn([
                'pagu_rka',
                'pagu_belanja_operasi',
                'pagu_belanja_modal',
                'pagu_belanja_tidak_terduga',
                'pagu_belanja_transfer',
            ]);
        });
    }
};
