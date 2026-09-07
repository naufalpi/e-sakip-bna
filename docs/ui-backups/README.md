# Cadangan public site sebelum desain September 2026

Arsip `public-site-before-2026-09-07.zip` menyimpan seluruh direktori
`resources/js/pages/PublicSite` sebelum perubahan. Termasuk halaman utama,
komponen, CSS, tipe data, utilitas, dan animasi lama.

- Commit asal: `6fef94f2c7818f5d57768aa2212e3357b171d564`
- SHA256 ZIP: `81E57AAC4BA866267839A1E85E1F2D004642117020F4BDFD266036CF79E5B111`
- Cadangan ini ikut version control sehingga tetap tersedia setelah push/pull.

## Mengembalikan desain lama

Simpan perubahan terbaru terlebih dahulu bila ingin tetap memilikinya.
Ekstrak ZIP ke direktori sementara. Di dalamnya ada folder `PublicSite`.
Salin isi folder tersebut ke `resources/js/pages/PublicSite`, menggantikan
file dengan nama sama, lalu jalankan `npm run build`.

Komponen baru `Portal*.vue`, `portal-redesign.css`, serta font lokal boleh
tetap ada; halaman lama tidak mengimpornya. Tidak perlu mengubah database.

## Desain baru

Portal editorial terang dengan Plus Jakarta Sans lokal (lisensi OFL),
warna biru tua/hijau, empat kartu siklus, angka statistik dari backend,
filter tahun di beranda, dan halaman dokumen responsif. Perubahan hanya
berlaku pada public site.
