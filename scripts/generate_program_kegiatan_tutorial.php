<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\VerticalJc;

$projectRoot = dirname(__DIR__);
$outputDirectory = $projectRoot.'/docs';
$outputPath = $outputDirectory.'/Tutorial_Penambahan_Program_Kegiatan_dan_Sub_Kegiatan.docx';
$logoPath = $projectRoot.'/public/images/logo-banjarnegara-1800.png';

if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
    throw new RuntimeException('Tidak dapat membuat direktori output: '.$outputDirectory);
}

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Arial');
$phpWord->setDefaultFontSize(10.5);

$phpWord->addTitleStyle(1, [
    'name' => 'Arial',
    'size' => 17,
    'bold' => true,
    'color' => '00336C',
], [
    'spaceBefore' => 100,
    'spaceAfter' => 170,
    'keepNext' => true,
]);
$phpWord->addTitleStyle(2, [
    'name' => 'Arial',
    'size' => 13,
    'bold' => true,
    'color' => '00336C',
], [
    'spaceBefore' => 100,
    'spaceAfter' => 110,
    'keepNext' => true,
]);
$phpWord->addTitleStyle(3, [
    'name' => 'Arial',
    'size' => 11,
    'bold' => true,
    'color' => '0F766E',
], [
    'spaceBefore' => 80,
    'spaceAfter' => 80,
    'keepNext' => true,
]);

$phpWord->addParagraphStyle('Body', [
    'alignment' => Jc::BOTH,
    'lineSpacing' => 1.12,
    'spaceAfter' => 90,
]);
$phpWord->addParagraphStyle('Compact', [
    'lineSpacing' => 1.05,
    'spaceAfter' => 45,
]);
$phpWord->addParagraphStyle('Step', [
    'lineSpacing' => 1.08,
    'spaceAfter' => 70,
    'leftIndent' => 150,
]);
$phpWord->addParagraphStyle('Caption', [
    'alignment' => Jc::CENTER,
    'spaceBefore' => 45,
    'spaceAfter' => 100,
]);

$sectionStyle = [
    'paperSize' => 'A4',
    'marginTop' => Converter::cmToTwip(1.55),
    'marginBottom' => Converter::cmToTwip(1.45),
    'marginLeft' => Converter::cmToTwip(1.7),
    'marginRight' => Converter::cmToTwip(1.7),
];

$section = $phpWord->addSection($sectionStyle);

$addFooter = static function ($section): void {
    $footer = $section->addFooter();
    $paragraph = $footer->addTextRun([
        'alignment' => Jc::CENTER,
        'spaceBefore' => 40,
    ]);
    $paragraph->addText('Tutorial Program/Kegiatan  |  E-SAKIP Kabupaten Banjarnegara  |  ', [
        'name' => 'Arial',
        'size' => 8,
        'color' => '64748B',
    ]);
    $paragraph->addField('PAGE');
};

$addFooter($section);

$addRule = static function ($container, string $color = '0F766E'): void {
    $table = $container->addTable([
        'width' => 100 * 50,
        'unit' => 'pct',
        'borderSize' => 0,
        'cellMargin' => 0,
    ]);
    $table->addRow(35, ['exactHeight' => true]);
    $cell = $table->addCell(null, [
        'bgColor' => $color,
        'borderSize' => 0,
        'valign' => VerticalJc::CENTER,
    ]);
    $cell->addText('');
};

$addCallout = static function ($container, string $title, string $text, string $accent = '0F766E', string $fill = 'ECFDF5'): void {
    $table = $container->addTable([
        'width' => 100 * 50,
        'unit' => 'pct',
        'borderSize' => 8,
        'borderColor' => $accent,
        'cellMargin' => 130,
    ]);
    $cell = $table->addRow()->addCell(null, [
        'bgColor' => $fill,
        'valign' => VerticalJc::CENTER,
    ]);
    $run = $cell->addTextRun(['spaceAfter' => 35]);
    $run->addText($title.'  ', [
        'bold' => true,
        'color' => $accent,
    ]);
    $run->addText($text, ['color' => '1E293B']);
    $container->addText('', [], ['spaceAfter' => 20]);
};

$addStep = static function ($container, int $number, string $title, string $detail): void {
    $table = $container->addTable([
        'width' => 100 * 50,
        'unit' => 'pct',
        'borderSize' => 0,
        'cellMargin' => 70,
    ]);
    $table->addRow();
    $numberCell = $table->addCell(650, [
        'bgColor' => '00336C',
        'valign' => VerticalJc::CENTER,
        'borderSize' => 0,
    ]);
    $numberCell->addText((string) $number, [
        'bold' => true,
        'size' => 11,
        'color' => 'FFFFFF',
    ], [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
    ]);
    $textCell = $table->addCell(null, [
        'bgColor' => 'F8FAFC',
        'valign' => VerticalJc::CENTER,
        'borderSize' => 0,
    ]);
    $run = $textCell->addTextRun(['spaceAfter' => 15]);
    $run->addText($title.'. ', ['bold' => true, 'color' => '0F172A']);
    $run->addText($detail, ['color' => '334155']);
    $container->addText('', [], ['spaceAfter' => 15]);
};

$addScreenshotPlaceholder = static function ($container, int $number, string $subject): void {
    $table = $container->addTable([
        'width' => 100 * 50,
        'unit' => 'pct',
        'borderSize' => 12,
        'borderColor' => '94A3B8',
        'cellMargin' => 160,
    ]);
    $cell = $table->addRow(2300, ['exactHeight' => false])->addCell(null, [
        'bgColor' => 'F8FAFC',
        'valign' => VerticalJc::CENTER,
    ]);
    $cell->addText('TEMPAT SCREENSHOT '.$number, [
        'bold' => true,
        'size' => 12,
        'color' => '64748B',
    ], [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 100,
    ]);
    $cell->addText($subject, [
        'italic' => true,
        'size' => 9.5,
        'color' => '64748B',
    ], [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 65,
    ]);
    $cell->addText('Klik di dalam kotak ini, hapus teks placeholder, lalu pilih Insert > Pictures untuk memasukkan screenshot.', [
        'size' => 8.5,
        'color' => '94A3B8',
    ], [
        'alignment' => Jc::CENTER,
        'spaceAfter' => 0,
    ]);
    $container->addText('Gambar '.$number.'. '.$subject, [
        'italic' => true,
        'size' => 8.5,
        'color' => '64748B',
    ], 'Caption');
};

$addBullet = static function ($container, string $text, int $level = 0): void {
    $container->addListItem($text, $level, [
        'name' => 'Arial',
        'size' => 10.5,
        'color' => '1E293B',
    ], [
        'listType' => 3,
    ], [
        'spaceAfter' => 45,
        'lineSpacing' => 1.05,
    ]);
};

// Cover
$coverTable = $section->addTable([
    'width' => 100 * 50,
    'unit' => 'pct',
    'borderSize' => 0,
    'cellMargin' => 120,
]);
$coverTable->addRow();
$logoCell = $coverTable->addCell(2100, ['valign' => VerticalJc::CENTER, 'borderSize' => 0]);
if (is_file($logoPath)) {
    $logoCell->addImage($logoPath, [
        'height' => Converter::cmToPixel(2.55),
        'alignment' => Jc::CENTER,
    ]);
}
$identityCell = $coverTable->addCell(null, ['valign' => VerticalJc::CENTER, 'borderSize' => 0]);
$identityCell->addText('PEMERINTAH KABUPATEN BANJARNEGARA', [
    'bold' => true,
    'size' => 12,
    'color' => '00336C',
], ['spaceAfter' => 35]);
$identityCell->addText('E-SAKIP', [
    'bold' => true,
    'size' => 19,
    'color' => '0F766E',
], ['spaceAfter' => 0]);

$section->addText('', [], ['spaceAfter' => 250]);
$addRule($section, '00336C');
$section->addText('', [], ['spaceAfter' => 550]);
$section->addText('TUTORIAL SINGKAT', [
    'bold' => true,
    'size' => 14,
    'color' => '0F766E',
], [
    'alignment' => Jc::CENTER,
    'spaceAfter' => 120,
]);
$section->addText('PENAMBAHAN PROGRAM, KEGIATAN, DAN SUB KEGIATAN', [
    'bold' => true,
    'size' => 23,
    'color' => '00336C',
], [
    'alignment' => Jc::CENTER,
    'lineSpacing' => 1.05,
    'spaceAfter' => 170,
]);
$section->addText('Menu Program/Kegiatan', [
    'bold' => true,
    'size' => 14,
    'color' => '334155',
], [
    'alignment' => Jc::CENTER,
    'spaceAfter' => 80,
]);
$section->addText('Panduan pengisian hierarki dan metadata Sub Kegiatan secara lengkap', [
    'size' => 11,
    'color' => '64748B',
], [
    'alignment' => Jc::CENTER,
    'spaceAfter' => 850,
]);

$coverInfo = $section->addTable([
    'width' => 100 * 50,
    'unit' => 'pct',
    'borderSize' => 8,
    'borderColor' => 'CBD5E1',
    'cellMargin' => 140,
]);
$coverInfoCell = $coverInfo->addRow()->addCell(null, ['bgColor' => 'F8FAFC']);
$coverInfoCell->addText('Dokumen kerja • ringkas • dapat dilengkapi screenshot secara manual', [
    'size' => 9.5,
    'color' => '475569',
], ['alignment' => Jc::CENTER, 'spaceAfter' => 30]);
$coverInfoCell->addText('Versi September 2026', [
    'bold' => true,
    'size' => 9.5,
    'color' => '00336C',
], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

$section->addPageBreak();

// Overview
$section->addTitle('1. Tujuan dan Alur Singkat', 1);
$section->addText(
    'Tutorial ini digunakan untuk menambah referensi Program, Kegiatan, dan Sub Kegiatan pada menu Program/Kegiatan. Data harus mengikuti nomenklatur resmi dan disusun berurutan dari Program sampai Sub Kegiatan.',
    [],
    'Body'
);

$addCallout(
    $section,
    'Alur utama:',
    'pilih periode RPJMD → tambah Program → buka Program → tambah Kegiatan → buka Kegiatan → tambah Sub Kegiatan → lengkapi metadata → simpan dan periksa.'
);

$section->addTitle('Sebelum mulai', 2);
$addBullet($section, 'Pastikan akun memiliki hak kelola menu Program/Kegiatan.');
$addBullet($section, 'Siapkan nomenklatur resmi: kode, nama, Bidang Urusan, Program, Kegiatan, dan Sub Kegiatan.');
$addBullet($section, 'Pastikan Periode RPJMD/Periode Tahun yang dipilih sudah benar.');
$addBullet($section, 'Pastikan Satuan Indikator yang diperlukan sudah tersedia di master satuan.');

$section->addTitle('Hierarki data', 2);
$hierarchy = $section->addTable([
    'width' => 100 * 50,
    'unit' => 'pct',
    'borderSize' => 8,
    'borderColor' => 'CBD5E1',
    'cellMargin' => 105,
]);
$header = $hierarchy->addRow();
foreach (['Urutan', 'Data', 'Induk/ruang lingkup'] as $label) {
    $header->addCell(null, ['bgColor' => '00336C'])->addText($label, [
        'bold' => true,
        'color' => 'FFFFFF',
        'size' => 9.5,
    ], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
}
foreach ([
    ['1', 'Program', 'Periode RPJMD dan Bidang Urusan'],
    ['2', 'Kegiatan', 'Program dan Periode Tahun'],
    ['3', 'Sub Kegiatan', 'Kegiatan dan Periode Tahun'],
] as $rowData) {
    $row = $hierarchy->addRow();
    foreach ($rowData as $index => $value) {
        $row->addCell()->addText($value, ['size' => 9.5], [
            'alignment' => $index === 0 ? Jc::CENTER : Jc::LEFT,
            'spaceAfter' => 0,
        ]);
    }
}

$section->addText('', [], ['spaceAfter' => 100]);
$addScreenshotPlaceholder($section, 1, 'Halaman awal menu Program/Kegiatan dan pemilihan periode');

$section->addPageBreak();

// Program
$section->addTitle('2. Menambah Program', 1);
$addStep($section, 1, 'Buka menu Program/Kegiatan', 'Masuk ke menu Program/Kegiatan dari navigasi aplikasi.');
$addStep($section, 2, 'Pilih Periode RPJMD', 'Gunakan filter periode di bagian atas. Pastikan rentang Tahun Awal–Tahun Akhir sesuai dokumen perencanaan.');
$addStep($section, 3, 'Klik Tambah Program', 'Form Tambah Program akan tampil pada halaman yang sama.');
$addStep($section, 4, 'Lengkapi data', 'Pilih Bidang Urusan, isi Kode, pilih Status, lalu isi Nama Program sesuai nomenklatur resmi.');
$addStep($section, 5, 'Klik Simpan Data', 'Periksa kembali periode, kode, nama, dan Bidang Urusan sebelum menyimpan.');

$section->addTitle('Field Program', 2);
$programFields = $section->addTable([
    'width' => 100 * 50,
    'unit' => 'pct',
    'borderSize' => 8,
    'borderColor' => 'CBD5E1',
    'cellMargin' => 95,
]);
$row = $programFields->addRow();
foreach (['Field', 'Cara mengisi'] as $label) {
    $row->addCell(null, ['bgColor' => 'E2E8F0'])->addText($label, ['bold' => true, 'size' => 9.5], ['spaceAfter' => 0]);
}
foreach ([
    ['Bidang Urusan', 'Pilih bidang yang menaungi Program.'],
    ['Kode', 'Isi kode resmi; tidak boleh ganda dalam ruang lingkup periode dan bidang yang sama.'],
    ['Nama Program', 'Isi nama lengkap sesuai nomenklatur resmi.'],
    ['Status', 'Pilih Aktif jika dapat digunakan; Tidak aktif jika hanya disimpan sebagai arsip/referensi lama.'],
] as $field) {
    $row = $programFields->addRow();
    $row->addCell(2500)->addText($field[0], ['bold' => true, 'size' => 9.3], ['spaceAfter' => 0]);
    $row->addCell()->addText($field[1], ['size' => 9.3], ['spaceAfter' => 0]);
}

$section->addText('', [], ['spaceAfter' => 90]);
$addScreenshotPlaceholder($section, 2, 'Form Tambah Program yang sudah diisi lengkap');

$section->addPageBreak();

// Kegiatan
$section->addTitle('3. Menambah Kegiatan', 1);
$addStep($section, 1, 'Temukan Program', 'Pada Daftar Program, cari Program yang menjadi induk Kegiatan.');
$addStep($section, 2, 'Buka daftar Kegiatan', 'Klik informasi jumlah/tautan Kegiatan pada baris Program. Sistem akan menampilkan Daftar Kegiatan milik Program tersebut.');
$addStep($section, 3, 'Pilih Periode Tahun', 'Pastikan tahun Kegiatan yang dipilih sudah benar.');
$addStep($section, 4, 'Klik Tambah Kegiatan', 'Konteks Program induk akan ditampilkan pada form.');
$addStep($section, 5, 'Lengkapi Kode, Status, dan Nama Kegiatan', 'Gunakan kode serta nama resmi. Jangan membuat Kegiatan di bawah Program yang salah.');
$addStep($section, 6, 'Klik Simpan Data', 'Periksa Program induk dan Periode Tahun sebelum menyimpan.');

$addCallout(
    $section,
    'Pemeriksaan penting:',
    'nama Program yang mirip tidak selalu merupakan Program yang sama. Cocokkan kode, Bidang Urusan, periode, dan konteks Program sebelum menambah Kegiatan.',
    'B45309',
    'FFF7ED'
);

$addScreenshotPlaceholder($section, 3, 'Daftar Kegiatan pada Program terpilih dan Form Tambah Kegiatan');

$section->addPageBreak();

// Sub Kegiatan
$section->addTitle('4. Menambah Sub Kegiatan', 1);
$addStep($section, 1, 'Temukan Kegiatan', 'Pada Daftar Kegiatan, cari Kegiatan yang menjadi induk Sub Kegiatan.');
$addStep($section, 2, 'Buka daftar Sub Kegiatan', 'Klik informasi jumlah/tautan Sub Kegiatan pada baris Kegiatan.');
$addStep($section, 3, 'Klik Tambah Sub Kegiatan', 'Periksa konteks Program dan Kegiatan yang tampil pada bagian atas form.');
$addStep($section, 4, 'Isi identitas utama', 'Lengkapi Kode, Status, dan Nama Sub Kegiatan sesuai nomenklatur resmi.');
$addStep($section, 5, 'Isi Metadata Sub Kegiatan', 'Lengkapi Sasaran Sub Kegiatan, seluruh Indikator beserta Satuan, dan Definisi Operasional.');
$addStep($section, 6, 'Klik Simpan Data', 'Simpan hanya setelah seluruh metadata diperiksa dengan checklist pada bagian akhir tutorial.');

$addScreenshotPlaceholder($section, 4, 'Form Tambah Sub Kegiatan: identitas, induk Kegiatan, dan status');

$section->addPageBreak();

// Metadata
$section->addTitle('5. Mengisi Metadata Sub Kegiatan Secara Lengkap', 1);
$section->addText(
    'Walaupun sebagian metadata dapat dibiarkan kosong oleh sistem, untuk kebutuhan tata kelola data seluruh field berikut harus diisi lengkap sebelum Sub Kegiatan digunakan pada dokumen perencanaan.',
    [],
    'Body'
);

$section->addTitle('A. Sasaran Sub Kegiatan', 2);
$addBullet($section, 'Isi hasil yang ingin dicapai dari pelaksanaan Sub Kegiatan, bukan mengulang nama aktivitas.');
$addBullet($section, 'Gunakan kalimat hasil, misalnya: “Meningkatnya ketersediaan layanan informasi publik.”');
$addBullet($section, 'Pastikan Sasaran selaras dengan Kegiatan induknya.');

$section->addTitle('B. Indikator dan Satuan', 2);
$addBullet($section, 'Isi indikator yang terukur dan benar-benar menunjukkan pencapaian Sasaran Sub Kegiatan.');
$addBullet($section, 'Indikator pertama adalah Indikator utama. Indikator ini menjadi acuan utama untuk RKA/DPA.');
$addBullet($section, 'Klik Tambah indikator jika diperlukan. Sistem mendukung maksimal 40 indikator untuk satu Sub Kegiatan.');
$addBullet($section, 'Setiap indikator wajib memiliki satuan yang sesuai, misalnya Persen, Dokumen, Orang, Layanan, Unit, atau Kegiatan.');
$addBullet($section, 'Jangan memilih satuan hanya agar form dapat disimpan. Pastikan jenis satuan sesuai cara pengukuran indikator.');
$addBullet($section, 'Contoh pasangan: “Persentase layanan informasi publik yang tersedia” — satuan: Persen.');

$section->addTitle('C. Definisi Operasional (DO)', 2);
$addBullet($section, 'Jelaskan arti indikator secara tegas agar pengguna lain menghitung objek yang sama.');
$addBullet($section, 'Cantumkan objek yang dihitung, ruang lingkup, periode pengukuran, serta batasan data yang masuk/tidak masuk.');
$addBullet($section, 'Jika relevan, tuliskan rumus atau cara hitung dan sumber data di dalam uraian DO.');
$addBullet($section, 'Contoh ringkas: “Persentase dihitung dari jumlah layanan informasi publik yang tersedia dibagi seluruh layanan yang ditetapkan pada tahun berjalan, dikali 100 persen.”');

$addCallout(
    $section,
    'Catatan indikator:',
    'seluruh indikator disalin ke RENSTRA, sedangkan indikator pertama diperlakukan sebagai indikator utama/baku untuk acuan RKA dan DPA.'
);

$addScreenshotPlaceholder($section, 5, 'Metadata Sub Kegiatan: Sasaran, Indikator, Satuan, dan Definisi Operasional');

$section->addPageBreak();

// Checklist
$section->addTitle('6. Checklist Sebelum Menyimpan', 1);
$checklist = $section->addTable([
    'width' => 100 * 50,
    'unit' => 'pct',
    'borderSize' => 8,
    'borderColor' => 'CBD5E1',
    'cellMargin' => 100,
]);
$row = $checklist->addRow();
foreach (['Cek', 'Yang harus dipastikan'] as $label) {
    $row->addCell(null, ['bgColor' => '00336C'])->addText($label, [
        'bold' => true,
        'color' => 'FFFFFF',
        'size' => 9.5,
    ], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
}
foreach ([
    ['☐', 'Periode RPJMD/Periode Tahun sudah benar.'],
    ['☐', 'Program dan Kegiatan induk sudah benar.'],
    ['☐', 'Kode resmi dan tidak ganda pada induk/periode yang sama.'],
    ['☐', 'Nama Program/Kegiatan/Sub Kegiatan sama dengan nomenklatur resmi.'],
    ['☐', 'Sasaran Sub Kegiatan sudah berupa hasil yang ingin dicapai.'],
    ['☐', 'Semua indikator terisi, tidak ganda, dan indikator utama sudah benar.'],
    ['☐', 'Setiap indikator sudah mempunyai satuan yang tepat.'],
    ['☐', 'Definisi Operasional cukup jelas untuk digunakan menghitung indikator.'],
    ['☐', 'Status Aktif/Tidak aktif sudah sesuai penggunaan data.'],
] as $item) {
    $row = $checklist->addRow();
    $row->addCell(900)->addText($item[0], ['size' => 11, 'color' => '0F766E'], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $row->addCell()->addText($item[1], ['size' => 9.5], ['spaceAfter' => 0]);
}

$section->addTitle('Hal yang perlu dihindari', 2);
$addBullet($section, 'Jangan memakai Input Cepat untuk Sub Kegiatan yang harus langsung lengkap. Input Cepat hanya mengisi kode dan nama; metadata harus diedit satu per satu setelahnya.');
$addBullet($section, 'Jangan menempatkan Kegiatan atau Sub Kegiatan pada induk yang salah.');
$addBullet($section, 'Jangan membuat kode ganda dalam induk dan periode yang sama.');
$addBullet($section, 'Jangan mengaktifkan referensi yang masih belum lengkap atau belum diverifikasi.');

$addCallout(
    $section,
    'Selesai:',
    'setelah data tersimpan, buka kembali baris Sub Kegiatan dan pastikan indikator, satuan, serta Definisi Operasional tampil dengan benar.',
    '00336C',
    'EFF6FF'
);

$section->addTitle('Cara mengganti placeholder screenshot', 2);
$section->addText(
    'Klik di dalam kotak placeholder → blok dan hapus teksnya → pilih Insert/Sisipkan → Pictures/Gambar → pilih file screenshot → atur lebar gambar agar tetap berada di dalam kotak. Kotak dapat dihapus jika tidak diperlukan.',
    [],
    'Body'
);

IOFactory::createWriter($phpWord, 'Word2007')->save($outputPath);

echo $outputPath.PHP_EOL;
