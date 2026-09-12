<?php

namespace App\Services\Imports;

use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use RuntimeException;
use ZipArchive;

class ImportTemplateService
{
    /**
     * @return array{filename: string, content: string}
     */
    public function make(string $module, array $context = []): array
    {
        [$filename, $sheets] = match ($module) {
            'rpjmd' => ['template-import-rpjmd-banjarnegara.xlsx', $this->rpjmdSheets()],
            'renstra_opd' => ['template-import-renstra-opd-banjarnegara.xlsx', $this->renstraSheets()],
            'rkpd' => ['template-import-rkpd-banjarnegara.xlsx', $this->rkpdSheets()],
            'jabatan_organisasi' => ['template-import-struktur-organisasi-banjarnegara.xlsx', $this->jabatanOrganisasiSheets($context['opd_id'] ?? null)],
            'pegawai_opd' => ['template-import-pegawai-opd-banjarnegara.xlsx', $this->pegawaiOpdSheets($context['opd_id'] ?? null)],
            default => throw new RuntimeException('Template import tidak tersedia.'),
        };

        return [
            'filename' => $filename,
            'content' => $this->buildWorkbook($sheets),
        ];
    }

    /**
     * @return array<string, array<int, array<int, string|int|float|null>>>
     */
    private function rpjmdSheets(): array
    {
        return [
            'Template RPJMD' => [
                ['level', 'kode', 'uraian', 'rpjmd_judul', 'nomor_perda', 'tahun_awal', 'tahun_akhir', 'tahun_target', 'target', 'target_text', 'definisi_operasional', 'alasan_pemilihan', 'formulasi_pengukuran', 'tipe_perhitungan', 'sumber_data', 'urusan_kode', 'opd_kode', 'opd_nama', 'peran', 'is_utama', 'keterangan', 'satuan', 'strategi_kode', 'misi_kode_terkait', 'indikator_tujuan_kode_terkait'],
                ['rpjmd', 'RPJMD-2025', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Perda Nomor ... Tahun 2025', 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, 'Baris identitas dokumen RPJMD.'],
                ['visi', 'V-1', 'Banjarnegara yang maju, sejahtera, dan berdaya saing', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                ['misi', 'M-1', 'Meningkatkan kualitas pembangunan manusia dan pelayanan publik', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                ['tujuan', 'T-1', 'Meningkatnya kesejahteraan masyarakat', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'M-1', null],
                ['indikator_tujuan', 'IT-1', 'Indeks Pembangunan Manusia', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, 2025, 75, '75 poin', 'Indikator komposit kualitas pembangunan manusia.', 'Menggambarkan capaian dasar pembangunan manusia daerah.', 'Mengacu metodologi IPM yang diterbitkan BPS.', 'non_kumulatif', 'BPS', null, '1.01', 'Dinas Contoh', null, null, null, 'Angka', null, null, null],
                ['sasaran', 'S-1', 'Meningkatnya kualitas layanan dasar', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
                ['indikator_sasaran', 'IS-1', 'Indeks Kepuasan Masyarakat', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, 2025, 80, '80 poin', 'Nilai kepuasan masyarakat terhadap layanan dasar.', 'Menjadi ukuran mutu pelayanan publik.', 'Nilai hasil survei IKM perangkat daerah.', 'non_kumulatif', 'Survei IKM', null, '1.01', 'Dinas Contoh', null, null, null, 'Angka', null, null, null],
                ['program', 'PR-1', 'Program Penunjang Urusan Pemerintahan Daerah', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, '1.01', null, null, null, null, null],
                ['indikator_program', 'IPR-1', 'Persentase layanan penunjang yang terpenuhi', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, 2025, 90, '90 persen', 'Persentase pemenuhan layanan penunjang perangkat daerah.', 'Menunjukkan kesiapan administrasi dan dukungan layanan.', '(Jumlah layanan terpenuhi / seluruh layanan) x 100', 'kumulatif', 'Laporan OPD', null, '1.01', 'Dinas Contoh', null, null, null, 'Persen', null, null, null],
                ['opd_penanggung_jawab', null, 'Penanggung jawab Program Penunjang Urusan Pemerintahan Daerah', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', null, 2025, 2029, null, null, null, null, null, null, null, null, null, '1.01', 'Dinas Contoh', 'penanggung_jawab', '1', 'Gunakan opd_kode sesuai master OPD.'],
            ],
            'Petunjuk' => [
                ['Kolom', 'Keterangan'],
                ['level', 'Wajib. Nilai: rpjmd, visi, misi, tujuan, indikator_tujuan, sasaran, indikator_sasaran, program, indikator_program, opd_penanggung_jawab. Strategi dipilih dari Master Strategi Daerah pada baris program.'],
                ['uraian', 'Wajib. Isi judul/nama/uraian sesuai level.'],
                ['tahun_target', 'Wajib untuk baris target atau indikator yang sekaligus berisi target. Tahun harus tersedia di master periode.'],
                ['tipe_perhitungan', 'Isi kumulatif atau non_kumulatif. Kosong dianggap non_kumulatif.'],
                ['satuan', 'Gunakan nama atau simbol yang sama dengan Master Satuan Indikator.'],
                ['strategi_kode', 'Opsional pada baris program. Gunakan kode dari Master Strategi Daerah.'],
                ['misi_kode_terkait', 'Opsional pada baris tujuan. Pisahkan beberapa kode misi dengan koma untuk tujuan lintas misi.'],
                ['indikator_tujuan_kode_terkait', 'Opsional pada baris sasaran untuk pola sasaran melalui indikator tujuan. Pisahkan beberapa kode dengan koma.'],
                ['opd_kode', 'Untuk baris indikator dapat diisi sebagai PD penanggung jawab. Untuk opd_penanggung_jawab wajib diisi.'],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<int, string|int|float|null>>>
     */
    private function rkpdSheets(): array
    {
        return [
            'Baris RKPD' => [
                ['opd_kode', 'sub_kegiatan_kode', 'program_rpjmd_kode', 'indikator', 'target_akhir_renstra', 'realisasi_capaian_renja_tahun_lalu', 'prakiraan_capaian_target_renja_tahun_berjalan', 'target', 'pagu_indikatif', 'lokasi', 'sumber_dana', 'prioritas_nasional', 'prioritas_daerah', 'kelompok_sasaran', 'prakiraan_maju_target', 'prakiraan_maju_pagu_indikatif', 'perangkat_daerah_penanggung_jawab', 'urutan'],
                ['1.01', '1.01.01.2.01.0001', 'PR-1', 'Persentase dokumen selesai', '100%', '90%', '95%', '100%', 150000000, 'Banjarnegara', 'APBD', 'Prioritas Nasional', 'Prioritas Daerah', 'Masyarakat', '100%', 175000000, null, 1],
            ],
            'Petunjuk' => [
                ['Kolom', 'Keterangan'],
                ['opd_kode', 'Wajib. Kode OPD harus sama dengan master OPD aktif.'],
                ['sub_kegiatan_kode', 'Wajib. Kode sub kegiatan harus tersedia pada master untuk tahun RKPD.'],
                ['program_rpjmd_kode', 'Opsional. Gunakan kode Program RPJMD bila baris perlu dihubungkan ke RPJMD.'],
                ['perangkat_daerah_penanggung_jawab', 'Opsional. Bila dikosongkan, otomatis diisi dari OPD yang cocok dengan opd_kode saat preview.'],
                ['pagu_indikatif / prakiraan_maju_pagu_indikatif', 'Opsional. Isi angka tanpa Rp; pemisah ribuan diperbolehkan.'],
                ['urutan', 'Opsional. Bila kosong, sistem menempatkan baris setelah urutan terakhir.'],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<int, string|int|float|null>>>
     */
    private function jabatanOrganisasiSheets(?int $opdId = null): array
    {
        return [
            'Struktur Organisasi' => [
                'rows' => [['Nama Jabatan *', 'Level Jabatan *', 'Kode OPD **', 'Kode Unit', 'Nama Jabatan Atasan **', 'Kode OPD Atasan', 'Kode Unit Atasan', 'Eselon', 'Urutan', 'Status']],
                'required_columns' => [0, 1, 2, 4],
                'widths' => [42, 24, 18, 20, 42, 20, 20, 16, 11, 14],
                'blank_rows' => 500,
                'tab_color' => '0B4A82',
                'validations' => [
                    ['column' => 1, 'values' => ['Kepala Daerah', 'JPT Pratama', 'Administrator', 'Pengawas', 'Fungsional', 'Pelaksana'], 'title' => 'Level jabatan', 'message' => 'Pilih level jabatan dari daftar.'],
                    ['column' => 7, 'values' => ['Eselon II.a', 'Eselon II.b', 'Eselon III.a', 'Eselon III.b', 'Eselon IV.a', 'Eselon IV.b', 'Non-eselon'], 'title' => 'Eselon', 'message' => 'Pilih eselon atau kosongkan.'],
                    ['column' => 9, 'values' => ['Aktif', 'Nonaktif'], 'title' => 'Status', 'message' => 'Kosong berarti Aktif.'],
                ],
            ],
            'Contoh Struktur' => [
                'rows' => [
                    ['Urutan pengisian', 'Nama Jabatan', 'Level', 'Kode OPD', 'Kode Unit', 'Atasan Langsung', 'Keterangan'],
                    [1, 'Bupati Banjarnegara', 'Kepala Daerah', null, null, null, 'Kepala Daerah tidak memakai kode OPD dan atasan.'],
                    [2, 'Kepala Dinas Contoh', 'JPT Pratama', 'KODE_OPD', null, 'Bupati Banjarnegara', 'Kepala OPD umumnya tidak memakai kode unit.'],
                    [3, 'Sekretaris Dinas Contoh', 'Administrator', 'KODE_OPD', 'KODE_UNIT_SEKRETARIAT', 'Kepala Dinas Contoh', 'Isi identitas atasan persis seperti baris atasannya.'],
                    [4, 'Kepala Bidang Contoh', 'Administrator', 'KODE_OPD', 'KODE_UNIT_BIDANG', 'Kepala Dinas Contoh', 'Urutan saudara dapat diatur melalui kolom Urutan.'],
                    [5, 'Kepala Subbagian Contoh', 'Pengawas', 'KODE_OPD', 'KODE_UNIT_SUBBAG', 'Sekretaris Dinas Contoh', 'Kode unit harus terdaftar pada OPD yang sama.'],
                    [6, 'Pranata Komputer Ahli Pertama', 'Fungsional', 'KODE_OPD', 'KODE_UNIT_BIDANG', 'Kepala Bidang Contoh', 'Satu nomenklatur fungsional dapat diisi beberapa pegawai.'],
                ],
                'widths' => [18, 42, 22, 18, 25, 42, 62],
                'tab_color' => '5B9BD5',
            ],
            'Petunjuk' => [
                'rows' => [
                    ['Bagian', 'Kolom / topik', 'Petunjuk'],
                    ['MULAI', 'Urutan kerja', '1) Isi sheet Struktur Organisasi mulai baris 2. 2) Gunakan kode pada sheet Referensi OPD & Unit. 3) Jangan mengubah judul kolom. 4) Unggah dan periksa preview. 5) Terapkan setelah semua baris valid.'],
                    ['MULAI', 'Tanda kolom', '* selalu wajib. ** wajib kecuali untuk Kepala Daerah. Kolom tanpa tanda boleh dikosongkan.'],
                    ['AMAN', 'Cara kerja import', 'Unggah hanya membuat preview. Data disimpan dalam satu transaksi setelah tombol Terapkan Import ditekan. Import tidak menghapus jabatan yang tidak dicantumkan.'],
                    ['AMAN', 'Pembaruan data', 'Gabungan Nama Jabatan + Kode OPD + Kode Unit menjadi identitas. Jika identitas sudah ada, data jabatan tersebut diperbarui; jika belum, dibuat baru.'],
                    ['STRUKTUR', 'Urutan baris', 'Susun atasan sebelum bawahannya: Kepala Daerah, Kepala OPD, Sekretaris/Kabid, Kasubbag/Kasi, lalu Fungsional/Pelaksana.'],
                    ['STRUKTUR', 'Kode OPD', 'Salin kode persis dari sheet Referensi OPD & Unit. Kepala Daerah tidak memakai kode OPD maupun unit.'],
                    ['STRUKTUR', 'Kode Unit', 'Isi bila jabatan melekat pada unit. Kepala OPD umumnya tidak memakai kode unit.'],
                    ['STRUKTUR', 'Identitas atasan', 'Nama Jabatan Atasan, Kode OPD Atasan, dan Kode Unit Atasan harus sama persis dengan identitas atasan pada file atau sistem.'],
                    ['STRUKTUR', 'Urutan', 'Angka 0–65535. Angka lebih kecil tampil lebih dahulu di bawah atasan yang sama. Kosong dianggap 0.'],
                    ['STRUKTUR', 'Status', 'Pilih Aktif atau Nonaktif. Kosong dianggap Aktif.'],
                    ['VALIDASI', 'Batas data', 'Maksimal 2.000 baris struktur per file. Seluruh baris harus valid sebelum import dapat diterapkan.'],
                    ['VERSI', 'Template', 'Template Struktur Organisasi E-SAKIP v2 · diunduh '.now()->timezone(config('app.timezone'))->format('d-m-Y H:i').' WIB.'],
                ],
                'widths' => [16, 28, 110],
                'tab_color' => '70AD47',
            ],
            'Referensi OPD & Unit' => [
                'rows' => $this->opdUnitReferenceRows($opdId),
                'widths' => [18, 52, 22, 52],
                'tab_color' => 'A5A5A5',
            ],
        ];
    }

    private function pegawaiOpdSheets(?int $opdId = null): array
    {
        return [
            'Pegawai OPD' => [
                'rows' => [['Nama Pegawai *', 'NIP', 'Jenis Pegawai', 'Status Pegawai', 'Pangkat / Golongan', 'Nama Jabatan *', 'Kode OPD **', 'Kode Unit', 'Jenis Penugasan', 'TMT Jabatan *', 'Tanggal Selesai', 'Nomor SK', 'Tanggal SK', 'Akun Pengguna']],
                'required_columns' => [0, 5, 6, 9],
                'widths' => [38, 24, 20, 18, 28, 46, 18, 22, 22, 18, 18, 28, 18, 30],
                'blank_rows' => 1000,
                'tab_color' => '107C41',
                'validations' => [
                    ['column' => 2, 'values' => ['Pejabat Negara', 'PNS', 'PPPK', 'Non-ASN'], 'title' => 'Jenis pegawai', 'message' => 'Kosong berarti PNS.'],
                    ['column' => 3, 'values' => ['Aktif', 'Nonaktif'], 'title' => 'Status pegawai', 'message' => 'Kosong berarti Aktif untuk data baru.'],
                    ['column' => 8, 'values' => ['Definitif', 'Penjabat (Pj.)', 'Pelaksana Tugas (Plt.)', 'Pelaksana Harian (Plh.)'], 'title' => 'Jenis penugasan', 'message' => 'Kosong berarti Definitif.'],
                ],
            ],
            'Contoh Pegawai' => [
                'rows' => [
                    ['Contoh Nama Pegawai', 'Contoh NIP', 'Jenis', 'Status', 'Pangkat / Golongan', 'Nama Jabatan', 'Kode OPD', 'Kode Unit', 'Penugasan', 'TMT Jabatan', 'Tanggal Selesai', 'Catatan'],
                    ['Nama Kepala Dinas', '197001011995011001', 'PNS', 'Aktif', 'Pembina Utama Muda, IV/c', 'Kepala Dinas Contoh', 'KODE_OPD', null, 'Definitif', '2026-01-03', null, 'Kosongkan Tanggal Selesai untuk penempatan aktif.'],
                    ['Nama Pelaksana Tugas', '198701012011011001', 'PNS', 'Aktif', 'Penata Tingkat I, III/d', 'Kepala Bidang Contoh', 'KODE_OPD', 'KODE_UNIT_BIDANG', 'Pelaksana Tugas (Plt.)', '2026-01-10', '2026-04-09', 'Masa Plt. harus memiliki periode yang tidak bertumpang tindih.'],
                    ['Nama Pranata Komputer', '199001012020011001', 'PNS', 'Aktif', 'Penata Muda, III/a', 'Pranata Komputer Ahli Pertama', 'KODE_OPD', 'KODE_UNIT_BIDANG', 'Definitif', '2026-01-03', null, 'JF/Pelaksana dapat memiliki lebih dari satu pemegang.'],
                ],
                'widths' => [34, 24, 18, 16, 28, 44, 18, 24, 24, 18, 18, 62],
                'tab_color' => '70AD47',
            ],
            'Petunjuk' => [
                'rows' => [
                    ['Bagian', 'Kolom / topik', 'Petunjuk'],
                    ['MULAI', 'Urutan kerja', '1) Pastikan Struktur Organisasi sudah tersedia. 2) Isi sheet Pegawai OPD mulai baris 2. 3) Salin identitas jabatan dari sheet Referensi Jabatan. 4) Unggah dan periksa preview. 5) Terapkan setelah semua baris valid.'],
                    ['MULAI', 'Tanda kolom', '* selalu wajib. ** wajib kecuali untuk pejabat tingkat kabupaten/Kepala Daerah. Kolom tanpa tanda boleh dikosongkan.'],
                    ['AMAN', 'Cara kerja import', 'Unggah hanya membuat preview. Data disimpan dalam satu transaksi setelah tombol Terapkan Import ditekan. Import tidak menghapus pegawai atau riwayat yang tidak dicantumkan.'],
                    ['PEGAWAI', 'NIP', 'Sangat disarankan untuk PNS/PPPK. Isi tepat 18 digit. Kolom telah diformat sebagai teks agar angka nol dan seluruh digit tetap utuh. Jangan memakai notasi ilmiah.'],
                    ['PEGAWAI', 'Jenis Pegawai', 'Pilih Pejabat Negara, PNS, PPPK, atau Non-ASN. Kosong dianggap PNS.'],
                    ['PEGAWAI', 'Status Pegawai', 'Pilih Aktif atau Nonaktif. Jika dikosongkan, data baru dianggap Aktif dan status data lama tetap dipertahankan.'],
                    ['PENEMPATAN', 'Identitas jabatan', 'Nama Jabatan + Kode OPD + Kode Unit harus sama persis dengan Referensi Jabatan. Kode Unit boleh kosong bila jabatan tidak terikat unit.'],
                    ['PENEMPATAN', 'TMT Jabatan', 'Tanggal efektif mulai bertugas. Gunakan YYYY-MM-DD atau DD/MM/YYYY.'],
                    ['PENEMPATAN', 'Tanggal Selesai', 'Kosongkan untuk penempatan aktif. Isi ketika mutasi, pensiun, diganti, atau masa Plt./Plh./Pj. berakhir.'],
                    ['PENEMPATAN', 'Pergantian pejabat', 'Akhiri masa tugas pejabat struktural lama sebelum TMT pejabat baru agar periode tidak bertumpang tindih.'],
                    ['AKUN', 'Akun Pengguna', 'Opsional. Isi username atau email akun aktif hanya bila pegawai perlu login/menyetujui dokumen. Kolom kosong tidak memutus akun yang sudah terhubung.'],
                    ['VALIDASI', 'Batas data', 'Maksimal 2.000 baris pegawai per file. Seluruh baris harus valid sebelum import dapat diterapkan.'],
                    ['VERSI', 'Template', 'Template Pegawai OPD E-SAKIP v2 · diunduh '.now()->timezone(config('app.timezone'))->format('d-m-Y H:i').' WIB.'],
                ],
                'widths' => [16, 28, 110],
                'tab_color' => '70AD47',
            ],
            'Referensi Jabatan' => [
                'rows' => $this->jabatanReferenceRows($opdId),
                'widths' => [48, 18, 52, 22, 42, 22, 20],
                'tab_color' => 'A5A5A5',
            ],
            'Referensi OPD & Unit' => [
                'rows' => $this->opdUnitReferenceRows($opdId),
                'widths' => [18, 52, 22, 52],
                'tab_color' => 'A5A5A5',
            ],
        ];
    }

    private function opdUnitReferenceRows(?int $opdId): array
    {
        $rows = [['Kode OPD', 'Nama Perangkat Daerah', 'Kode Unit', 'Nama Unit']];
        $opds = Opd::query()
            ->with(['units' => fn ($query) => $query->where('status', 'active')->orderBy('kode')->orderBy('nama')])
            ->where('status', 'active')
            ->when($opdId, fn ($query) => $query->whereKey($opdId))
            ->orderBy('kode')
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);

        foreach ($opds as $opd) {
            if ($opd->units->isEmpty()) {
                $rows[] = [$opd->kode, $opd->nama, null, 'Tidak memiliki unit aktif'];

                continue;
            }

            foreach ($opd->units as $unit) {
                $rows[] = [$opd->kode, $opd->nama, $unit->kode, $unit->nama];
            }
        }

        return $rows;
    }

    private function jabatanReferenceRows(?int $opdId): array
    {
        $rows = [['Nama Jabatan', 'Kode OPD', 'Nama Perangkat Daerah', 'Kode Unit', 'Nama Unit', 'Level', 'Status Verifikasi']];
        $jobs = JabatanOrganisasi::query()
            ->with(['opd:id,kode,nama', 'opdUnit:id,kode,nama'])
            ->where('status', 'active')
            ->whereIn('verification_status', ['verified', 'pending'])
            ->when($opdId, fn ($query) => $query->where('opd_id', $opdId))
            ->orderByRaw('CASE WHEN opd_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('opd_id')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        foreach ($jobs as $job) {
            $rows[] = [
                $job->nama,
                $job->opd?->kode,
                $job->opd?->nama ?? 'Pemerintah Kabupaten',
                $job->opdUnit?->kode,
                $job->opdUnit?->nama,
                JabatanOrganisasi::levelLabels()[$job->level_jabatan] ?? $job->level_jabatan,
                $job->verification_status === 'verified' ? 'Terverifikasi' : 'Menunggu verifikasi',
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array<int, array<int, string|int|float|null>>>
     */
    private function renstraSheets(): array
    {
        return [
            'Template Renstra' => [
                ['level', 'opd_kode', 'opd_nama', 'rpjmd_judul', 'renstra_judul', 'nomor_dokumen', 'tahun_awal', 'tahun_akhir', 'kode', 'uraian', 'sasaran_level', 'tahun_target', 'target', 'target_text', 'pagu', 'tipe_indikator', 'formula', 'sumber_data', 'tujuan_daerah_kode', 'indikator_tujuan_daerah_kode', 'sasaran_daerah_kode', 'indikator_sasaran_daerah_kode', 'program_rpjmd_kode', 'indikator_program_rpjmd_kode', 'keterangan'],
                ['renstra', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', 'RENSTRA/001/2025', 2025, 2029, 'REN-1', 'Renstra Dinas Contoh Tahun 2025-2029', null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'Baris identitas Renstra OPD.'],
                ['tujuan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'TO-1', 'Meningkatnya kualitas layanan OPD', null, null, null, null, null, null, null, null, 'T-1', null, null, null, null, null, null],
                ['indikator_tujuan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'ITO-1', 'Indeks layanan OPD', null, 2025, 80, '80 poin', null, 'positif', '(realisasi / target) x 100', 'Laporan OPD', null, 'IT-1', null, null, null, null, null],
                ['sasaran', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'SO-1', 'Meningkatnya capaian sasaran OPD', null, null, null, null, null, null, null, null, null, null, 'S-1', null, null, null, null],
                ['indikator_sasaran', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'ISO-1', 'Persentase capaian sasaran OPD', null, 2025, 85, '85 persen', null, 'positif', '(realisasi / target) x 100', 'Laporan OPD', null, null, null, 'IS-1', null, null, null],
                ['program', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'P-1', 'Program Penunjang Urusan Pemerintahan Daerah', 'Meningkatnya layanan penunjang perangkat daerah', null, null, null, 1000000000, null, null, null, null, null, null, null, 'PR-1', null, null],
                ['indikator_program', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'IP-1', 'Persentase layanan program terpenuhi', null, 2025, 90, '90 persen', 1000000000, 'positif', '(realisasi / target) x 100', 'Laporan OPD', null, null, null, null, null, 'IPR-1', null],
                ['kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'K-1', 'Administrasi umum perangkat daerah', 'Tersedianya layanan administrasi perangkat daerah', null, null, null, 600000000, null, null, null, null, null, null, null, null, null, null],
                ['indikator_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'IK-1', 'Persentase layanan kegiatan terpenuhi', null, 2025, 80, '80 persen', null, 'positif', '(realisasi / target) x 100', 'Laporan kegiatan', null, null, null, null, null, null, null],
                ['target_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, null, 'Target indikator kegiatan', null, 2026, 85, '85 persen', null, null, null, null, null, null, null, null, null, null, null],
                ['sub_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'SK-1', 'Penyediaan jasa penunjang administrasi', 'Tersedianya dokumen administrasi perangkat daerah', null, null, null, 300000000, null, null, null, null, null, null, null, null, null, null],
                ['indikator_sub_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, 'ISK-1', 'Jumlah dokumen administrasi selesai', null, 2025, 25, '25 dokumen', null, 'positif', '(realisasi / target) x 100', 'Laporan sub kegiatan', null, null, null, null, null, null, null],
                ['target_sub_kegiatan', '1.01', 'Dinas Contoh', 'RPJMD Kabupaten Banjarnegara Tahun 2025-2029', 'Renstra Dinas Contoh Tahun 2025-2029', null, 2025, 2029, null, 'Target indikator sub kegiatan', null, 2026, 30, '30 dokumen', null, null, null, null, null, null, null, null, null, null, null],
            ],
            'Petunjuk' => [
                ['Kolom', 'Keterangan'],
                ['level', 'Wajib. Nilai: renstra, tujuan, indikator_tujuan, target_tujuan, sasaran, indikator_sasaran, target_sasaran, program, indikator_program, target_program, kegiatan, indikator_kegiatan, target_kegiatan, sub_kegiatan, indikator_sub_kegiatan, target_sub_kegiatan.'],
                ['opd_kode', 'Wajib. Harus sama dengan kode OPD di master OPD.'],
                ['rpjmd_judul', 'Wajib jika tidak memakai rpjmd_id. Harus sama dengan judul RPJMD existing.'],
                ['tahun_target', 'Wajib untuk semua target tahunan. Tahun harus tersedia di master periode.'],
            ],
        ];
    }

    /** @param array<string, array<mixed>> $sheets */
    private function buildWorkbook(array $sheets): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive belum aktif, template .xlsx tidak bisa dibuat.');
        }

        $path = tempnam(sys_get_temp_dir(), 'sakip_template_');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File template sementara tidak bisa dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('docProps/core.xml', $this->coreProperties());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml(array_keys($sheets)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        $index = 1;
        foreach ($sheets as $definition) {
            $zip->addFromString("xl/worksheets/sheet{$index}.xml", $this->worksheetXml($this->normalizeSheetDefinition($definition)));
            $index++;
        }

        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        if (! is_string($content)) {
            throw new RuntimeException('Template .xlsx gagal dibaca.');
        }

        return $content;
    }

    /** @param array<mixed> $definition */
    private function normalizeSheetDefinition(array $definition): array
    {
        if (! array_key_exists('rows', $definition)) {
            $definition = ['rows' => $definition];
        }

        return [
            'rows' => $definition['rows'] ?? [],
            'required_columns' => $definition['required_columns'] ?? [],
            'widths' => $definition['widths'] ?? [],
            'blank_rows' => $definition['blank_rows'] ?? 0,
            'validations' => $definition['validations'] ?? [],
            'tab_color' => $definition['tab_color'] ?? null,
        ];
    }

    private function worksheetXml(array $definition): string
    {
        $rows = $definition['rows'];
        $blankRows = max(0, (int) $definition['blank_rows']);
        $columnCount = max(1, count($rows[0] ?? []));
        $lastRow = max(1, count($rows) + $blankRows);
        $lastColumn = $this->columnName($columnCount);
        $columns = '';

        foreach ($definition['widths'] as $index => $width) {
            $column = $index + 1;
            $columns .= '<col min="'.$column.'" max="'.$column.'" width="'.max(8, min(120, (float) $width)).'" customWidth="1"/>';
        }

        $tabColor = $definition['tab_color']
            ? '<sheetPr><tabColor rgb="FF'.$this->escapeAttribute($definition['tab_color']).'"/></sheetPr>'
            : '';
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$tabColor
            .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .($columns !== '' ? '<cols>'.$columns.'</cols>' : '')
            .'<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="'.$excelRow.'"'.($rowIndex === 0 ? ' ht="34" customHeight="1"' : '').'>';

            foreach ($row as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1).$excelRow;
                $style = $rowIndex === 0
                    ? (in_array($columnIndex, $definition['required_columns'], true) ? 1 : 2)
                    : (count($rows) > 1 ? 4 : 3);
                $xml .= '<c r="'.$cell.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        for ($rowIndex = count($rows) + 1; $rowIndex <= $lastRow; $rowIndex++) {
            $xml .= '<row r="'.$rowIndex.'">';
            for ($columnIndex = 1; $columnIndex <= $columnCount; $columnIndex++) {
                $cell = $this->columnName($columnIndex).$rowIndex;
                $xml .= '<c r="'.$cell.'" s="3" t="inlineStr"><is><t></t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        if ($blankRows > 0) {
            $xml .= '<autoFilter ref="A1:'.$lastColumn.$lastRow.'"/>';
        }

        if ($definition['validations'] !== []) {
            $validations = '';
            foreach ($definition['validations'] as $validation) {
                $column = $this->columnName(((int) $validation['column']) + 1);
                $values = implode(',', $validation['values']);
                $validations .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorStyle="stop"'
                    .' errorTitle="Nilai tidak dikenali" error="Pilih nilai yang tersedia pada daftar."'
                    .' promptTitle="'.$this->escapeAttribute($validation['title']).'" prompt="'.$this->escapeAttribute($validation['message']).'"'
                    .' sqref="'.$column.'2:'.$column.$lastRow.'"><formula1>&quot;'.$this->escape($values).'&quot;</formula1></dataValidation>';
            }
            $xml .= '<dataValidations count="'.count($definition['validations']).'">'.$validations.'</dataValidations>';
        }

        return $xml.'<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>';
    }

    /**
     * @param  array<int, string>  $sheetNames
     */
    private function workbookXml(array $sheetNames): string
    {
        $sheets = '';

        foreach ($sheetNames as $index => $name) {
            $sheetId = $index + 1;
            $sheets .= '<sheet name="'.$this->escapeAttribute($name).'" sheetId="'.$sheetId.'" r:id="rId'.$sheetId.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }

        $relationships .= '<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '';

        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Aptos"/><family val="2"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Aptos Display"/><family val="2"/></font></fonts>'
            .'<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B4A82"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF52738F"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF3F7FA"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD7E0E8"/></left><right style="thin"><color rgb="FFD7E0E8"/></right><top style="thin"><color rgb="FFD7E0E8"/></top><bottom style="thin"><color rgb="FFD7E0E8"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>E-SAKIP Kabupaten Banjarnegara</Application></Properties>';
    }

    private function coreProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>E-SAKIP Kabupaten Banjarnegara</dc:creator>'
            .'<dc:title>Template Import E-SAKIP</dc:title>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.now()->toISOString().'</dcterms:created>'
            .'</cp:coreProperties>';
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
