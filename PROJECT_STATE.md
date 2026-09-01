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
- Referensi organisasi tetap dipisahkan secara data menjadi `Jabatan Organisasi` dan `Pegawai/Penempatan` agar nomenklatur struktur tidak tercampur dengan identitas orang.
    - UI Admin OPD disederhanakan menjadi satu menu `Jabatan & Pegawai` dengan tab `Pegawai` dan `Jabatan di OPD`. Saat menambah pegawai, jabatan, status jabatan, TMT, dan SK dapat disimpan sekaligus dalam satu formulir. Istilah penempatan tetap dipakai pada struktur data internal untuk menjaga histori.
    - Pengelola pusat tetap memperoleh menu `Struktur Organisasi` untuk pengendalian nomenklatur dan verifikasi usulan seluruh OPD.
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
    - Struktur resmi/nomenklatur jabatan dikendalikan oleh Super Admin, Bagian Organisasi, dan Dinkominfo. Jabatan lama otomatis berstatus `verified` sehingga migrasi tidak mengubah data atau penempatan production.
    - Admin OPD dapat mengusulkan jabatan baru hanya untuk OPD sendiri melalui permission `jabatan_organisasi.manage_opd`. Usulan berstatus `pending`; Admin OPD hanya dapat mengubah/menghapus usulan miliknya yang masih `pending/rejected`, sedangkan jabatan resmi `verified` tetap terkunci.
    - Admin Kabupaten memverifikasi atau mengembalikan usulan dengan catatan melalui permission `jabatan_organisasi.verify`. Verifikasi hierarki wajib dilakukan dari jabatan atasan ke bawah.
    - Jabatan `pending` dapat dipakai untuk penempatan pegawai agar operasional OPD tidak terhenti. Jabatan `rejected` tetap mempertahankan histori lama tetapi tidak dapat dipilih untuk penempatan baru sampai diperbaiki dan diverifikasi.
    - Admin OPD dapat menambah/memperbarui pegawai dan mencatat/mengakhiri penempatan hanya untuk OPD sendiri melalui permission `pegawai.manage`. Admin OPD tidak dapat mengubah jabatan resmi atau menghapus histori penempatan.
    - Pengelola pusat dapat mengelola seluruh pegawai, perpindahan lintas OPD, serta penghapusan data kosong. Data yang sudah memiliki histori penempatan/PK harus dinonaktifkan, bukan dihapus.
    - Lingkup kinerja tahunan tidak lagi diatur pada profil pegawai. Tabel/rute penugasan pengampu lama tetap dipertahankan untuk kompatibilitas histori production, tetapi UI-nya disembunyikan dan tidak menjadi syarat PK baru.
- Pimpinan memperoleh akses lihat sesuai cakupan OPD.
- Daftar `Struktur Organisasi` dan `Pegawai OPD` dikelompokkan per perangkat daerah. Di dalam setiap OPD, data mengikuti hierarki jabatan aktif: Kepala Daerah/Kepala OPD, Sekretaris, Administrator/Kabid, Pengawas, Fungsional, lalu Pelaksana; pegawai tanpa jabatan aktif ditempatkan paling bawah.
- Struktur level mengikuti rantai akuntabilitas Perbup Banjarnegara Nomor 41 Tahun 2024 sebagai fondasi penyusunan PK bertingkat.
    - Migrasi `2026_08_27_000001_add_jabatan_verification_workflow.php` menambahkan status/audit verifikasi jabatan dan permission usulan/verifikasi tanpa menghapus data lama.
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
- Satu RENSTRA dapat memiliki lebih dari satu Tujuan OPD. Saat membuat Sasaran OPD, tujuan induk wajib dipilih; jika hanya ada satu tujuan maka terpilih otomatis, sedangkan jika ada lebih dari satu tujuan pilihan awal dikosongkan agar user menentukan cabangnya.
- Tampilan input memakai panel/section, modal, dan grouping.
- Program di Renstra mengambil program relevan dari RPJMD untuk OPD terkait.
- Program penunjang dipetakan ke kode program master sesuai OPD, bukan selalu kode pertama.
- Ketika memilih program RPJMD, indikator program dan targetnya disalin menjadi snapshot Renstra.
- Kegiatan dan sub kegiatan wajib dari master, tidak boleh input manual oleh admin OPD.
- Satu program master dapat dipakai pada beberapa cabang Sasaran OPD/Sasaran Program dan satu kegiatan master dapat dipakai pada beberapa cabang Sasaran Kegiatan. Setiap cabang tetap menyimpan indikator dan targetnya sendiri tanpa saling menimpa.
- Dropdown induk saat menambah kegiatan menampilkan konteks bertingkat: Sasaran OPD di atas dan Sasaran Program di bawah. Dropdown induk saat menambah sub kegiatan menampilkan Sasaran Program di atas dan Sasaran Kegiatan di bawah.
- Daftar program pada kelola kegiatan dan sub kegiatan menampilkan Sasaran OPD serta Sasaran Program sebagai badge satu baris yang ringkas; daftar kegiatan juga menampilkan Sasaran Kegiatan agar cabang bernama sama mudah dibedakan.
- Satu sub kegiatan master hanya boleh muncul sekali dalam satu versi RENSTRA, termasuk lintas cabang kegiatan. Pilihan yang sudah digunakan tampil pudar/nonaktif beserta lokasi cabangnya, dan backend manual/autosave/import tetap menolak duplikasi.
- RENJA mengonsolidasikan cabang program RENSTRA berdasarkan ID program master, sehingga program bernama sama dari beberapa sasaran hanya muncul sekali pada dokumen tahunan. RKA dan DPA meneruskan struktur master dari RENJA sehingga tidak menggandakan program/kegiatan tersebut.
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
- Preview tabel dan export Excel menampilkan baris pengelompokan Sasaran Program, Sasaran Kegiatan, dan Sasaran Sub Kegiatan. Program/kegiatan bernama sama tidak digabung jika berada pada cabang sasaran berbeda; setiap cabang tetap ditampilkan sesuai urutan hierarkinya.
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
- Sinkronisasi/tarik data RKPD dan RENJA memakai tabel `planning_sync_batches` dan `planning_sync_batch_rows`. Sumber wajib dokumen resmi aktif berstatus `approved/locked`; target wajib masih `draft/revision/rejected`. Status `submitted/verified/approved/locked` tidak dapat menjadi target, termasuk oleh Super Admin.
- Arah RKPD -> RENJA mengambil RKPD Ditetapkan aktif untuk RENJA Akhir Draft, atau RKPD Perubahan Ditetapkan aktif untuk RENJA Perubahan. Arah RENJA -> RKPD hanya mengambil RENJA Ditetapkan/Perubahan Ditetapkan aktif. Status dan keaktifan sumber diperiksa kembali saat penerapan batch.

### Renja OPD

- Renja tahunan.
- RENJA sekarang memiliki versioning tahunan per OPD/unit:
    - `RENJA Akhir Draft` (nilai internal `awal`) yang memakai acuan RKPD Ditetapkan aktif,
    - `RENJA Ditetapkan` yang otomatis dibuat sebagai snapshot saat RENJA Awal disetujui dan memakai RKPD Ditetapkan,
    - `RENJA Perubahan` yang hanya dapat dibuat setelah RKPD Perubahan ditetapkan dan menjadi versi aktif setelah disetujui.
- Snapshot menyalin seluruh `renja_opd_items`; versi lama tetap menjadi arsip dan data production lama tidak diganti ID-nya.
- Migrasi lama memetakan status `approved/locked` sebagai RENJA Ditetapkan dan status lainnya sebagai RENJA Awal.
- Tahun Renja menjadi input utama; periode tahun mengikuti otomatis.
- Pembuatan RENJA Akhir Draft otomatis memilih RKPD Ditetapkan aktif yang sudah disetujui pada tahun yang sama. Referensi lama yang masih menunjuk RKPD Awal tetap dapat dipertahankan saat edit agar data production tidak rusak; sinkronisasi akan mencari versi resmi dari root RKPD yang sama.
- Judul dan nomor dokumen dibuat uppercase.
- Status awal otomatis draft.
- Form dan preview Renja disesuaikan agar mirip RKPD.
- Program yang muncul harus sesuai Renja OPD terkait, termasuk program penunjang khusus OPD.
- Saat `RENJA Akhir Draft` pertama kali dibuat (nilai internal tetap `awal` untuk kompatibilitas), RENSTRA aktif milik OPD yang mencakup tahun RENJA dipilih otomatis (tetap dapat dipilih pada form) dan seluruh Sub Kegiatan RENSTRA disalin satu kali sebagai struktur awal. Sebelum penyimpanan, modal konfirmasi menjelaskan proses penyalinan dan penyesuaian tahunan yang harus dilakukan user.
- Cabang Program/Kegiatan RENSTRA yang memakai master sama karena mendukung sasaran berbeda dikonsolidasikan; satu Sub Kegiatan master hanya menjadi satu baris RENJA dan dipetakan ke master Kegiatan/Sub Kegiatan pada periode tahun RENJA.
- Baris salinan ditandai `sumber_item = renstra` dan menyimpan `opd_sub_kegiatan_id`. Identitas Program, Kegiatan, Sub Kegiatan, indikator, dan target akhir periode RENSTRA dikunci di frontend serta backend; target/pagu/lokasi/sumber dana dan isian tahunan RENJA tetap dapat diedit, baris dapat dihapus, dan tombol tambah manual tetap tersedia.
- Daftar input RENJA ditampilkan hierarkis seperti rincian RKA: Program -> Kegiatan -> Sub Kegiatan, tanpa tabel operasional yang melebar ke samping.
- Bootstrap tidak pernah dijalankan ulang jika RENJA sudah pernah memiliki baris, termasuk baris yang telah dihapus lunak. Migrasi `2026_08_26_000001_add_renstra_source_to_renja_opd_items.php` bersifat aditif dan data lama otomatis tetap bertipe `manual`.
- Sumber pembuatan RENJA Akhir Draft wajib RENSTRA versi aktif berstatus `approved/locked`, berasal dari OPD yang sama, dan mencakup tahun RENJA. Filter UI, validasi request, dan service bootstrap memeriksa ulang syarat tersebut agar RENSTRA draft/revisi/ditolak tidak dapat tersalin.
- Migrasi `2026_08_26_000003_harden_renja_rkpd_source_integrity.php` menambahkan partial unique index untuk satu Sub Kegiatan aktif per dokumen RENJA. Migrasi melakukan preflight dan berhenti tanpa menghapus data bila menemukan duplikasi lama, sehingga data konflik dapat direkonsiliasi terlebih dahulu.
- Kolom `target_akhir_renstra` pada item RENJA dan RKPD menggunakan tipe `text` agar gabungan banyak indikator/target tidak terpotong pada batas 255 karakter.

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
    - alokasi T-1, pagu RENJA, pagu RKA Tahun T, dan alokasi T+1.
- Preview RKA memakai format rekap ringkas dengan header tabel bertingkat: Kode, Uraian, Sumber Dana, Lokasi, Alokasi Tahun-1, Total Pagu RKA Tahun berjalan, dan Alokasi Tahun+1. Kolom rincian jenis belanja tidak ditampilkan.
- Baris preview dikelompokkan dan diurutkan natural berdasarkan kode Urusan -> Bidang Urusan -> Program -> Kegiatan -> Sub Kegiatan. Setiap baris induk mengagregasi anggaran turunannya dan memakai warna level yang berbeda; sub kegiatan tetap berlatar putih.
- Preview RKA dapat diekspor ke Excel (`.xlsx`) dengan struktur header, urutan hierarki, warna level, dan total pagu yang sama dengan tabel di aplikasi.
- Lebar preview dan export RKA dibuat ringkas: kolom sumber dana, lokasi, kode, dan nominal dipadatkan dengan pembungkusan teks agar kebutuhan scroll horizontal berkurang.
- RKA di E-SAKIP mencatat hasil akhir penyusunan RKA, bukan proses usulan dan verifikasi anggaran. Setiap sub kegiatan hanya menginput satu total `Pagu RKA`; rincian Belanja Operasi, Modal, Tidak Terduga, dan Transfer tidak lagi diwajibkan atau diedit pada modul ini.
- Pagu RENJA tetap ditampilkan sebagai acuan. Jika Pagu RKA berbeda dari Pagu RENJA, penyusun wajib mengisi catatan perbedaan.
- Preview, export Excel, ringkasan, dan sumber pembuatan DPA selalu memakai `pagu_rka`, tidak bergantung pada status workflow.
- Seluruh input nominal pada modal RKA menggunakan pemisah ribuan Indonesia saat ditampilkan dan diketik (contoh `223.716.000`), lalu dinormalisasi kembali di backend saat disimpan.
- Migrasi `2026_08_24_000015_simplify_rka_final_budget.php` menambahkan kolom final tanpa menghapus kolom lama. Backfill mempertahankan nilai efektif sebelumnya: dokumen `verified`/`approved`/`locked` memakai hasil verifikasi lama, sedangkan status lain memakai pagu usulan lama. Kolom total lama tetap disinkronkan sementara, sedangkan kolom rincian jenis belanja lama dipertahankan tanpa diubah untuk keamanan rollback dan data production.
- Detail rekening, koefisien/volume, harga satuan, PPN, transaksi, dan penatausahaan tetap berada di SIPD/aplikasi keuangan utama.
- Alur workflow RKA: Draft -> Diajukan -> Disetujui -> Terkunci. Pemeriksa menilai kesesuaian dokumen final dan dapat menyetujui atau mengembalikannya untuk diperbaiki; pemeriksa tidak mengubah nominal RKA.
- Permission: `rka.view`, `rka.manage`, dan `rka.verify`. Kode `rka.verify` dipertahankan untuk kompatibilitas sebagai hak pemeriksa/persetujuan RKA.

### DPA OPD

- Modul DPA OPD tersedia pada grup menu `Penganggaran Kinerja` setelah RKA.
- DPA hanya dapat dibuat dari RKA yang sudah disetujui/terkunci; satu RKA hanya dapat memiliki satu DPA aktif.
- Pembuatan DPA menyalin struktur kinerja dan Pagu RKA final sebagai snapshot, sehingga perubahan DPA tidak mengubah RKA sumber.
- Jenis dokumen mengikuti RKA:
    - `DPA-SKPD` dari RKA APBD,
    - `DPPA-SKPD` dari RKA Perubahan APBD.
- Lingkup inti `DPA-BELANJA SKPD` mengikuti PP 12/2019 dan Permendagri 77/2020:
    - identitas OPD/unit, tahun anggaran, dan acuan RKA,
    - dasar Perda APBD dan Perkada Penjabaran APBD,
    - program/kegiatan/sub kegiatan, indikator/tolok ukur, target, sumber pendanaan, lokasi, kelompok sasaran, dan waktu pelaksanaan,
    - alokasi Tahun-1, pagu RKA, pagu DPA final, dan alokasi Tahun+1,
    - Pengguna Anggaran, pengesahan PPKD, dan persetujuan Sekretaris Daerah.
- Pagu DPA awal otomatis sama dengan Pagu RKA final. Penyesuaian oleh pemeriksa wajib memiliki alasan.
- Preview DPA mengikuti tabel hierarkis RKA (OPD, urusan, bidang, program, kegiatan, sub kegiatan), menggunakan satu kolom total Pagu DPA, dan menampilkan Nomor DPA pada identitas formulir.
- Rencana penarikan dana bulanan tidak digunakan pada modul DPA E-SAKIP. Tabel/data lama tetap dipertahankan untuk kompatibilitas dan keamanan data production, tetapi tidak dibuat, ditampilkan, atau divalidasi lagi.
- Migrasi `2026_08_26_000002_add_allocation_snapshots_to_dpa_opd_items.php` menambahkan snapshot alokasi Tahun-1 dan Tahun+1 serta melakukan backfill dari rincian RKA sumber tanpa menghapus data lama.
- DPA tidak dapat diajukan jika identitas dasar APBD belum lengkap atau rincian sub kegiatan belum tersedia.
- DPA tidak dapat disahkan sebelum nomor/tanggal DPA serta identitas PPKD dan Sekretaris Daerah dilengkapi.
- Workflow DPA: Draft -> Diajukan -> Terverifikasi -> Disahkan -> Terkunci.
- Detail rekening, koefisien/volume, harga satuan, PPN, SPP/SPM/SP2D, transaksi, dan akuntansi tetap berada di SIPD/aplikasi keuangan utama.
- Permission: `dpa.view`, `dpa.manage`, dan `dpa.verify`; penyusun berada pada Admin OPD, sedangkan verifikasi/pengesahan diberikan kepada role `admin_kabupaten_bpkad` sebagai fungsi PPKD/TAPD. Bapperida hanya memperoleh akses lihat DPA.

### Struktur Navigasi Kinerja

- Grup `Perencanaan Kinerja` saat ini hanya menampilkan RPJMD, RKPD, Renstra OPD, dan Renja OPD.
- Menu Pohon Kinerja dan Revisi Target masih disembunyikan tanpa menghapus route atau datanya.
- Perjanjian Kinerja dan Rencana Aksi sudah ditampilkan pada grup sidebar `Penetapan Kinerja`, berurutan PK lalu Rencana Aksi.
- Revisi Target direncanakan menjadi aksi kontekstual di detail dokumen terkait, bukan menu utama sidebar.

### Fondasi Perjanjian Kinerja Bertingkat

- PK sekarang memiliki empat level dokumen: `PK Bupati`, `PK Kepala OPD`, `PK Struktural`, dan `PK JF/Pelaksana`.
- Menu `Referensi Data -> Kop Dokumen` mengelola satu profil kop Kabupaten dan satu profil per OPD. Admin Kabupaten dapat mengelola seluruh profil, sedangkan Admin OPD hanya dapat mengelola profil OPD sendiri.
- Kop dokumen memuat logo, nama pemerintah, nama instansi, alamat, telepon, faksimile, website, surel, kota, dan kode pos. Saat PK dibuat, kop disalin menjadi snapshot sehingga perubahan master tidak mengubah dokumen lama; snapshot kop PK berstatus Draft/Perlu Perbaikan/Ditolak masih dapat disesuaikan melalui aksi `Atur Kop`.
- PK Bupati mengambil snapshot indikator tujuan/sasaran beserta target dan program/anggaran dari RKPD resmi aktif berstatus `approved/locked`.
- PK Kepala OPD menampilkan matriks hanya dari Tujuan OPD dan Sasaran OPD beserta indikator/target tahunannya. Sasaran Program dan indikator program tidak menjadi baris bernomor pada matriks; Program OPD tetap ditampilkan terpisah pada tabel Program dan Anggaran yang bersumber dari DPA/DPPA resmi.
- Seluruh PK Cascading memakai snapshot read-only. Koreksi dilakukan pada dokumen sumber resmi atau lingkup pilihan PK, lalu snapshot dibentuk ulang; data sumber tidak ikut berubah.
- PK memiliki subjek pegawai, snapshot nama/NIP/jabatan Pihak Pertama dan Pihak Kedua, penempatan yang digunakan, tanggal/tempat penandatanganan, dan sumber dokumen.
- PK Struktural memilih langsung beberapa Sasaran OPD, Program OPD, Kegiatan OPD, atau Sub Kegiatan OPD dari Renstra resmi aktif pada form PK. Pilihan disimpan pada `lingkup_kinerja_snapshot`, lalu seluruh indikator dan target tahun terkait disalin sebagai snapshot.
- PK JF/Pelaksana mempunyai dua mode: `Cascading` memakai pemilihan lingkup Renstra yang sama, sedangkan `Manual` diisi sendiri setelah PK dibuat. Hanya PK Cascading resmi yang dapat diteruskan ke Rencana Aksi dan realisasi/pengukuran organisasi.
- Pihak Kedua PK Struktural/JF/Pelaksana mengikuti pemegang aktif jabatan induk pada Struktur Organisasi, bukan sekadar pegawai lain dalam OPD. Kepala OPD tetap berpasangan dengan Kepala Daerah; Kabid/Kabag dengan Kepala OPD atau atasan langsungnya; Kasi/Kasubbag dengan Kabid/Kabag sesuai rantai `parent_id`.
- Cetak PK Struktural memakai format resmi Sekretaris/Kabid/Kabag: identitas pejabat dan unit kerja, matriks Sasaran Program/Sasaran Kegiatan, rekap Program-Anggaran-Keterangan, tanda tangan atasan langsung, dan catatan penyesuaian. Seluruh cetak browser, PDF, dan Word PK memakai ukuran 21 × 33 cm.
- Pilihan PK kini membedakan `PK Sek/Kabid/Kabag` (`level_pk=struktural`) dan `PK Kasi/Kasubbag/JF/Pelaksana` (`level_pk=individu`). Mode cascading untuk kelompok kedua hanya menerima Kegiatan/Sub Kegiatan Renstra dan memakai format cetak khusus: matriks Sasaran Kegiatan/Sasaran Sub Kegiatan, rekap Kegiatan-Sub Kegiatan beserta anggaran DPA/DPPA resmi, serta catatan kaki resmi.
- Halaman awal PK menampilkan dokumen dalam kelompok per OPD (termasuk kelompok khusus tingkat kabupaten), dengan ringkasan jumlah dokumen/resmi, informasi pemilik, level, status, tahun, isi snapshot, anggaran, dan aksi yang responsif. Pencarian PK juga mencakup nama/NIP pegawai.
- Judul PK dibentuk otomatis dan readonly dari jabatan penandatangan serta tahun (`PK Bupati Banjarnegara Tahun YYYY`, atau `PK {Nama Jabatan} Tahun YYYY`). Jika data lama belum memiliki penempatan, sistem memakai nama pegawai sebagai fallback; backend menormalisasi judul saat dokumen disimpan.
- Migrasi `2026_08_31_000002_add_direct_cascading_scope_to_perjanjian_kinerja.php` bersifat aditif dan tidak menghapus penugasan maupun PK lama.
- Sinkronisasi Struktur Organisasi, Pegawai OPD, dan PK memakai penempatan aktif sebagai sumber OPD/unit/jabatan. Perubahan identitas pegawai menyegarkan identitas penempatan aktif, perubahan struktur menyegarkan pemegang aktif, dan PK memvalidasi lingkup berdasarkan OPD jabatan yang dipilih. Snapshot PK yang sudah tersimpan tetap tidak diubah. Migrasi `2026_08_31_000003_sync_current_pegawai_organization.php` menormalkan data aktif lama tanpa menghapus histori.
- Data PK lama tetap bertipe cascading secara default dan tidak dihapus oleh migrasi; identitas pegawainya dapat dilengkapi saat dokumen diedit.
- Preview/cetak PK memakai format dua halaman: pernyataan dan tanda tangan, kemudian lampiran matriks indikator serta rekap program/anggaran. Export PDF memakai layout yang sama; export Word tetap tersedia.
- Dasar implementasi: Perpres 29/2014, PermenPANRB 53/2014, dan Perbup Banjarnegara 41/2024. Perbup Banjarnegara 14/2015 sudah dicabut oleh Perbup 41/2024.

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

5. Lanjutkan PK struktural dan Rencana Aksi:

    - batasi pemilihan matriks PK struktural secara penuh berdasarkan beberapa penugasan sasaran/program/kegiatan/sub kegiatan milik pegawai,
    - finalisasi matriks PK struktural untuk indikator kegiatan/sub kegiatan,
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
