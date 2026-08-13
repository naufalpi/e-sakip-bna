# Project State - E-SAKIP Kabupaten Banjarnegara

Dokumen ini adalah ringkasan handoff agar pekerjaan bisa dilanjutkan di chat baru tanpa membawa seluruh riwayat percakapan lama.

## Lokasi Project

- Stack:
    - Backend: Laravel 13
    - Frontend: Inertia.js + Vue 3 + TypeScript bila memungkinkan
    - Database: PostgreSQL
    - Cache/queue target: Redis
    - UI: Tailwind, component-based, formal, responsif
    - Auth: session Laravel
    - Authorization: custom role/permission database

## Aturan Kerja Penting

- Selalu jalankan command dengan prefix `rtk`.
- Jangan revert perubahan user atau perubahan lama yang tidak jelas asalnya.
- Gunakan `apply_patch` untuk edit manual file.
- Untuk pencarian file/teks, gunakan `rg` lebih dulu.
- Fokus hemat token:
    - baca file seperlunya,
    - patch langsung pada file relevan,
    - verifikasi secukupnya,
    - laporan akhir singkat.
- Jangan gunakan Filament atau Livewire.
- Semua halaman utama admin memakai Inertia + Vue.
- Storage dokumen harus lewat Laravel Storage, bukan direct `public/uploads`.

## Akun Seed Utama

- Super Admin:
    - username: `superadmin`
    - email: `admin@example.test`
    - password: `password`
- Seeder juga sudah dibuat untuk role kabupaten, pimpinan, admin OPD, dan akun OPD/unit.

## Modul Yang Sudah Ada

### Auth, Role, Permission

- Tabel custom `roles`, `permissions`, `role_user`, `permission_role`.
- Helper `User`:
    - `hasRole()`
    - `hasPermission()`
    - `isSuperAdmin()`
- Middleware role sederhana.
- Role Permission UI hanya untuk super admin dan admin kabupaten Dinkominfo.

### Master Data

- Master OPD dan unit OPD digabung dalam menu `Master OPD`.
- User sudah bisa dikaitkan ke `opd_id` dan `opd_unit_id`.
- Master periode tahun.
- Master satuan indikator.
- Master strategi daerah.
- Master urusan pemerintahan dan bidang urusan.
- Master program/kegiatan/sub kegiatan.
- Sub kegiatan sudah ditambah metadata:
    - sasaran sub kegiatan,
    - indikator sub kegiatan,
    - satuan,
    - definisi operasional.

### RPJMD Kabupaten

- Struktur RPJMD mendukung pola dinamis:
    - pola tujuan lintas misi,
    - pola sasaran langsung ke tujuan,
    - dan pola umum untuk periode berikutnya.
- Untuk Banjarnegara 2025-2029:
    - 1 visi,
    - 5 misi,
    - 1 tujuan lintas misi,
    - sasaran langsung ke tujuan.
- Input RPJMD memakai mode tabel/bulk, bukan CRUD datar.
- Jenis data RPJMD meliputi:
    - visi,
    - misi,
    - tujuan daerah,
    - indikator tujuan,
    - target indikator tujuan,
    - sasaran daerah,
    - indikator sasaran,
    - target indikator sasaran,
    - program RPJMD,
    - pagu program RPJMD,
    - indikator program,
    - target indikator program.
- Strategi daerah dipindah menjadi master, bukan node RPJMD.
- OPD penanggung jawab program RPJMD sekarang diturunkan otomatis dari PD pengampu bidang urusan, tetapi tetap bisa manual jika perlu.
- Program penunjang yang berlaku untuk semua OPD ditangani khusus.
- Preview tabel RPJMD sudah mendekati format resmi dan ada export Excel.
- Import Excel RPJMD sedang disembunyikan.

### Versi Dokumen RPJMD

- RPJMD memiliki versi:
    - `Murni`
    - `Perubahan I`, `Perubahan II`, dst.
- Perubahan RPJMD dibuat tanpa menghapus data murni.
- Dokumen murni menjadi arsip sementara ketika perubahan dibuat.
- Aksi `Batalkan Perubahan` sudah dirancang/dikerjakan untuk mengaktifkan kembali dokumen sebelumnya jika perubahan masih draft/revisi/ditolak.
- Perlu dicek ulang setelah perubahan terakhir jika ada edge case.

### Renstra OPD

- Renstra 5 tahunan, terkait OPD dan RPJMD.
- Input Renstra sudah dipisah menjadi area:
    - tujuan OPD,
    - sasaran OPD,
    - program OPD,
    - kegiatan OPD,
    - sub kegiatan OPD.
- Tampilan input memakai panel/section, modal, dan grouping.
- Program di Renstra mengambil program relevan dari RPJMD untuk OPD terkait.
- Program penunjang dipetakan ke kode program master sesuai OPD, bukan selalu kode pertama.
- Ketika memilih program RPJMD, indikator program dan targetnya disalin menjadi snapshot Renstra.
- Kegiatan dan sub kegiatan wajib dari master, tidak boleh input manual oleh admin OPD.
- Sub kegiatan saat dipilih akan membawa metadata master sebagai snapshot dan masih bisa diedit.
- Anggaran hanya diinput pada level sub kegiatan.
- Anggaran kegiatan dan program seharusnya menjadi hasil agregasi dari bawah.
- Preview tabel Renstra dibuat seperti format resmi:
    - bidang urusan,
    - program,
    - kegiatan,
    - sub kegiatan,
    - indikator,
    - baseline,
    - target,
    - pagu indikatif.
- Preview tree Renstra sedang disembunyikan.

### Versi Dokumen Renstra

- Renstra memiliki versi:
    - `Murni`
    - `Perubahan I`, `Perubahan II`, dst.
- Perubahan Renstra mengikuti pola RPJMD.
- Ada fitur `Batalkan Perubahan` untuk versi belum resmi.
- Pernah ada error karena kolom versi belum termigrate; jika muncul, jalankan migration terbaru.

### RKPD Kabupaten

- RKPD tahunan, berisi data final yang diinput Bapperida/admin kabupaten.
- RKPD bukan proses usulan OPD untuk tahap sekarang.
- RKPD punya tab:
    - IKU Kabupaten,
    - Baris RKPD,
    - Preview Tabel.
- IKU Kabupaten mengambil indikator tujuan daerah dan indikator sasaran daerah dari RPJMD.
- Target RPJMD ditampilkan otomatis, target RKPD diinput.
- Baris RKPD mengikuti format tabel resmi:
    - OPD,
    - urusan/bidang/program/kegiatan/sub kegiatan,
    - indikator,
    - target akhir Renstra,
    - realisasi,
    - prakiraan capaian,
    - target,
    - pagu,
    - lokasi,
    - sumber dana,
    - prioritas nasional/daerah,
    - kelompok sasaran,
    - prakiraan maju,
    - PD penanggung jawab.
- Form input RKPD dibuat menurun satu kolom.
- Alur pilihan:
    - pilih OPD,
    - pilih program sesuai OPD,
    - pilih kegiatan sesuai program,
    - pilih sub kegiatan sesuai kegiatan.
- Indikator sub kegiatan otomatis dari master.
- Sinkronisasi/tarik data RKPD dan Renja sudah mulai dibuat dengan tabel `planning_sync_batches` dan `planning_sync_batch_rows`.

### Renja OPD

- Renja tahunan.
- Tahun Renja menjadi input utama; periode tahun mengikuti otomatis.
- Judul dan nomor dokumen dibuat uppercase.
- Status awal otomatis draft.
- Form dan preview Renja disesuaikan agar mirip RKPD.
- Program yang muncul harus sesuai Renja OPD terkait, termasuk program penunjang khusus OPD.

### Dokumen Publik

- Modul dokumen dan bukti dukung sudah ada.
- File disimpan lewat Laravel Storage.
- Public site menampilkan dokumen perencanaan, pengukuran, pelaporan, evaluasi.
- Dokumen yang dihapus harus ikut hilang dari public site.
- Dokumen kabupaten di public site untuk perencanaan:
    - RPJMD,
    - RKPD.

### Workflow / Persetujuan

- Istilah UI diganti menjadi bahasa user biasa:
    - `Pengajuan Masuk`,
    - `Ajukan`,
    - `Setujui`,
    - `Tolak`,
    - `Minta Perbaikan`,
    - `Kunci`,
    - `Buka Kunci`.
- Tabel:
    - `workflow_submissions`
    - `workflow_histories`
    - `notifications`
    - `activity_logs`
- Service:
    - `SubmitWorkflowService`
    - `ApproveWorkflowService`
    - `RejectWorkflowService`
    - `RequestRevisionWorkflowService`
    - `LockDataService`
    - `ActivityLogService`
- Workflow berlaku/direncanakan untuk:
    - RPJMD,
    - RKPD,
    - Renstra,
    - Renja,
    - PK,
    - Rencana Aksi,
    - Realisasi,
    - LKJIP,
    - Evaluasi.

### Public Site

- Landing page public sudah dibuat.
- Warna utama public site: putih dan biru `#00336C`.
- Hero memakai animasi jaringan/nodes/triangles ringan dan interaktif.
- Landing telah direfactor menjadi beberapa komponen, bukan satu file besar.
- Navbar public:
    - Beranda,
    - Perencanaan,
    - Pengukuran,
    - Pelaporan,
    - Evaluasi,
    - Login/Dashboard.
- Title beranda public: `E-SAKIP Kabupaten Banjarnegara`.
- Copyright Diskominfo Kabupaten Banjarnegara sudah ditambahkan.

### Dashboard Admin

- Dashboard admin sudah dibuat ulang beberapa kali.
- Style terakhir mengikuti dashboard modern dengan card, chart, ranking, pagination, animasi angka/kurva.
- Masih ada pekerjaan terakhir yang belum selesai:
    - dark mode dashboard belum sepenuhnya konsisten,
    - topbar/header admin harus benar-benar sticky/frozen,
    - area konten admin harus memakai scroll browser/window, bukan scroll container internal.

## Seeder Penting

Seeder sudah dibuat/diubah untuk:

- roles dan permissions,
- user awal,
- OPD dan unit OPD,
- akun OPD/unit,
- periode tahun,
- satuan indikator,
- urusan dan bidang urusan,
- strategi daerah,
- program/kegiatan/sub kegiatan,
- sub kegiatan lengkap dari Excel,
- RPJMD kosong 2025-2029.

Catatan:

- Jika reset total lokal: biasanya gunakan `php artisan migrate:fresh --seed`.
- Untuk production uji coba, reset total hanya jika memang data boleh hilang.

## Command Development Lokal

Umum:

```bash
composer install
npm install
php artisan migrate --seed
php artisan serve
npm run dev
```

Jika Redis belum jalan di lokal, gunakan `.env`:

```env
CACHE_STORE=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
```

Login:

```text
admin@example.test / password
```

## Command Production Uji Coba

Setelah `git pull`:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika reset total production uji coba:

```bash
php artisan migrate:fresh --seed --force
npm ci
npm run build
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Production domain uji coba:

```text
https://esakip-dev.banjarnegarakab.go.id
```

Catatan production:

- Pastikan CLI PHP memakai PHP 8.3+, bukan PHP 8.1.
- Pastikan extension PHP sesuai versi:
    - pdo_pgsql,
    - pgsql,
    - mbstring,
    - fileinfo,
    - zip.
- Mixed content pernah terjadi; pastikan `APP_URL` HTTPS dan trusted proxy/force scheme sudah benar.

## Known Issues / Pekerjaan Tertunda

Prioritas dekat:

1. Perbaiki dashboard admin:

    - dark mode penuh,
    - header admin sticky tanpa gerakan,
    - konten admin memakai scroll window/browser.

2. Cek ulang fitur `Batalkan Perubahan` RPJMD/Renstra:

    - perubahan draft dibatalkan,
    - dokumen murni aktif kembali,
    - tidak boleh batalkan versi disetujui/terkunci,
    - nomor perubahan berikutnya tidak bentrok walau perubahan sebelumnya soft deleted.

3. Rapikan Renstra:

    - grouping program/kegiatan/sub kegiatan,
    - smooth scroll ke detail kegiatan/sub kegiatan,
    - modal dropdown tidak tertutup header,
    - preview tabel Renstra dan export Excel.

4. Rapikan RKPD:

    - preview tabel harus grouping OPD rapi,
    - OPD muncul sekali lalu seluruh baris di bawahnya,
    - export Excel format resmi.

5. Lanjut desain perubahan dokumen tahunan:

    - RKPD Awal/Final,
    - Renja Awal/Final,
    - RKA,
    - DPA,
    - PK,
    - Rencana Aksi.

6. Kuatkan sinkronisasi RKPD <-> Renja:
    - tarik Renja ke RKPD,
    - tarik RKPD ke Renja,
    - tampilkan diff jika target/pagu/indikator/lokasi berbeda.

## Catatan Desain Bisnis

- RPJMD dan Renstra adalah dokumen 5 tahunan.
- RKPD dan Renja adalah dokumen tahunan.
- RPJMD Banjarnegara 2025-2029 memiliki prakiraan maju 2030.
- Baseline tidak perlu dibuat sebagai master periode baru; baseline dihitung dari tahun sebelum tahun awal dokumen.
- Program RPJMD sekarang terhubung ke sasaran daerah, bukan indikator sasaran.
- Program penunjang urusan pemerintahan daerah kabupaten/kota:
    - di RPJMD bisa muncul sebagai satu nama program,
    - di master memiliki banyak kode sesuai bidang/OPD,
    - di Renstra/RKPD/Renja harus dipetakan ke kode program OPD masing-masing.
- Kegiatan dan sub kegiatan Renstra/Renja/RKPD harus memakai master, bukan ketik manual oleh admin OPD.
- Sub kegiatan punya indikator baku dari master, tetapi snapshot di Renstra bisa diedit.

## Catatan Untuk Chat Baru

Mulai chat baru dengan prompt singkat:

```text
Kita lanjut project E-SAKIP di:
C:\Users\NAUFAL\WEB\e-sakip-bna

Baca PROJECT_STATE.md dulu.
Ikuti AGENTS.md dan RTK.md.
Fokus hemat token: baca file seperlunya, patch langsung, verifikasi secukupnya.

Task sekarang:
[isi task spesifik]
```
