<?php

namespace App\Support;

class SystemSettingCatalog
{
    public static function groups(): array
    {
        return [
            'identitas_aplikasi' => [
                'label' => 'Identitas Aplikasi',
                'description' => 'Nama aplikasi, instansi pengelola, kontak resmi, dan teks footer.',
            ],
            'siklus_sakip' => [
                'label' => 'Siklus SAKIP',
                'description' => 'Tahun aktif, periode realisasi, triwulan berjalan, dan aturan kunci data.',
            ],
            'rpjmd' => [
                'label' => 'Struktur RPJMD',
                'description' => 'Pola struktur tujuan dan sasaran RPJMD yang ditetapkan super admin.',
            ],
            'publik' => [
                'label' => 'Portal Publik',
                'description' => 'Kontrol tampilan data yang dibuka untuk masyarakat.',
            ],
            'dokumen' => [
                'label' => 'Dokumen',
                'description' => 'Batas unggah, tipe file, disk storage, dan aturan publikasi dokumen.',
            ],
            'workflow' => [
                'label' => 'Workflow',
                'description' => 'Aturan pengajuan, review, revisi, approval, dan penguncian data.',
            ],
            'dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Cache, ambang warna capaian, ranking OPD, dan data monitoring.',
            ],
            'evaluasi' => [
                'label' => 'Evaluasi SAKIP',
                'description' => 'Bobot awal, predikat, catatan evaluator, dan aturan bukti evaluasi.',
            ],
            'pelaporan' => [
                'label' => 'Pelaporan LKJIP',
                'description' => 'Template laporan, proses generate, penandatangan, dan export dokumen.',
            ],
            'notifikasi' => [
                'label' => 'Notifikasi',
                'description' => 'Pengiriman notifikasi aplikasi, badge unread, dan email.',
            ],
            'integrasi' => [
                'label' => 'Integrasi',
                'description' => 'Pengaturan awal untuk integrasi SIPD/API dan layanan eksternal.',
            ],
            'keamanan' => [
                'label' => 'Keamanan',
                'description' => 'Sesi login, batas percobaan login, panjang password, dan mode perawatan.',
            ],
        ];
    }

    public static function settings(): array
    {
        $year = (int) date('Y');

        return [
            'app.name' => [
                'group' => 'identitas_aplikasi',
                'label' => 'Nama Aplikasi',
                'type' => 'string',
                'value' => config('app.name', 'E-SAKIP Kabupaten Banjarnegara'),
                'is_public' => true,
                'description' => 'Nama resmi aplikasi yang tampil di login, dashboard, dan portal publik.',
                'placeholder' => 'E-SAKIP Kabupaten Banjarnegara',
            ],
            'app.tagline' => [
                'group' => 'identitas_aplikasi',
                'label' => 'Tagline Aplikasi',
                'type' => 'string',
                'value' => 'Sistem Akuntabilitas Kinerja Instansi Pemerintah',
                'is_public' => true,
                'description' => 'Kalimat pendek untuk menjelaskan tujuan aplikasi.',
                'placeholder' => 'Sistem Akuntabilitas Kinerja Instansi Pemerintah',
            ],
            'app.instansi_pengelola' => [
                'group' => 'identitas_aplikasi',
                'label' => 'Instansi Pengelola',
                'type' => 'string',
                'value' => 'Dinas Komunikasi dan Informatika Kabupaten Banjarnegara',
                'is_public' => true,
                'description' => 'Instansi yang ditampilkan sebagai pengelola sistem.',
                'placeholder' => 'Dinas Komunikasi dan Informatika Kabupaten Banjarnegara',
            ],
            'app.kabupaten' => [
                'group' => 'identitas_aplikasi',
                'label' => 'Nama Kabupaten',
                'type' => 'string',
                'value' => 'Kabupaten Banjarnegara',
                'is_public' => true,
                'description' => 'Nama daerah untuk header, laporan, dan portal publik.',
                'placeholder' => 'Kabupaten Banjarnegara',
            ],
            'app.alamat_instansi' => [
                'group' => 'identitas_aplikasi',
                'label' => 'Alamat Instansi',
                'type' => 'text',
                'value' => 'Banjarnegara, Jawa Tengah',
                'is_public' => true,
                'description' => 'Alamat singkat instansi pengelola atau pemerintah daerah.',
                'placeholder' => 'Jl. ... Banjarnegara',
            ],
            'app.email_instansi' => [
                'group' => 'identitas_aplikasi',
                'label' => 'Email Instansi',
                'type' => 'string',
                'value' => 'diskominfo@banjarnegarakab.go.id',
                'is_public' => true,
                'description' => 'Email kontak yang dapat ditampilkan di portal publik.',
                'placeholder' => 'diskominfo@banjarnegarakab.go.id',
            ],
            'app.website_instansi' => [
                'group' => 'identitas_aplikasi',
                'label' => 'Website Instansi',
                'type' => 'string',
                'value' => 'https://banjarnegarakab.go.id',
                'is_public' => true,
                'description' => 'Alamat website resmi pemerintah daerah atau instansi pengelola.',
                'placeholder' => 'https://banjarnegarakab.go.id',
            ],
            'app.footer_text' => [
                'group' => 'identitas_aplikasi',
                'label' => 'Teks Copyright',
                'type' => 'string',
                'value' => 'Dinas Komunikasi dan Informatika Kabupaten Banjarnegara',
                'is_public' => true,
                'description' => 'Teks copyright di portal publik dan halaman admin.',
                'placeholder' => 'Dinas Komunikasi dan Informatika Kabupaten Banjarnegara',
            ],
            'sakip.tahun_default' => [
                'group' => 'siklus_sakip',
                'label' => 'Tahun Default',
                'type' => 'integer',
                'value' => $year,
                'is_public' => true,
                'description' => 'Tahun awal yang otomatis dipilih pada filter dashboard dan portal publik.',
                'placeholder' => (string) $year,
            ],
            'sakip.periode_realisasi_default' => [
                'group' => 'siklus_sakip',
                'label' => 'Periode Realisasi Default',
                'type' => 'string',
                'value' => 'triwulan',
                'is_public' => false,
                'description' => 'Periode input realisasi bawaan: bulanan, triwulan, semester, atau tahunan.',
                'placeholder' => 'triwulan',
            ],
            'sakip.triwulan_aktif' => [
                'group' => 'siklus_sakip',
                'label' => 'Triwulan Aktif',
                'type' => 'string',
                'value' => 'tw1',
                'is_public' => true,
                'description' => 'Triwulan berjalan yang dipakai untuk filter cepat dan dashboard.',
                'placeholder' => 'tw1',
            ],
            'sakip.lock_target_approved' => [
                'group' => 'siklus_sakip',
                'label' => 'Kunci Target Approved',
                'type' => 'boolean',
                'value' => true,
                'is_public' => false,
                'description' => 'Jika aktif, target yang sudah approved tidak dapat diedit tanpa revisi resmi.',
                'placeholder' => '1',
            ],
            'sakip.require_approved_target_before_realisasi' => [
                'group' => 'siklus_sakip',
                'label' => 'Realisasi Harus dari Target Approved',
                'type' => 'boolean',
                'value' => true,
                'is_public' => false,
                'description' => 'Mencegah input realisasi sebelum target kinerja disetujui.',
                'placeholder' => '1',
            ],
            'rpjmd.default_struktur_tujuan_mode' => [
                'group' => 'rpjmd',
                'label' => 'Pola Tujuan Default',
                'type' => 'string',
                'value' => 'tujuan_lintas_misi',
                'is_public' => false,
                'description' => 'Pola tujuan yang otomatis dipakai saat admin membuat RPJMD baru.',
                'placeholder' => 'tujuan_lintas_misi',
                'options' => [
                    ['value' => 'tujuan_lintas_misi', 'label' => 'Tujuan lintas misi'],
                    ['value' => 'tujuan_per_misi', 'label' => 'Tujuan per misi'],
                ],
                'super_admin_only' => true,
            ],
            'rpjmd.default_struktur_sasaran_mode' => [
                'group' => 'rpjmd',
                'label' => 'Pola Sasaran Default',
                'type' => 'string',
                'value' => 'sasaran_langsung_tujuan',
                'is_public' => false,
                'description' => 'Pola sasaran yang otomatis dipakai saat admin membuat RPJMD baru.',
                'placeholder' => 'sasaran_langsung_tujuan',
                'options' => [
                    ['value' => 'sasaran_langsung_tujuan', 'label' => 'Sasaran langsung ke tujuan'],
                    ['value' => 'sasaran_melalui_indikator_tujuan', 'label' => 'Sasaran melalui indikator tujuan'],
                    ['value' => 'campuran', 'label' => 'Campuran'],
                ],
                'super_admin_only' => true,
            ],
            'publik.portal_enabled' => [
                'group' => 'publik',
                'label' => 'Aktifkan Portal Publik',
                'type' => 'boolean',
                'value' => true,
                'is_public' => true,
                'description' => 'Mengatur apakah landing page dan data publik dapat diakses masyarakat.',
                'placeholder' => '1',
            ],
            'publik.show_download_button' => [
                'group' => 'publik',
                'label' => 'Tampilkan Tombol Download',
                'type' => 'boolean',
                'value' => true,
                'is_public' => true,
                'description' => 'Menampilkan tombol unduh dokumen pada tabel publik.',
                'placeholder' => '1',
            ],
            'publik.show_evaluation_score' => [
                'group' => 'publik',
                'label' => 'Tampilkan Nilai SAKIP',
                'type' => 'boolean',
                'value' => true,
                'is_public' => true,
                'description' => 'Menampilkan nilai SAKIP OPD pada menu evaluasi publik.',
                'placeholder' => '1',
            ],
            'publik.require_document_approval' => [
                'group' => 'publik',
                'label' => 'Dokumen Publik Harus Disetujui',
                'type' => 'boolean',
                'value' => true,
                'is_public' => false,
                'description' => 'Dokumen baru tampil di portal publik setelah status publikasinya disetujui.',
                'placeholder' => '1',
            ],
            'dokumen.max_upload_mb' => [
                'group' => 'dokumen',
                'label' => 'Maksimal Upload per File (MB)',
                'type' => 'integer',
                'value' => 20,
                'is_public' => false,
                'description' => 'Batas ukuran unggah dokumen dan bukti dukung per file.',
                'placeholder' => '20',
            ],
            'dokumen.allowed_mime_types' => [
                'group' => 'dokumen',
                'label' => 'Tipe File Diizinkan',
                'type' => 'json',
                'value' => ['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                'is_public' => false,
                'description' => 'Daftar MIME type yang boleh diunggah.',
                'placeholder' => '["application/pdf", "image/jpeg", "image/png"]',
            ],
            'dokumen.storage_disk' => [
                'group' => 'dokumen',
                'label' => 'Storage Disk Dokumen',
                'type' => 'string',
                'value' => config('filesystems.default', 'local'),
                'is_public' => false,
                'description' => 'Disk Laravel Filesystem untuk penyimpanan dokumen.',
                'placeholder' => 'local',
            ],
            'dokumen.hash_required' => [
                'group' => 'dokumen',
                'label' => 'Wajib Simpan Hash File',
                'type' => 'boolean',
                'value' => true,
                'is_public' => false,
                'description' => 'Menandai dokumen harus punya hash untuk deteksi perubahan file.',
                'placeholder' => '1',
            ],
            'workflow.auto_assign_reviewer' => [
                'group' => 'workflow',
                'label' => 'Auto Assign Reviewer',
                'type' => 'boolean',
                'value' => false,
                'is_public' => false,
                'description' => 'Jika aktif, sistem mencoba memilih reviewer otomatis saat data diajukan.',
                'placeholder' => '0',
            ],
            'workflow.default_revision_days' => [
                'group' => 'workflow',
                'label' => 'Batas Waktu Revisi (Hari)',
                'type' => 'integer',
                'value' => 7,
                'is_public' => false,
                'description' => 'Jumlah hari rekomendasi penyelesaian revisi setelah reviewer meminta perbaikan.',
                'placeholder' => '7',
            ],
            'workflow.allow_super_admin_unlock' => [
                'group' => 'workflow',
                'label' => 'Super Admin Bisa Unlock Data',
                'type' => 'boolean',
                'value' => true,
                'is_public' => false,
                'description' => 'Memberi akses pengecualian untuk membuka data terkunci oleh super admin.',
                'placeholder' => '1',
            ],
            'workflow.notify_reviewer_on_submit' => [
                'group' => 'workflow',
                'label' => 'Notifikasi Reviewer Saat Submit',
                'type' => 'boolean',
                'value' => true,
                'is_public' => false,
                'description' => 'Membuat notifikasi untuk reviewer saat OPD mengajukan data.',
                'placeholder' => '1',
            ],
            'dashboard.cache_ttl_seconds' => [
                'group' => 'dashboard',
                'label' => 'TTL Cache Dashboard (Detik)',
                'type' => 'integer',
                'value' => 300,
                'is_public' => false,
                'description' => 'Durasi cache untuk query dashboard yang berat.',
                'placeholder' => '300',
            ],
            'dashboard.ranking_limit' => [
                'group' => 'dashboard',
                'label' => 'Jumlah Ranking OPD',
                'type' => 'integer',
                'value' => 10,
                'is_public' => false,
                'description' => 'Jumlah OPD yang ditampilkan pada ranking dashboard.',
                'placeholder' => '10',
            ],
            'dashboard.status_merah_below' => [
                'group' => 'dashboard',
                'label' => 'Ambang Status Merah',
                'type' => 'integer',
                'value' => 70,
                'is_public' => false,
                'description' => 'Capaian di bawah angka ini masuk status merah.',
                'placeholder' => '70',
            ],
            'dashboard.status_hijau_above' => [
                'group' => 'dashboard',
                'label' => 'Ambang Status Hijau',
                'type' => 'integer',
                'value' => 90,
                'is_public' => false,
                'description' => 'Capaian di atas angka ini masuk status hijau.',
                'placeholder' => '90',
            ],
            'evaluasi.bobot_default' => [
                'group' => 'evaluasi',
                'label' => 'Bobot Evaluasi Default',
                'type' => 'json',
                'value' => [
                    'perencanaan' => 30,
                    'pengukuran' => 30,
                    'pelaporan' => 15,
                    'evaluasi_internal' => 25,
                ],
                'is_public' => false,
                'description' => 'Bobot awal evaluasi SAKIP. Detail operasional tetap mengikuti tabel komponen evaluasi.',
                'placeholder' => '{"perencanaan":30,"pengukuran":30,"pelaporan":15,"evaluasi_internal":25}',
            ],
            'evaluasi.show_internal_notes_to_opd' => [
                'group' => 'evaluasi',
                'label' => 'Catatan Internal Tampil ke OPD',
                'type' => 'boolean',
                'value' => false,
                'is_public' => false,
                'description' => 'Mengatur apakah catatan internal evaluator dapat dibaca oleh OPD.',
                'placeholder' => '0',
            ],
            'evaluasi.minimum_evidence_required' => [
                'group' => 'evaluasi',
                'label' => 'Minimal Bukti Evaluasi',
                'type' => 'integer',
                'value' => 1,
                'is_public' => false,
                'description' => 'Jumlah minimal bukti dukung untuk menandai kriteria evaluasi lengkap.',
                'placeholder' => '1',
            ],
            'pelaporan.lkjip_generate_with_queue' => [
                'group' => 'pelaporan',
                'label' => 'Generate LKJIP via Queue',
                'type' => 'boolean',
                'value' => true,
                'is_public' => false,
                'description' => 'Laporan besar diproses lewat queue agar request browser tidak berat.',
                'placeholder' => '1',
            ],
            'pelaporan.template_lkjip' => [
                'group' => 'pelaporan',
                'label' => 'Template LKJIP Default',
                'type' => 'string',
                'value' => 'resmi_pemkab',
                'is_public' => false,
                'description' => 'Kode template yang dipakai saat generate LKJIP.',
                'placeholder' => 'resmi_pemkab',
            ],
            'pelaporan.penandatangan_jabatan' => [
                'group' => 'pelaporan',
                'label' => 'Jabatan Penandatangan',
                'type' => 'string',
                'value' => 'Kepala Perangkat Daerah',
                'is_public' => false,
                'description' => 'Jabatan bawaan untuk blok tanda tangan dokumen.',
                'placeholder' => 'Kepala Perangkat Daerah',
            ],
            'notifikasi.email_enabled' => [
                'group' => 'notifikasi',
                'label' => 'Aktifkan Email',
                'type' => 'boolean',
                'value' => false,
                'is_public' => false,
                'description' => 'Mengirim notifikasi email selain notifikasi dalam aplikasi.',
                'placeholder' => '0',
            ],
            'notifikasi.unread_badge_enabled' => [
                'group' => 'notifikasi',
                'label' => 'Badge Notifikasi Belum Dibaca',
                'type' => 'boolean',
                'value' => true,
                'is_public' => false,
                'description' => 'Menampilkan badge jumlah notifikasi belum dibaca di sidebar/topbar.',
                'placeholder' => '1',
            ],
            'notifikasi.sender_name' => [
                'group' => 'notifikasi',
                'label' => 'Nama Pengirim Email',
                'type' => 'string',
                'value' => config('mail.from.name', 'E-SAKIP Banjarnegara'),
                'is_public' => false,
                'description' => 'Nama pengirim saat email notifikasi aktif.',
                'placeholder' => 'E-SAKIP Banjarnegara',
            ],
            'notifikasi.sender_email' => [
                'group' => 'notifikasi',
                'label' => 'Email Pengirim',
                'type' => 'string',
                'value' => config('mail.from.address', 'noreply@example.test'),
                'is_public' => false,
                'description' => 'Alamat email pengirim notifikasi.',
                'placeholder' => 'noreply@example.test',
            ],
            'integrasi.sipd_enabled' => [
                'group' => 'integrasi',
                'label' => 'Aktifkan Integrasi SIPD',
                'type' => 'boolean',
                'value' => false,
                'is_public' => false,
                'description' => 'Flag awal untuk integrasi SIPD/API di masa depan.',
                'placeholder' => '0',
            ],
            'integrasi.sipd_base_url' => [
                'group' => 'integrasi',
                'label' => 'Base URL SIPD',
                'type' => 'string',
                'value' => '',
                'is_public' => false,
                'description' => 'URL dasar layanan SIPD/API jika integrasi sudah tersedia.',
                'placeholder' => 'https://...',
            ],
            'integrasi.sipd_timeout_seconds' => [
                'group' => 'integrasi',
                'label' => 'Timeout SIPD (Detik)',
                'type' => 'integer',
                'value' => 15,
                'is_public' => false,
                'description' => 'Batas waktu request ke layanan integrasi eksternal.',
                'placeholder' => '15',
            ],
            'keamanan.session_lifetime_minutes' => [
                'group' => 'keamanan',
                'label' => 'Durasi Sesi Login (Menit)',
                'type' => 'integer',
                'value' => (int) config('session.lifetime', 120),
                'is_public' => false,
                'description' => 'Referensi durasi sesi login aplikasi.',
                'placeholder' => '120',
            ],
            'keamanan.login_max_attempts' => [
                'group' => 'keamanan',
                'label' => 'Maksimal Percobaan Login',
                'type' => 'integer',
                'value' => 5,
                'is_public' => false,
                'description' => 'Batas percobaan login sebelum throttling aktif.',
                'placeholder' => '5',
            ],
            'keamanan.password_min_length' => [
                'group' => 'keamanan',
                'label' => 'Panjang Minimal Password',
                'type' => 'integer',
                'value' => 8,
                'is_public' => false,
                'description' => 'Panjang minimal password untuk kebijakan user baru atau reset password.',
                'placeholder' => '8',
            ],
            'keamanan.maintenance_mode_message' => [
                'group' => 'keamanan',
                'label' => 'Pesan Mode Perawatan',
                'type' => 'text',
                'value' => 'Aplikasi sedang dalam pemeliharaan terjadwal.',
                'is_public' => true,
                'description' => 'Pesan yang dapat ditampilkan saat aplikasi sedang perawatan.',
                'placeholder' => 'Aplikasi sedang dalam pemeliharaan terjadwal.',
            ],
        ];
    }

    public static function group(string $group): ?array
    {
        return self::groups()[$group] ?? null;
    }

    public static function setting(string $key): ?array
    {
        return self::settings()[$key] ?? null;
    }

    public static function superAdminOnlyKeys(): array
    {
        return collect(self::settings())
            ->filter(fn (array $setting) => (bool) ($setting['super_admin_only'] ?? false))
            ->keys()
            ->values()
            ->all();
    }

    public static function groupOptions(): array
    {
        return collect(self::groups())
            ->map(fn (array $meta, string $group) => [
                'value' => $group,
                'label' => $meta['label'],
                'description' => $meta['description'],
            ])
            ->values()
            ->all();
    }

    public static function typeOptions(): array
    {
        return [
            ['value' => 'string', 'label' => 'String', 'description' => 'Teks pendek seperti nama, URL, atau kode.'],
            ['value' => 'text', 'label' => 'Teks Panjang', 'description' => 'Narasi atau alamat yang dapat lebih dari satu baris.'],
            ['value' => 'integer', 'label' => 'Angka', 'description' => 'Bilangan bulat seperti tahun, batas upload, atau TTL cache.'],
            ['value' => 'boolean', 'label' => 'Ya/Tidak', 'description' => 'Nilai aktif atau tidak aktif.'],
            ['value' => 'json', 'label' => 'JSON', 'description' => 'Struktur data daftar atau objek konfigurasi.'],
        ];
    }
}
