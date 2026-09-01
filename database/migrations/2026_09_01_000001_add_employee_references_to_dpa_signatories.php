<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dpa_opd', function (Blueprint $table) {
            $table->foreignId('pengguna_anggaran_pegawai_id')->nullable()->after('nip_pengguna_anggaran')->constrained('pegawai')->nullOnDelete();
            $table->foreignId('pengguna_anggaran_penempatan_id')->nullable()->after('pengguna_anggaran_pegawai_id')->constrained('riwayat_pejabat_jabatan')->nullOnDelete();
            $table->foreignId('ppkd_pegawai_id')->nullable()->after('nip_ppkd')->constrained('pegawai')->nullOnDelete();
            $table->foreignId('ppkd_penempatan_id')->nullable()->after('ppkd_pegawai_id')->constrained('riwayat_pejabat_jabatan')->nullOnDelete();
            $table->foreignId('sekretaris_daerah_pegawai_id')->nullable()->after('nip_sekretaris_daerah')->constrained('pegawai')->nullOnDelete();
            $table->foreignId('sekretaris_daerah_penempatan_id')->nullable()->after('sekretaris_daerah_pegawai_id')->constrained('riwayat_pejabat_jabatan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dpa_opd', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sekretaris_daerah_penempatan_id');
            $table->dropConstrainedForeignId('sekretaris_daerah_pegawai_id');
            $table->dropConstrainedForeignId('ppkd_penempatan_id');
            $table->dropConstrainedForeignId('ppkd_pegawai_id');
            $table->dropConstrainedForeignId('pengguna_anggaran_penempatan_id');
            $table->dropConstrainedForeignId('pengguna_anggaran_pegawai_id');
        });
    }
};
