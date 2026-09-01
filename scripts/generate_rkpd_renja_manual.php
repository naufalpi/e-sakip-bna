<?php

declare(strict_types=1);

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Table;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$outputDirectory = $root.'/docs';
$outputPath = $outputDirectory.'/Manual_Book_Proses_RKPD_dan_RENJA_E-SAKIP_Banjarnegara.docx';
$logoPath = $root.'/public/images/logo-banjarnegara-1800.png';

if (! is_dir($outputDirectory)) {
    mkdir($outputDirectory, 0775, true);
}

$phpWord = new PhpWord;
$phpWord->setDefaultFontName('Arial');
$phpWord->setDefaultFontSize(10.5);
$phpWord->getSettings()->setUpdateFields(true);

$navy = '00336C';
$blue = '0B5CAB';
$sky = 'EAF3FB';
$teal = '087F5B';
$mint = 'E7F7F1';
$amber = '9A6700';
$cream = 'FFF7DB';
$red = 'B42318';
$rose = 'FDECEC';
$slate = '334155';
$lightSlate = 'F1F5F9';
$border = 'CBD5E1';
$white = 'FFFFFF';

$phpWord->addTitleStyle(1, [
    'name' => 'Arial',
    'size' => 16,
    'bold' => true,
    'color' => $navy,
], [
    'spaceBefore' => 160,
    'spaceAfter' => 140,
    'keepNext' => true,
]);
$phpWord->addTitleStyle(2, [
    'name' => 'Arial',
    'size' => 13,
    'bold' => true,
    'color' => $blue,
], [
    'spaceBefore' => 160,
    'spaceAfter' => 100,
    'keepNext' => true,
]);
$phpWord->addTitleStyle(3, [
    'name' => 'Arial',
    'size' => 11,
    'bold' => true,
    'color' => $slate,
], [
    'spaceBefore' => 120,
    'spaceAfter' => 70,
    'keepNext' => true,
]);

$phpWord->addParagraphStyle('Body', [
    'alignment' => Jc::BOTH,
    'lineHeight' => 1.25,
    'spaceAfter' => 100,
]);
$phpWord->addParagraphStyle('Compact', [
    'alignment' => Jc::LEFT,
    'lineHeight' => 1.15,
    'spaceAfter' => 55,
]);
$phpWord->addParagraphStyle('Bullet', [
    'alignment' => Jc::LEFT,
    'lineHeight' => 1.18,
    'spaceAfter' => 55,
    'leftIndent' => 360,
    'hanging' => 180,
]);
$phpWord->addParagraphStyle('Step', [
    'alignment' => Jc::LEFT,
    'lineHeight' => 1.2,
    'spaceAfter' => 70,
    'leftIndent' => 360,
    'hanging' => 260,
]);
$phpWord->addParagraphStyle('Caption', [
    'alignment' => Jc::CENTER,
    'spaceBefore' => 40,
    'spaceAfter' => 100,
]);

$phpWord->addTableStyle('DataTable', [
    'borderColor' => $border,
    'borderSize' => 6,
    'cellMargin' => 80,
    'layout' => Table::LAYOUT_FIXED,
], [
    'bgColor' => $navy,
    'cantSplit' => true,
    'tblHeader' => true,
]);
$phpWord->addTableStyle('SoftTable', [
    'borderColor' => $border,
    'borderSize' => 4,
    'cellMargin' => 90,
    'layout' => Table::LAYOUT_FIXED,
]);
$phpWord->addTableStyle('FlowTable', [
    'borderColor' => $white,
    'borderSize' => 2,
    'cellMargin' => 80,
    'layout' => Table::LAYOUT_FIXED,
]);

/** @param array<string, mixed> $font */
function addBody($container, string $text, array $font = []): void
{
    $container->addText($text, $font, 'Body');
}

function addBullet($container, string $text, bool $boldLead = false): void
{
    $run = $container->addTextRun('Bullet');
    $run->addText('• ', ['bold' => true, 'color' => '0B5CAB']);
    if ($boldLead && str_contains($text, ':')) {
        [$lead, $rest] = explode(':', $text, 2);
        $run->addText($lead.':', ['bold' => true]);
        $run->addText($rest);
    } else {
        $run->addText($text);
    }
}

function addStep($container, int $number, string $title, string $description): void
{
    $run = $container->addTextRun('Step');
    $run->addText($number.'. ', ['bold' => true, 'color' => '00336C']);
    $run->addText($title, ['bold' => true]);
    $run->addText(' — '.$description);
}

/** @param list<string> $headers @param list<list<string>> $rows @param list<int> $widths */
function addDataTable($container, array $headers, array $rows, array $widths): void
{
    $table = $container->addTable('DataTable');
    $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
    foreach ($headers as $index => $header) {
        $cell = $table->addCell($widths[$index], ['bgColor' => '00336C', 'valign' => 'center']);
        $cell->addText($header, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    }
    foreach ($rows as $rowIndex => $row) {
        $table->addRow(null, ['cantSplit' => true]);
        foreach ($row as $index => $value) {
            $cell = $table->addCell($widths[$index], [
                'bgColor' => $rowIndex % 2 === 0 ? 'FFFFFF' : 'F8FAFC',
                'valign' => 'top',
            ]);
            $cell->addText($value, ['size' => 9.5], ['lineHeight' => 1.12, 'spaceAfter' => 0]);
        }
    }
    $container->addText('', [], ['spaceAfter' => 20]);
}

function addCallout($container, string $title, string $text, string $type = 'info'): void
{
    $palette = match ($type) {
        'success' => ['E7F7F1', '087F5B'],
        'warning' => ['FFF7DB', '9A6700'],
        'danger' => ['FDECEC', 'B42318'],
        default => ['EAF3FB', '0B5CAB'],
    };
    $table = $container->addTable('SoftTable');
    $cell = $table->addRow()->addCell(9300, ['bgColor' => $palette[0], 'valign' => 'center']);
    $run = $cell->addTextRun(['lineHeight' => 1.18, 'spaceAfter' => 0]);
    $run->addText($title.'  ', ['bold' => true, 'color' => $palette[1]]);
    $run->addText($text, ['color' => '334155']);
    $container->addText('', [], ['spaceAfter' => 15]);
}

function addFlow($container, array $items): void
{
    $table = $container->addTable('FlowTable');
    foreach ($items as $index => $item) {
        $table->addRow(null, ['cantSplit' => true]);
        $number = $table->addCell(700, ['bgColor' => '00336C', 'valign' => 'center']);
        $number->addText((string) ($index + 1), ['bold' => true, 'color' => 'FFFFFF', 'size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $content = $table->addCell(7900, ['bgColor' => $index % 2 === 0 ? 'EAF3FB' : 'F1F5F9', 'valign' => 'center']);
        $run = $content->addTextRun(['lineHeight' => 1.12, 'spaceAfter' => 0]);
        $run->addText($item[0], ['bold' => true, 'color' => '00336C']);
        $run->addText(' — '.$item[1]);
        if ($index < count($items) - 1) {
            $arrow = $table->addRow()->addCell(8600, ['gridSpan' => 2, 'bgColor' => 'FFFFFF']);
            $arrow->addText('↓', ['bold' => true, 'color' => '0B5CAB', 'size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }
    }
    $container->addText('', [], ['spaceAfter' => 20]);
}

$cover = $phpWord->addSection([
    'pageSizeW' => Converter::cmToTwip(21),
    'pageSizeH' => Converter::cmToTwip(29.7),
    'marginTop' => Converter::cmToTwip(2.2),
    'marginRight' => Converter::cmToTwip(2.2),
    'marginBottom' => Converter::cmToTwip(2.2),
    'marginLeft' => Converter::cmToTwip(2.2),
]);

$cover->addText('PEMERINTAH KABUPATEN BANJARNEGARA', ['bold' => true, 'size' => 11, 'color' => $navy], ['alignment' => Jc::CENTER, 'spaceAfter' => 520]);
if (is_file($logoPath)) {
    $cover->addImage($logoPath, ['width' => 88, 'alignment' => Jc::CENTER]);
}
$cover->addText('', [], ['spaceAfter' => 420]);
$cover->addText('MANUAL BOOK', ['bold' => true, 'size' => 22, 'color' => $navy], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);
$cover->addText('PROSES RKPD DAN RENJA', ['bold' => true, 'size' => 25, 'color' => $blue], ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);
$cover->addText('APLIKASI E-SAKIP KABUPATEN BANJARNEGARA', ['bold' => true, 'size' => 13, 'color' => $slate], ['alignment' => Jc::CENTER, 'spaceAfter' => 540]);

$coverLine = $cover->addTable('FlowTable');
$coverLine->addRow()->addCell(9300, ['bgColor' => $navy])->addText('', ['size' => 2], ['spaceAfter' => 0]);
$cover->addText('', [], ['spaceAfter' => 560]);
$cover->addText('Panduan operasional penyusunan, sinkronisasi, persetujuan, dan penetapan dokumen perencanaan tahunan daerah dan perangkat daerah.', ['size' => 11, 'color' => $slate], ['alignment' => Jc::CENTER, 'lineHeight' => 1.35, 'spaceAfter' => 680]);
$cover->addText('Versi September 2026', ['bold' => true, 'size' => 10, 'color' => $teal], ['alignment' => Jc::CENTER, 'spaceAfter' => 60]);
$cover->addText('E-SAKIP Kabupaten Banjarnegara', ['size' => 9.5, 'color' => '64748B'], ['alignment' => Jc::CENTER]);

$section = $phpWord->addSection([
    'pageSizeW' => Converter::cmToTwip(21),
    'pageSizeH' => Converter::cmToTwip(29.7),
    'marginTop' => Converter::cmToTwip(1.8),
    'marginRight' => Converter::cmToTwip(1.8),
    'marginBottom' => Converter::cmToTwip(1.7),
    'marginLeft' => Converter::cmToTwip(1.8),
    'headerHeight' => Converter::cmToTwip(0.7),
    'footerHeight' => Converter::cmToTwip(0.7),
]);

$header = $section->addHeader();
$headerTable = $header->addTable('FlowTable');
$headerTable->addRow();
$headerTable->addCell(6500)->addText('MANUAL BOOK RKPD DAN RENJA', ['bold' => true, 'size' => 8.5, 'color' => $navy], ['spaceAfter' => 0]);
$headerTable->addCell(2800)->addText('E-SAKIP BANJARNEGARA', ['bold' => true, 'size' => 8, 'color' => '64748B'], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

$footer = $section->addFooter();
$footerTable = $footer->addTable('FlowTable');
$footerTable->addRow();
$footerTable->addCell(6500)->addText('Pemerintah Kabupaten Banjarnegara', ['size' => 8, 'color' => '64748B'], ['spaceAfter' => 0]);
$footerTable->addCell(2800)->addPreserveText('Halaman {PAGE} dari {NUMPAGES}', ['size' => 8, 'color' => '64748B'], ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]);

$section->addTitle('Informasi Dokumen', 1);
addDataTable($section,
    ['Elemen', 'Keterangan'],
    [
        ['Judul', 'Manual Book Proses RKPD dan RENJA'],
        ['Aplikasi', 'E-SAKIP Kabupaten Banjarnegara'],
        ['Versi panduan', 'September 2026'],
        ['Ruang lingkup', 'RKPD Awal/Ditetapkan/Perubahan dan RENJA Akhir Draft/Ditetapkan/Perubahan'],
        ['Pengguna utama', 'Admin OPD, Admin Kabupaten/Bapperida, serta pejabat verifikator/pemberi persetujuan'],
    ],
    [2400, 6900],
);

addCallout($section, 'Tujuan panduan', 'Memberikan urutan kerja yang konsisten agar penyusunan RENJA, kompilasi RKPD, sinkronisasi balik, dan penetapan dokumen tidak mengalami kebuntuan status atau menggunakan sumber yang keliru.', 'info');

$section->addTitle('Daftar Isi', 1);
$section->addTOC(['name' => 'Arial', 'size' => 10, 'color' => $slate], ['tabLeader' => 'dot', 'useHyperlink' => true, 'minDepth' => 1, 'maxDepth' => 3]);
$section->addPageBreak();

$section->addTitle('1. Gambaran Umum', 1);
addBody($section, 'RKPD adalah dokumen perencanaan tahunan pemerintah daerah, sedangkan RENJA adalah dokumen perencanaan tahunan masing-masing perangkat daerah. Di aplikasi, keduanya disusun melalui tahapan kerja, sinkronisasi, pemeriksaan, persetujuan, dan penetapan.');
addBody($section, 'Prinsip utama alur terbaru adalah dokumen kerja boleh berubah, sedangkan dokumen resmi yang telah ditetapkan dipertahankan sebagai snapshot dan tidak ditimpa oleh proses sinkronisasi berikutnya.');

$section->addTitle('1.1 Prinsip Data', 2);
addBullet($section, 'Satu tahun dan satu keluarga versi: sinkronisasi hanya mempertemukan dokumen pada tahun serta jenis versi yang sesuai.', true);
addBullet($section, 'Dokumen target harus editable: hanya status Draft, Perlu Revisi, atau Ditolak yang dapat menerima perubahan sinkronisasi.', true);
addBullet($section, 'Dokumen resmi tidak ditimpa: status Disetujui atau Terkunci tidak menjadi target sinkronisasi.', true);
addBullet($section, 'Sinkronisasi bersifat selektif: pengguna memilih baris Baru atau Berbeda yang akan diterapkan.', true);
addBullet($section, 'Baris Hanya Target tidak dihapus otomatis: evaluasi dan penghapusan dilakukan secara sadar oleh pengguna.', true);

$section->addTitle('1.2 Istilah Versi Dokumen', 2);
addDataTable($section,
    ['Dokumen', 'Fungsi', 'Keterangan'],
    [
        ['RKPD Awal', 'Dokumen kerja kabupaten', 'Menampung kompilasi RENJA Akhir Draft. Setelah disetujui, sistem menerbitkan RKPD Ditetapkan.'],
        ['RKPD Ditetapkan', 'Dokumen resmi daerah', 'Snapshot resmi dan aktif; menjadi sumber sinkronisasi RKPD ke RENJA.'],
        ['RKPD Perubahan', 'Dokumen kerja perubahan', 'Dibuat dari dokumen resmi aktif untuk proses perubahan tahun berjalan.'],
        ['RENJA Akhir Draft', 'Dokumen kerja OPD', 'Disusun dan diajukan oleh OPD. Setelah disetujui, tetap tersimpan sebagai Arsip proses.'],
        ['RENJA Ditetapkan', 'Dokumen resmi OPD', 'Dibuat otomatis saat RENJA Akhir Draft disetujui; menjadi sumber resmi pembuatan RKA.'],
        ['RENJA Perubahan', 'Dokumen perubahan OPD', 'Dibuat dari RENJA Ditetapkan aktif dan mengikuti alur perubahan.'],
    ],
    [2000, 2650, 4650],
);
addCallout($section, 'Penting', 'RENJA Akhir Draft dan RENJA Ditetapkan memang tampil sebagai dua dokumen. Ini bukan duplikasi: yang pertama adalah riwayat proses, sedangkan yang kedua adalah dokumen resmi yang digunakan oleh proses turunannya.', 'warning');

$section->addTitle('2. Peran Pengguna', 1);
addDataTable($section,
    ['Peran', 'Tanggung jawab utama'],
    [
        ['Admin OPD', 'Membuat dan melengkapi RENJA, meninjau hasil salinan RENSTRA, mengajukan dokumen, memperbaiki catatan, serta menyelaraskan RENJA dari RKPD Ditetapkan.'],
        ['Admin Kabupaten/Bapperida', 'Mengelola RKPD, mengompilasi RENJA yang telah diajukan/diverifikasi, memeriksa konsistensi lintas OPD, dan menjalankan proses persetujuan sesuai kewenangan.'],
        ['Verifikator/Reviewer', 'Memverifikasi dokumen, meminta perbaikan, menyetujui, menolak, atau mengunci dokumen sesuai hak akses.'],
    ],
    [2800, 6500],
);
addCallout($section, 'Catatan hak akses', 'Nama peran organisasi dapat berbeda sesuai konfigurasi. Tombol hanya muncul jika akun mempunyai izin terhadap modul dan lingkup OPD yang bersangkutan.', 'info');

$section->addTitle('3. Status dan Aksi Dokumen', 1);
addDataTable($section,
    ['Status', 'Arti', 'Aksi umum berikutnya'],
    [
        ['Draft', 'Dokumen sedang disusun dan dapat diedit.', 'Edit, sinkronkan, lalu Ajukan.'],
        ['Diajukan', 'Dokumen telah dikirim untuk diperiksa.', 'Verifikasi, Perbaiki, Tolak, atau Tarik Pengajuan jika diizinkan.'],
        ['Terverifikasi', 'Pemeriksaan awal telah selesai.', 'Setujui, Perbaiki, atau Tolak.'],
        ['Perlu Revisi', 'Dokumen dikembalikan kepada pengelola.', 'Perbaiki/sinkronkan, lalu Ajukan kembali.'],
        ['Ditolak', 'Dokumen ditolak dengan catatan.', 'Perbaiki, lalu Ajukan kembali jika proses dilanjutkan.'],
        ['Disetujui', 'Dokumen telah mendapat persetujuan.', 'Dokumen resmi/snapshot diterbitkan sesuai jenisnya; dapat dikunci.'],
        ['Terkunci', 'Dokumen resmi tidak dapat diubah oleh pengguna biasa.', 'Tidak ada perubahan langsung; gunakan proses koreksi/perubahan resmi.'],
    ],
    [1550, 3850, 3900],
);

$section->addTitle('4. Alur Utama RKPD dan RENJA', 1);
addFlow($section, [
    ['Buat RENJA Akhir Draft', 'Admin OPD membuat dokumen dan meninjau struktur awal yang disalin dari RENSTRA.'],
    ['Ajukan RENJA', 'RENJA Akhir Draft harus berstatus Diajukan atau Terverifikasi agar dapat dikompilasi ke RKPD.'],
    ['Kompilasi ke RKPD', 'Admin Kabupaten/Bapperida menjalankan Sinkronkan dari RENJA pada RKPD yang masih editable.'],
    ['Setujui RKPD', 'Persetujuan RKPD Awal menerbitkan RKPD Ditetapkan sebagai snapshot resmi aktif.'],
    ['Selaraskan RENJA', 'RENJA dikembalikan ke Perlu Revisi bila perlu, lalu menjalankan Sinkronkan dari RKPD.'],
    ['Setujui RENJA', 'Setelah sesuai RKPD resmi, RENJA diajukan dan disetujui.'],
    ['Terbit RENJA Ditetapkan', 'Sistem membuat snapshot resmi; RENJA Akhir Draft tetap tersimpan sebagai Arsip proses.'],
]);

addCallout($section, 'Mengapa alurnya demikian?', 'RKPD perlu menerima usulan RENJA sebelum ditetapkan. Setelah RKPD resmi tersedia, RENJA diselaraskan kembali terhadap keputusan final tersebut, kemudian baru ditetapkan sebagai sumber resmi RKA.', 'success');

$section->addTitle('5. Membuat RENJA Akhir Draft', 1);
$section->addTitle('5.1 Prasyarat', 2);
addBullet($section, 'Periode/tahun anggaran tersedia dan aktif.');
addBullet($section, 'OPD dan unit OPD pengguna sudah sesuai.');
addBullet($section, 'RENSTRA OPD sumber telah Disetujui atau Terkunci.');
addBullet($section, 'RKPD Awal tahap kerja atau RKPD Ditetapkan aktif untuk tahun yang sama tersedia sebagai referensi.');

$section->addTitle('5.2 Langkah Pembuatan', 2);
addStep($section, 1, 'Buka menu RENJA OPD', 'pilih tombol Tambah RENJA Akhir Draft.');
addStep($section, 2, 'Pilih identitas dokumen', 'tentukan tahun, perangkat daerah, unit bila digunakan, RENSTRA OPD, serta RKPD referensi yang sesuai.');
addStep($section, 3, 'Periksa ringkasan', 'pastikan tahun, OPD, RENSTRA, dan RKPD referensi tidak tertukar.');
addStep($section, 4, 'Klik Buat RENJA Akhir Draft', 'modal konfirmasi menjelaskan bahwa sub kegiatan dari RENSTRA akan disalin satu kali sebagai struktur awal.');
addStep($section, 5, 'Konfirmasi pembuatan', 'sistem membuat dokumen dan menyalin sub kegiatan dari RENSTRA. Data dokumen yang telah ada tidak dihapus atau ditimpa.');
addStep($section, 6, 'Tinjau daftar sub kegiatan', 'hapus sub kegiatan yang tidak dilaksanakan pada tahun tersebut, sesuaikan data RENJA yang editable, atau tambah baris manual bila diperlukan.');

addCallout($section, 'Data dari RENSTRA', 'Identitas cascading yang disalin dari RENSTRA ditampilkan sebagai acuan dan tidak diedit dari RENJA. Perubahan struktur sumber dilakukan pada dokumen perencanaan asal melalui prosedur yang berlaku.', 'warning');

$section->addTitle('6. Mengajukan dan Memeriksa RENJA', 1);
addStep($section, 1, 'Validasi isian', 'pastikan indikator, target, satuan, pagu, lokasi, sumber dana, dan keterangan lain telah sesuai kebutuhan tahun berjalan.');
addStep($section, 2, 'Klik Ajukan', 'isi catatan pengajuan bila diperlukan. Status berubah dari Draft/Perlu Revisi/Ditolak menjadi Diajukan.');
addStep($section, 3, 'Lakukan verifikasi', 'reviewer dapat memilih Verifikasi, Perbaiki, atau Tolak. Jika Verifikasi digunakan, status menjadi Terverifikasi.');
addStep($section, 4, 'Tindak lanjuti catatan', 'jika berstatus Perlu Revisi, Admin OPD memperbaiki dokumen dan mengajukan kembali.');

addCallout($section, 'Syarat kompilasi ke RKPD', 'RENJA Akhir Draft yang menjadi sumber harus berstatus Diajukan atau Terverifikasi. RENJA yang masih Draft tidak ditarik ke RKPD.', 'info');

$section->addTitle('7. Sinkronisasi RENJA ke RKPD', 1);
$section->addTitle('7.1 Kondisi yang Diizinkan', 2);
addDataTable($section,
    ['Komponen', 'Ketentuan'],
    [
        ['Target RKPD', 'Status Draft, Perlu Revisi, atau Ditolak.'],
        ['Sumber RENJA', 'Status Diajukan atau Terverifikasi.'],
        ['Tahun', 'Sama dengan tahun target RKPD.'],
        ['Jenis versi', 'RKPD Awal mengambil RENJA Akhir Draft; RKPD Perubahan mengambil RENJA Perubahan.'],
        ['Lingkup', 'Dokumen sumber berada pada keluarga/root versi RKPD yang sama.'],
    ],
    [2600, 6700],
);

$section->addTitle('7.2 Langkah Sinkronisasi', 2);
addStep($section, 1, 'Buka detail RKPD', 'pastikan dokumen target masih editable.');
addStep($section, 2, 'Buka bagian Sinkronisasi RENJA ke RKPD', 'klik Sinkronkan dari RENJA.');
addStep($section, 3, 'Periksa hasil pembandingan', 'tinjau kategori Baru, Berbeda, Sama, dan Hanya Target.');
addStep($section, 4, 'Pilih perubahan', 'centang baris Baru atau Berbeda yang memang akan dimasukkan ke RKPD.');
addStep($section, 5, 'Klik Terapkan ke RKPD', 'sistem memvalidasi ulang sumber dan target, lalu menerapkan pilihan.');
addStep($section, 6, 'Periksa ringkasan hasil', 'aplikasi menampilkan jumlah baris yang diterapkan dan dilewati.');

$section->addTitle('8. Penetapan RKPD', 1);
addStep($section, 1, 'Periksa hasil kompilasi', 'pastikan semua OPD yang wajib masuk telah tersedia dan tidak ada data yang salah versi.');
addStep($section, 2, 'Ajukan RKPD', 'kirim dokumen kerja ke proses pemeriksaan.');
addStep($section, 3, 'Verifikasi dan setujui', 'reviewer menjalankan Verifikasi jika tahap ini digunakan, kemudian Setujui.');
addStep($section, 4, 'Periksa RKPD Ditetapkan', 'sistem menerbitkan snapshot resmi aktif. RKPD Ditetapkan inilah yang menjadi sumber sinkronisasi balik ke RENJA.');

addCallout($section, 'Jangan sinkronkan ke dokumen resmi', 'RKPD Disetujui/Terkunci tidak dapat menjadi target sinkronisasi. Bila perlu perubahan setelah penetapan, gunakan RKPD Perubahan atau mekanisme koreksi resmi.', 'danger');

$section->addTitle('9. Sinkronisasi RKPD ke RENJA', 1);
$section->addTitle('9.1 Kondisi yang Diizinkan', 2);
addDataTable($section,
    ['Komponen', 'Ketentuan'],
    [
        ['Sumber RKPD', 'RKPD Ditetapkan aktif dengan status Disetujui atau Terkunci.'],
        ['Target RENJA', 'Status Draft, Perlu Revisi, atau Ditolak.'],
        ['Tahun/versi', 'Harus sesuai dengan tahun dan keluarga versi target RENJA.'],
        ['OPD/unit', 'Baris sumber difilter hanya untuk OPD target dan unit OPD yang sama bila unit digunakan.'],
    ],
    [2600, 6700],
);

$section->addTitle('9.2 Langkah Sinkronisasi', 2);
addStep($section, 1, 'Siapkan target editable', 'jika RENJA masih Diajukan/Terverifikasi, reviewer memilih Perbaiki agar status menjadi Perlu Revisi.');
addStep($section, 2, 'Buka detail RENJA', 'masuk ke bagian Sinkronisasi RKPD ke RENJA.');
addStep($section, 3, 'Klik Sinkronkan dari RKPD', 'sistem membandingkan RENJA dengan RKPD Ditetapkan aktif.');
addStep($section, 4, 'Pilih baris Baru/Berbeda', 'tinjau perubahan secara sadar, terutama nilai target dan pagu.');
addStep($section, 5, 'Klik Terapkan ke RENJA', 'perubahan terpilih diterapkan; baris Hanya Target tidak dihapus otomatis.');
addStep($section, 6, 'Periksa kembali', 'pastikan struktur program, kegiatan, sub kegiatan, indikator, target, dan pagu telah selaras.');

$section->addTitle('10. Menetapkan RENJA', 1);
addStep($section, 1, 'Selesaikan penyesuaian', 'RENJA harus sudah mencerminkan RKPD Ditetapkan yang aktif.');
addStep($section, 2, 'Ajukan kembali', 'klik Ajukan setelah perbaikan/sinkronisasi selesai.');
addStep($section, 3, 'Verifikasi dan setujui', 'reviewer memeriksa lalu memilih Setujui.');
addStep($section, 4, 'Periksa versi hasil', 'sistem otomatis menerbitkan RENJA Ditetapkan dan mengikatnya ke RKPD Ditetapkan resmi.');
addStep($section, 5, 'Gunakan untuk RKA', 'RKA hanya dibuat dari RENJA Ditetapkan atau RENJA Perubahan yang sudah Disetujui/Terkunci.');

addDataTable($section,
    ['Yang tampil setelah persetujuan', 'Makna'],
    [
        ['RENJA Akhir Draft — Arsip proses', 'Riwayat dokumen kerja dan proses persetujuan. Bukan sumber resmi RKA.'],
        ['RENJA Ditetapkan — Versi aktif', 'Snapshot resmi OPD yang digunakan sebagai sumber RKA.'],
    ],
    [3900, 5400],
);

$section->addTitle('11. Arti Kategori Hasil Sinkronisasi', 1);
addDataTable($section,
    ['Kategori', 'Arti', 'Tindakan'],
    [
        ['Baru', 'Baris ada pada sumber, belum ada pada target.', 'Dapat dipilih untuk dibuat pada target.'],
        ['Berbeda', 'Baris ada pada kedua dokumen, tetapi ada nilai yang tidak sama.', 'Dapat dipilih untuk memperbarui target.'],
        ['Sama', 'Baris sumber dan target sudah konsisten.', 'Tidak perlu diterapkan.'],
        ['Hanya Target', 'Baris hanya ada pada dokumen target.', 'Tidak dihapus otomatis; tinjau dan hapus manual jika memang tidak diperlukan.'],
    ],
    [1600, 4400, 3300],
);

addCallout($section, 'Validasi saat penerapan', 'Jika status sumber atau target berubah setelah preview dibuat, sistem akan memvalidasi ulang. Buat preview baru bila hasil lama sudah tidak sesuai kondisi terkini.', 'warning');

$section->addTitle('12. Alur RKPD dan RENJA Perubahan', 1);
addFlow($section, [
    ['Buat RKPD Perubahan', 'Mulai dari RKPD Ditetapkan aktif; hasilnya berupa dokumen kerja RKPD Perubahan.'],
    ['Buat RENJA Perubahan', 'Mulai dari RENJA Ditetapkan aktif dan gunakan RKPD Perubahan tahap kerja yang sesuai.'],
    ['Ajukan RENJA Perubahan', 'RENJA Perubahan berstatus Diajukan/Terverifikasi menjadi sumber kompilasi.'],
    ['Kompilasi ke RKPD Perubahan', 'Jalankan Sinkronkan dari RENJA pada RKPD Perubahan yang editable.'],
    ['Setujui RKPD Perubahan', 'Dokumen menjadi sumber resmi perubahan tahun berjalan.'],
    ['Selaraskan RENJA Perubahan', 'Kembalikan target ke Perlu Revisi bila diperlukan, lalu sinkronkan dari RKPD Perubahan resmi.'],
    ['Setujui RENJA Perubahan', 'RENJA Perubahan menjadi versi resmi aktif dan dapat digunakan oleh proses anggaran berikutnya.'],
]);

addCallout($section, 'Konsistensi versi', 'RKPD Perubahan hanya mengompilasi RENJA Perubahan. Dokumen versi awal tidak dicampur ke alur perubahan.', 'info');

$section->addTitle('13. Kondisi Bisa dan Tidak Bisa Sinkronisasi', 1);
addDataTable($section,
    ['Skenario', 'Hasil', 'Solusi'],
    [
        ['RKPD Draft ← RENJA Draft', 'Tidak bisa', 'Ajukan atau verifikasi RENJA terlebih dahulu.'],
        ['RKPD Draft ← RENJA Diajukan/Terverifikasi', 'Bisa', 'Buat preview, pilih perubahan, lalu terapkan.'],
        ['RKPD Disetujui/Terkunci sebagai target', 'Tidak bisa', 'Gunakan RKPD Perubahan atau mekanisme koreksi resmi.'],
        ['RENJA Draft/Perlu Revisi ← RKPD Ditetapkan aktif', 'Bisa', 'Buat preview, pilih perubahan, lalu terapkan.'],
        ['RENJA Diajukan/Terverifikasi sebagai target', 'Tidak bisa', 'Reviewer memilih Perbaiki agar RENJA kembali editable.'],
        ['RENJA Disetujui/Terkunci sebagai target', 'Tidak bisa', 'Gunakan RENJA Perubahan atau mekanisme koreksi resmi.'],
        ['Tahun atau versi sumber berbeda', 'Tidak bisa', 'Pilih dokumen pada tahun dan jenis versi yang sama.'],
        ['Tidak ada baris Baru/Berbeda', 'Tidak ada yang diterapkan', 'Dokumen sudah konsisten atau hanya terdapat baris Hanya Target.'],
    ],
    [3600, 1550, 4150],
);

$section->addTitle('14. Penanganan Kendala Umum', 1);
addDataTable($section,
    ['Pesan/kondisi', 'Penyebab umum', 'Tindakan'],
    [
        ['RENJA Akhir Draft yang sudah diajukan atau diverifikasi belum tersedia', 'RENJA masih Draft, berbeda tahun/versi, atau berada di keluarga RKPD lain.', 'Periksa status, tahun, jenis versi, dan referensi RKPD RENJA.'],
        ['RKPD hanya dapat menjadi target ketika Draft, Perlu Revisi, atau Ditolak', 'RKPD sudah Diajukan, Terverifikasi, Disetujui, atau Terkunci.', 'Gunakan dokumen kerja yang editable; jangan menimpa dokumen resmi.'],
        ['RKPD Ditetapkan aktif belum tersedia', 'RKPD Awal belum disetujui atau snapshot resmi belum aktif.', 'Selesaikan persetujuan RKPD dan periksa RKPD Ditetapkan.'],
        ['Tombol Terapkan tidak aktif', 'Belum ada baris Baru/Berbeda yang dipilih.', 'Centang minimal satu baris yang dapat diterapkan.'],
        ['Sebagian baris dilewati', 'Data/status berubah setelah preview atau baris tidak lagi memenuhi syarat.', 'Muat ulang halaman dan buat preview sinkronisasi baru.'],
        ['Data hanya muncul pada target', 'Baris target tidak ditemukan pada sumber.', 'Tinjau manual; hapus hanya jika dipastikan tidak diperlukan.'],
    ],
    [3000, 3150, 3150],
);

$section->addTitle('15. Checklist Operasional', 1);
$section->addTitle('15.1 Sebelum Sinkronisasi RENJA ke RKPD', 2);
addBullet($section, 'RENJA sumber berstatus Diajukan atau Terverifikasi.');
addBullet($section, 'RKPD target berstatus Draft, Perlu Revisi, atau Ditolak.');
addBullet($section, 'Tahun dan jenis versi sesuai.');
addBullet($section, 'Semua OPD yang wajib berkontribusi sudah mengajukan RENJA.');
addBullet($section, 'Preview telah diperiksa sebelum menerapkan perubahan.');

$section->addTitle('15.2 Sebelum Sinkronisasi RKPD ke RENJA', 2);
addBullet($section, 'RKPD Ditetapkan aktif sudah tersedia.');
addBullet($section, 'RENJA target berada pada status editable.');
addBullet($section, 'OPD dan unit OPD target benar.');
addBullet($section, 'Baris Berbeda telah diperiksa, khususnya target dan pagu.');
addBullet($section, 'Baris Hanya Target telah dievaluasi secara manual.');

$section->addTitle('15.3 Sebelum Persetujuan Final RENJA', 2);
addBullet($section, 'Struktur program, kegiatan, dan sub kegiatan konsisten dengan RKPD resmi.');
addBullet($section, 'Indikator, target, satuan, lokasi, sumber dana, dan pagu sudah benar.');
addBullet($section, 'Catatan perbaikan reviewer telah ditindaklanjuti.');
addBullet($section, 'Tidak ada perubahan sinkronisasi yang masih belum diterapkan.');
addBullet($section, 'Pengguna memahami bahwa persetujuan menerbitkan RENJA Ditetapkan dan menyimpan RENJA Akhir Draft sebagai Arsip proses.');

$section->addTitle('16. Ringkasan Cepat', 1);
addDataTable($section,
    ['Tahap', 'Dokumen sumber', 'Dokumen target', 'Status minimum sumber', 'Status target'],
    [
        ['Kompilasi usulan', 'RENJA Akhir Draft', 'RKPD Awal', 'Diajukan/Terverifikasi', 'Draft/Perlu Revisi/Ditolak'],
        ['Penyelarasan final', 'RKPD Ditetapkan aktif', 'RENJA Akhir Draft', 'Disetujui/Terkunci', 'Draft/Perlu Revisi/Ditolak'],
        ['Kompilasi perubahan', 'RENJA Perubahan', 'RKPD Perubahan', 'Diajukan/Terverifikasi', 'Draft/Perlu Revisi/Ditolak'],
        ['Penyelarasan perubahan', 'RKPD Perubahan resmi', 'RENJA Perubahan', 'Disetujui/Terkunci', 'Draft/Perlu Revisi/Ditolak'],
    ],
    [1500, 2100, 2000, 1900, 1800],
);

addCallout($section, 'Urutan singkat', 'RENJA diajukan → dikompilasi ke RKPD Draft → RKPD ditetapkan → RENJA dikembalikan ke status editable → disinkronkan dari RKPD resmi → RENJA ditetapkan → RENJA Ditetapkan menjadi sumber RKA.', 'success');

$section->addText('', [], ['spaceAfter' => 300]);
$section->addText('— Akhir Manual Book —', ['bold' => true, 'size' => 10, 'color' => $navy], ['alignment' => Jc::CENTER, 'spaceBefore' => 300, 'spaceAfter' => 80]);
$section->addText('Dokumen ini mengikuti alur aplikasi E-SAKIP per September 2026.', ['size' => 9, 'color' => '64748B'], ['alignment' => Jc::CENTER]);

IOFactory::createWriter($phpWord, 'Word2007')->save($outputPath);

echo $outputPath.PHP_EOL;
