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
- Referensi organisasi dipisahkan menjadi `Struktur Jabatan` dan `Pegawai & Penempatan` agar nomenklatur organisasi tidak tercampur dengan identitas orang.
    - UI operasional disederhanakan menjadi menu `Pegawai OPD`: saat menambah pegawai, jabatan, status jabatan, TMT, dan SK dapat disimpan sekaligus dalam satu formulir. Istilah penempatan tetap dipakai pada struktur data internal untuk menjaga histori.
    - Menu struktur ditampilkan sebagai `Struktur Organisasi` untuk pengelola pusat dan disembunyikan dari sidebar Admin OPD; Admin OPD cukup bekerja dari `Pegawai OPD`.
    - `Struktur Jabatan` memuat hierarki permanen: Kepala Daerah, JPT Pratama, Administrator, Pengawas, Fungsional, dan Pelaksana.
    - Jabatan tidak memakai kode khusus; identitas teknis menggunakan ID database dan pengguna cukup mengisi nomenklatur jabatan.
    - Setiap jabatan selain Kepala Daerah wajib memiliki OPD dan atasan langsung yang valid; unit organisasi bersifat opsional.
    - `Pegawai & Penempatan` memuat PNS, PPPK, dan Non-ASN; akun aplikasi bersifat opsional dan tidak identik dengan pegawai.
    - Penempatan mencatat jabatan, jenis penugasan definitif/Pj./Plt./Plh., TMT Jabatan wajib, tanggal selesai opsional, serta dasar SK.
    - Pejabat definitif tidak diberi tanggal selesai sampai benar-benar diganti/mutasi/pensiun/diberhentikan; sebelum pejabat pengganti dicatat, riwayat lama harus ditutup agar masa tugas tidak bertumpang tindih.
    - Jabatan struktural hanya boleh memiliki satu pemegang pada rentang aktif yang sama. Jabatan Fungsional dan Pelaksana dapat diisi banyak pegawai, tetapi penempatan pegawai yang sama tidak boleh bertumpang tindih.
    - Akun pejabat tidak wajib untuk penyusunan PK karena Admin OPD dapat bertindak sebagai operator. Akun hanya perlu dihubungkan bila pejabat akan login atau melakukan persetujuan sendiri; identitas pihak PK tetap berasal dari pejabat aktif sesuai TMT/periode dokumen.
    - Import Excel tersedia melalui alur template -> upload/validasi -> preview -> terapkan. Workbook terbaru memakai sheet `Jabatan` dan `Pegawai`; pencocokan jabatan menggunakan kombinasi nama jabatan + OPD + unit.
    - Template memberi contoh hierarki sampai Sekretaris/Kabid, Kasubbag/Kasi, JF, dan Pelaksana; memakai kolom `jenis_pegawai` serta `tmt_jabatan`. Importer tetap menerima template lama tanpa `jenis_pegawai`, sheet bernama `Pejabat`, dan kolom lama `tanggal_mulai` untuk kompatibilitas.
    - Validasi import menolak OPD/unit/atasan/akun yang tidak ditemukan, jabatan ganda, hierarki tidak valid atau bersiklus, format tanggal salah, serta masa tugas pejabat yang bertumpang tindih. Seluruh baris harus valid sebelum import dapat diterapkan.
    - Data lama pada riwayat pejabat otomatis dibentuk menjadi master pegawai saat migrasi; relasi lama tetap dipertahankan agar data production tidak hilang.
    - Jabatan dengan turunan atau riwayat tidak dapat dihapus dan harus dinonaktifkan agar histori dokumen aman.
    - Struktur/nomenklatur jabatan dikelola terpusat oleh Super Admin, Bagian Organisasi, dan Dinkominfo.
    - Admin OPD dapat menambah/memperbarui pegawai, mencatat/mengakhiri penempatan, dan menentukan pengampu kinerja tahunan hanya untuk OPD sendiri melalui permission `pegawai.manage`. Admin OPD tidak dapat mengubah struktur jabatan atau menghapus histori penempatan.
    - Pengelola pusat dapat mengelola seluruh pegawai, perpindahan lintas OPD, serta penghapusan data kosong. Data yang sudah memiliki histori penempatan/PK harus dinonaktifkan, bukan dihapus.
    - Penugasan pengampu kinerja disimpan per pegawai, periode, dan level cascading (sasaran/program/kegiatan/sub kegiatan). Penugasan ini menjadi syarat pembuatan PK Cascading.
    - Pimpinan memperoleh akses lihat sesuai cakupan OPD.
    - Struktur level mengikuti rantai akuntabilitas Perbup Banjarnegara Nomor 41 Tahun 2024 sebagai fondasi penyusunan PK bertingkat.
- Master periode tahun.
- Master satuan indikator.
- Master strategi daerah.
- Master urusan pemerintahan dan bidang urusan.
- Master program/kegiatan/sub kegiatan.
- Sub kegiatan sudah ditambah metadata:
    - sasaran sub kegiatan,
    - daftar indikator sub kegiatan (indikator utama baku Kemendagri + indikator tambahan),
    - satuan,
    - definisi operasional.
- Indikator utama tetap disinkronkan ke kolom lama `indikator_sub_kegiatan`/`satuan_indikator_id` agar RKPD, Renja, RKA, DPA, seeder, dan integrasi lama tetap kompatibel. Daftar multi-indikator disimpan di `indikator_sub_kegiatan_pemerintahan`.

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
- Sub kegiatan saat dipilih akan membawa seluruh indikator master sebagai snapshot Renstra dan masih bisa diedit; perubahan master berikutnya tidak menimpa indikator yang sudah disesuaikan pada dokumen Renstra.
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

- RKPD tahunan, diinput dan dikelola oleh Bapperida/admin kabupaten.
- RKPD sekarang memiliki versioning tahunan:
    - `RKPD Awal`,
    - `RKPD Ditetapkan` yang otomatis menjadi snapshot saat RKPD Awal disetujui,
    - `RKPD Perubahan` yang disalin dari RKPD Ditetapkan dan menjadi versi aktif setelah disetujui.
- Versi lama tetap tersimpan sebagai arsip; ID serta relasi data RKPD/RENJA production dipertahankan oleh migrasi aditif.
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
- Baris RKPD dapat diimport melalui Excel dua tahap: download template, upload dan validasi per baris, preview, lalu tombol `Terapkan Import`. Import diblokir bila masih ada baris tidak valid.
- Alur pilihan:
    - pilih OPD,
    - pilih program sesuai OPD,
    - pilih kegiatan sesuai program,
    - pilih sub kegiatan sesuai kegiatan.
- Indikator sub kegiatan otomatis dari master.
- Sinkronisasi/tarik data RKPD dan Renja sudah mulai dibuat dengan tabel `planning_sync_batches` dan `planning_sync_batch_rows`.

### Renja OPD

- Renja tahunan.
- RENJA sekarang memiliki versioning tahunan per OPD/unit:
    - `RENJA Awal` yang memakai acuan RKPD Awal,
    - `RENJA Ditetapkan` yang otomatis dibuat sebagai snapshot saat RENJA Awal disetujui dan memakai RKPD Ditetapkan,
    - `RENJA Perubahan` yang hanya dapat dibuat setelah RKPD Perubahan ditetapkan dan menjadi versi aktif setelah disetujui.
- Snapshot menyalin seluruh `renja_opd_items`; versi lama tetap menjadi arsip dan data production lama tidak diganti ID-nya.
- Migrasi lama memetakan status `approved/locked` sebagai RENJA Ditetapkan dan status lainnya sebagai RENJA Awal.
- Tahun Renja menjadi input utama; periode tahun mengikuti otomatis.
- Judul dan nomor dokumen dibuat uppercase.
- Status awal otomatis draft.
- Form dan preview Renja disesuaikan agar mirip RKPD.
- Program yang muncul harus sesuai Renja OPD terkait, termasuk program penunjang khusus OPD.

### RKA OPD

- Modul RKA OPD tersedia pada grup menu `Penganggaran Kinerja`.
- RKA hanya dapat dibuat dari `RENJA Ditetapkan` atau `RENJA Perubahan` yang sudah disetujui/terkunci.
- Satu versi RENJA hanya dapat memiliki satu RKA aktif; pembuatan RKA menyalin seluruh sub kegiatan sebagai snapshot sehingga perubahan RKA tidak mengubah RENJA sumber.
- Jenis dokumen mengikuti sumbernya:
    - `RKA APBD` dari RENJA Ditetapkan,
    - `RKA Perubahan APBD` dari RENJA Perubahan Ditetapkan.
- Struktur isian memakai lingkup inti `RKA-BELANJA SKPD` berdasarkan PP 12/2019 dan Permendagri 77/2020:
    - organisasi dan unit organisasi,
    - urusan/program/kegiatan/sub kegiatan,
    - indikator dan tolok ukur kinerja,
    - target kinerja,
    - sumber pendanaan,
    - lokasi, waktu pelaksanaan, dan kelompok sasaran,
    - alokasi T-1, pagu RENJA, pagu usulan T, hasil verifikasi T, dan alokasi T+1.
- Preview RKA memakai format rekap resmi dengan header tabel bertingkat: Kode, Uraian, Sumber Dana, Lokasi, Tahun T-1, rincian Belanja Operasi/Modal/Tidak Terduga/Transfer dan jumlah Tahun T, serta Tahun T+1.
- Baris preview dikelompokkan dan diurutkan natural berdasarkan kode Urusan -> Bidang Urusan -> Program -> Kegiatan -> Sub Kegiatan. Setiap baris induk mengagregasi anggaran turunannya dan memakai warna level yang berbeda; sub kegiatan tetap berlatar putih.
- Preview RKA dapat diekspor ke Excel (`.xlsx`) dengan struktur header, urutan hierarki, warna level, rincian pagu, dan total yang sama dengan tabel di aplikasi.
- Lebar preview dan export RKA dibuat ringkas: kolom sumber dana, lokasi, kode, dan nominal dipadatkan dengan pembungkusan teks agar kebutuhan scroll horizontal berkurang.
- Pagu Tahun T pada setiap sub kegiatan RKA dirinci menjadi Belanja Operasi, Modal, Tidak Terduga, dan Transfer, masing-masing untuk usulan serta hasil verifikasi; total `pagu_usulan`/`pagu_hasil_verifikasi` dihitung server-side dari empat rincian tersebut.
- Seluruh input nominal pada modal rincian RKA menggunakan pemisah ribuan Indonesia saat ditampilkan dan diketik (contoh `223.716.000`), lalu dinormalisasi kembali di backend saat disimpan.
- Migrasi `2026_08_23_000013_add_belanja_breakdown_to_rka_opd_items.php` mempertahankan kolom total dan `jenis_belanja` lama, lalu melakukan backfill pagu production ke rincian yang sesuai sehingga data lama tidak hilang.
- Detail rekening, koefisien/volume, harga satuan, PPN, transaksi, dan penatausahaan tetap berada di SIPD/aplikasi keuangan utama.
- Alur workflow RKA: Draft -> Diajukan -> Terverifikasi -> Disetujui -> Terkunci; pemeriksa dengan permission `rka.verify` dapat mengubah pagu hasil verifikasi dan wajib memberi alasan bila berbeda dari usulan.
- Permission baru: `rka.view`, `rka.manage`, dan `rka.verify`.

### DPA OPD

- Modul DPA OPD tersedia pada grup menu `Penganggaran Kinerja` setelah RKA.
- DPA hanya dapat dibuat dari RKA yang sudah disetujui/terkunci; satu RKA hanya dapat memiliki satu DPA aktif.
- Pembuatan DPA menyalin struktur kinerja dan pagu hasil verifikasi RKA sebagai snapshot, sehingga perubahan DPA tidak mengubah RKA sumber.
- Jenis dokumen mengikuti RKA:
    - `DPA-SKPD` dari RKA APBD,
    - `DPPA-SKPD` dari RKA Perubahan APBD.
- Lingkup inti `DPA-BELANJA SKPD` mengikuti PP 12/2019 dan Permendagri 77/2020:
    - identitas OPD/unit, tahun anggaran, dan acuan RKA,
    - dasar Perda APBD dan Perkada Penjabaran APBD,
    - program/kegiatan/sub kegiatan, indikator/tolok ukur, target, sumber pendanaan, lokasi, kelompok sasaran, dan waktu pelaksanaan,
    - pagu RKA dan pagu DPA,
    - rencana penarikan dana Januari-Desember per sub kegiatan,
    - Pengguna Anggaran, pengesahan PPKD, dan persetujuan Sekretaris Daerah.
- Pagu DPA awal otomatis sama dengan pagu hasil verifikasi RKA. Penyesuaian oleh pemeriksa wajib memiliki alasan.
- DPA tidak dapat diajukan jika identitas dasar APBD belum lengkap atau total penarikan bulanan tidak sama dengan pagu DPA.
- DPA tidak dapat disahkan sebelum nomor/tanggal DPA serta identitas PPKD dan Sekretaris Daerah dilengkapi.
- Workflow DPA: Draft -> Diajukan -> Terverifikasi -> Disahkan -> Terkunci.
- Detail rekening, koefisien/volume, harga satuan, PPN, SPP/SPM/SP2D, transaksi, dan akuntansi tetap berada di SIPD/aplikasi keuangan utama.
- Permission: `dpa.view`, `dpa.manage`, dan `dpa.verify`; penyusun berada pada Admin OPD, sedangkan verifikasi/pengesahan diberikan kepada role `admin_kabupaten_bpkad` sebagai fungsi PPKD/TAPD. Bapperida hanya memperoleh akses lihat DPA.

### Struktur Navigasi Kinerja

- Grup `Perencanaan Kinerja` saat ini hanya menampilkan RPJMD, RKPD, Renstra OPD, dan Renja OPD.
- Menu Pohon Kinerja, Perjanjian Kinerja, Rencana Aksi, dan Revisi Target sementara disembunyikan dari sidebar tanpa menghapus route atau datanya.
- Saat dilanjutkan, Perjanjian Kinerja dan Rencana Aksi direncanakan masuk ke grup baru `Penetapan Kinerja`, berurutan PK lalu Rencana Aksi.
- Revisi Target direncanakan menjadi aksi kontekstual di detail dokumen terkait, bukan menu utama sidebar.

### Fondasi Perjanjian Kinerja Bertingkat

- PK sekarang memiliki subjek pegawai, snapshot nama/NIP/jabatan, atasan, penempatan yang digunakan, dan tipe `PK Cascading` atau `PK Individu`.
- PK Cascading hanya dapat dibuat bila pegawai memiliki penugasan pengampu pada periode yang sama; jalur ini dapat diteruskan ke Rencana Aksi dan realisasi/pengukuran organisasi.
- PK Individu dapat dibuat untuk staf/JF/Pelaksana tanpa sumber cascading; item diisi manual dan sengaja tidak tersedia sebagai sumber Rencana Aksi maupun realisasi organisasi.
- Data PK lama tetap bertipe cascading secara default dan tidak dihapus oleh migrasi; identitas pegawainya dapat dilengkapi saat dokumen diedit.

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
- Dokumen RPJMD, RKPD, Renstra, Renja, RKA, dan DPA yang sudah disetujui/terkunci dapat dibuka oleh Super Admin melalui aksi `Koreksi Data` tanpa membuat versi Perubahan:
    - alasan koreksi dan acuan dokumen resmi wajib diisi,
    - status kembali menjadi `revision`/Perlu Perbaikan dan metadata pengesahan aktif dikosongkan,
    - riwayat menyimpan alasan, acuan resmi, pelaksana, status asal, dan dokumen turunan terdampak,
    - turunan Draft/Revisi/Ditolak dipertahankan lalu ditandai Perlu Perbaikan,
    - turunan Diajukan/Terverifikasi/Disetujui/Terkunci memblokir koreksi sehingga koreksi berantai wajib dimulai dari dokumen paling bawah,
    - turunan tidak dapat diajukan kembali sebelum dokumen acuannya selesai dikoreksi dan disetujui kembali.
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

5. Lanjut desain/versioning dokumen tahunan berikutnya (versioning RKPD dan RENJA serta modul RKA dan DPA sudah selesai):

    - finalisasi matriks item PK bertingkat untuk indikator kegiatan/sub kegiatan,
    - penyempurnaan Rencana Aksi berdasarkan penugasan PK Cascading.

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
