# E-SAKIP Kabupaten Banjarnegara

E-SAKIP Kabupaten Banjarnegara adalah aplikasi akuntabilitas kinerja pemerintah daerah berbasis Laravel, Inertia.js, Vue 3, TypeScript, PostgreSQL, dan Redis. Aplikasi ini dibangun untuk mendukung siklus SAKIP secara end-to-end, mulai dari perencanaan kinerja, pengukuran capaian, pelaporan LKJIP, evaluasi SAKIP, dokumen bukti dukung, workflow persetujuan, hingga audit log.

## Daftar Isi

- [Fitur Utama](#fitur-utama)
- [Stack Teknologi](#stack-teknologi)
- [Requirement Development](#requirement-development)
- [Requirement Production](#requirement-production)
- [Struktur Project](#struktur-project)
- [Instalasi Development](#instalasi-development)
- [Menjalankan Aplikasi Development](#menjalankan-aplikasi-development)
- [Akun Awal](#akun-awal)
- [Konfigurasi Environment](#konfigurasi-environment)
- [Database dan Seeder](#database-dan-seeder)
- [Storage Dokumen](#storage-dokumen)
- [Queue, Cache, dan Redis](#queue-cache-dan-redis)
- [Testing dan Quality Check](#testing-dan-quality-check)
- [Instalasi Production](#instalasi-production)
- [Nginx Production](#nginx-production)
- [Queue Worker Production](#queue-worker-production)
- [Scheduler Production](#scheduler-production)
- [Backup dan Restore PostgreSQL](#backup-dan-restore-postgresql)
- [Troubleshooting](#troubleshooting)

## Fitur Utama

### Portal Publik

- Landing page publik E-SAKIP.
- Tabel publik per siklus: Perencanaan, Pengukuran, Pelaporan, dan Evaluasi.
- Filter tahun dan pencarian perangkat daerah.
- Dokumen publik dapat dilihat dan diunduh melalui route Laravel, bukan direct public upload.

### Auth dan Authorization

- Login berbasis session Laravel.
- Role dan permission custom berbasis database.
- Role awal:
  - `super_admin`
  - `admin_kabupaten_bagian_organisasi`
  - `admin_kabupaten_bapperida`
  - `admin_kabupaten_inspektorat`
  - `admin_kabupaten_dinkominfo`
  - `admin_opd`
  - `pimpinan`
- Middleware, policy, dan gate untuk pembatasan akses.
- User OPD dibatasi pada data OPD masing-masing.

### Master Data

- OPD.
- Unit OPD.
- User.
- Role dan permission.
- Periode tahun.
- Satuan indikator.
- Urusan pemerintahan.
- Pengaturan sistem.
- Audit log.

### Perencanaan Kinerja

- RPJMD Kabupaten.
- Visi, misi, tujuan daerah, indikator tujuan, sasaran daerah, indikator sasaran, strategi, program RPJMD, indikator program, target tahunan, target triwulan, dan OPD penanggung jawab.
- Struktur RPJMD Banjarnegara: tujuan daerah turun dari visi, sedangkan misi tetap dicatat sebagai elemen RPJMD.
- Renstra OPD dan cascading OPD.
- Keterhubungan Renstra OPD ke RPJMD Kabupaten.
- Pohon kinerja kabupaten dan OPD.
- Import RPJMD dan Renstra berbasis template.
- Revisi target formal.
- Validasi hierarki perencanaan.

### Pengukuran Kinerja

- Perjanjian Kinerja.
- Rencana Aksi.
- Realisasi Kinerja.
- Realisasi Program.
- Target dan realisasi anggaran.
- Capaian indikator positif dan negatif.
- Status capaian: merah, kuning, hijau.
- Perhitungan serapan anggaran dan efisiensi.

### Pelaporan Kinerja

- LKJIP.
- Bab LKJIP.
- Generate draft LKJIP.
- Export dokumen laporan.
- Dokumen hasil generate disimpan melalui Laravel Storage.

### Evaluasi SAKIP

- Komponen, sub komponen, dan kriteria evaluasi.
- Evaluasi SAKIP per OPD dan periode.
- Nilai evaluator, catatan, rekomendasi, LHE, dan tindak lanjut.
- Predikat evaluasi tersimpan di database.
- Bobot awal:
  - Perencanaan Kinerja: 30
  - Pengukuran Kinerja: 30
  - Pelaporan Kinerja: 15
  - Evaluasi Internal: 25

### Workflow, Notifikasi, dan Audit

- Workflow generic untuk RPJMD, Renstra OPD, PK, Rencana Aksi, Realisasi Kinerja, LKJIP, Evaluasi SAKIP, dan Tindak Lanjut Rekomendasi.
- Status workflow:
  - `draft`
  - `submitted`
  - `revision`
  - `verified`
  - `approved`
  - `rejected`
  - `locked`
- Workflow history.
- Notification untuk reviewer/user terkait.
- Activity log untuk perubahan data penting.
- Data locked tidak dapat diedit oleh user biasa.

## Stack Teknologi

- Backend: Laravel 13.
- Frontend: Inertia.js 2 + Vue 3.
- Bahasa frontend: TypeScript.
- Styling: Tailwind CSS.
- Database: PostgreSQL.
- Cache dan queue: Redis.
- Storage dokumen: Laravel Filesystem, default local/private, dapat diarahkan ke MinIO/S3-compatible.
- PDF/Word: Dompdf dan PHPWord.
- Alert UI: SweetAlert2.
- Icon: Lucide Vue.
- Build tool: Vite.

## Requirement Development

Minimal software yang perlu tersedia di komputer development:

- Git.
- PHP 8.3 atau lebih baru.
- Composer 2.
- Node.js LTS, disarankan Node.js 20 atau 22.
- npm.
- PostgreSQL, disarankan PostgreSQL 15 atau lebih baru.
- Redis, disarankan Redis 7 atau lebih baru.
- Browser modern: Chrome, Edge, Firefox, atau sejenisnya.

Ekstensi PHP yang dibutuhkan:

- `bcmath`
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `filter`
- `hash`
- `mbstring`
- `openssl`
- `pdo_pgsql`
- `pgsql`
- `session`
- `tokenizer`
- `xml`
- `zip`

Untuk Windows, Laragon boleh digunakan sebagai environment PHP dan Nginx/Apache lokal. PostgreSQL dan Redis tetap perlu tersedia, baik dari installer resmi, Laragon add-on, Docker, WSL, atau service lain yang dapat diakses dari aplikasi.

## Requirement Production

Target deployment production:

- Linux server LTS.
- Nginx.
- PHP-FPM 8.3+.
- Composer 2.
- Node.js LTS dan npm untuk build asset.
- PostgreSQL.
- Redis.
- Supervisor/systemd untuk queue worker.
- Cron untuk Laravel scheduler.
- Storage private lokal, MinIO, atau S3-compatible.
- HTTPS aktif.

## Struktur Project

Ringkasan direktori penting:

```text
app/
  Http/Controllers/      Controller Laravel
  Http/Requests/         Form Request validation
  Models/                Model Eloquent
  Policies/              Policy authorization
  Services/              Business logic dan service domain
database/
  migrations/            Migration PostgreSQL-compatible
  seeders/               Seeder role, permission, user awal, master awal, dan demo data
resources/js/
  components/            Komponen Vue reusable
  composables/           Composable Vue
  layouts/               Layout Inertia
  pages/                 Halaman Inertia Vue
routes/
  web.php                Route aplikasi web dan portal publik
storage/
  app/private/           Storage dokumen private default
docs/
  deployment-production.md
```

## Instalasi Development

### 1. Clone repository

```bash
git clone <url-repository> e-sakip-bna
cd e-sakip-bna
```

Jika project sudah ada di lokal, cukup masuk ke folder project:

```bash
cd C:\Users\NAUFAL\WEB\e-sakip-bna
```

### 2. Install dependency PHP

```bash
composer install
```

### 3. Install dependency frontend

Gunakan `npm ci` jika ingin install sesuai `package-lock.json`:

```bash
npm ci
```

Atau gunakan:

```bash
npm install
```

### 4. Buat file `.env`

```bash
copy .env.example .env
```

Di Linux/macOS:

```bash
cp .env.example .env
```

### 5. Generate APP_KEY

```bash
php artisan key:generate
```

### 6. Buat database PostgreSQL

Contoh lewat `psql`:

```sql
CREATE DATABASE e_sakip_bna;
```

Jika menggunakan user selain `postgres`, buat user dan grant sesuai kebutuhan:

```sql
CREATE USER e_sakip_user WITH PASSWORD 'password_kuat';
GRANT ALL PRIVILEGES ON DATABASE e_sakip_bna TO e_sakip_user;
```

### 7. Sesuaikan konfigurasi database di `.env`

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=e_sakip_bna
DB_USERNAME=postgres
DB_PASSWORD=
```

### 8. Siapkan Redis

Pastikan Redis berjalan di:

```dotenv
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

Jika Redis belum tersedia untuk development sementara, gunakan fallback database:

```dotenv
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Fallback ini hanya untuk development. Production tetap disarankan memakai Redis.

### 9. Jalankan migration dan seeder

Untuk setup awal development:

```bash
php artisan migrate:fresh --seed
```

Jika ingin menambahkan data dummy/demo:

```bash
php artisan db:seed --class=DemoDataSeeder
```

### 10. Buat symbolic link storage

```bash
php artisan storage:link
```

Catatan: dokumen penting tetap disimpan melalui disk private dan diunduh lewat controller Laravel.

## Menjalankan Aplikasi Development

Cara paling sederhana adalah menjalankan backend Laravel dan Vite frontend di dua terminal.

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

Buka aplikasi di browser:

```text
http://127.0.0.1:8000
```

Jika queue menggunakan Redis/database dan ingin proses queue berjalan:

```bash
php artisan queue:work
```

Project juga menyediakan script Composer untuk menjalankan server, queue, log, dan Vite sekaligus:

```bash
composer run dev
```

Script tersebut menjalankan:

- `php artisan serve`
- `php artisan queue:listen --tries=1`
- `php artisan pail --timeout=0`
- `npm run dev`

## Akun Awal

Seeder membuat user super admin awal dari konfigurasi `.env`:

```dotenv
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_USERNAME=superadmin
SUPER_ADMIN_EMAIL=admin@example.test
SUPER_ADMIN_PASSWORD=password
```

Default login development:

```text
Username/email: superadmin atau admin@example.test
Password: password
```

Untuk production, wajib ubah password sebelum menjalankan seeder atau segera setelah login pertama.

## Konfigurasi Environment

Contoh utama tersedia di:

- `.env.example` untuk development.
- `.env.production.example` untuk production.

Variabel penting:

```dotenv
APP_NAME="E-SAKIP Kabupaten Banjarnegara"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=e_sakip_bna
DB_USERNAME=postgres
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=redis
QUEUE_CONNECTION=redis

FILESYSTEM_DISK=local
DOCUMENTS_DISK=local

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

MAIL_MAILER=log
VITE_APP_NAME="${APP_NAME}"
```

## Database dan Seeder

Migration utama meliputi:

- Auth, users, roles, permissions, pivot role-user, pivot permission-role.
- OPD, unit OPD, periode tahun, satuan indikator, urusan pemerintahan, system settings.
- RPJMD Kabupaten.
- Renstra OPD.
- Perjanjian Kinerja, Rencana Aksi, Realisasi Kinerja.
- Dokumen dan dokumen relations.
- LKJIP.
- Evaluasi SAKIP, LHE, rekomendasi, tindak lanjut.
- Workflow submissions, workflow histories, notifications, activity logs.
- Import batches dan import batch rows.
- Target triwulan, predikat evaluasi, revisi target, dan metrik bisnis SAKIP.

Perintah penting:

```bash
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed
php artisan db:seed --class=DemoDataSeeder
```

Jangan gunakan `migrate:fresh` di production karena akan menghapus seluruh data.

## Storage Dokumen

Dokumen disimpan melalui Laravel Filesystem.

Default:

```dotenv
FILESYSTEM_DISK=local
DOCUMENTS_DISK=local
```

Untuk dokumen private lokal, file berada di storage aplikasi dan akses download melewati route/controller yang menerapkan authorization.

Jika menggunakan MinIO/S3-compatible:

```dotenv
DOCUMENTS_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=e-sakip-documents
AWS_ENDPOINT=https://minio.example.go.id
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Jangan menyimpan dokumen penting langsung di `public/uploads`.

## Queue, Cache, dan Redis

Redis dipakai untuk:

- Cache dashboard dan query berat.
- Queue proses dokumen/generate laporan.
- Workflow/notifikasi yang membutuhkan proses async.

Development:

```bash
php artisan queue:work
```

Production:

```bash
php artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=180 --memory=256
```

Setelah deploy kode baru:

```bash
php artisan queue:restart
```

Jika muncul error:

```text
No connection could be made because the target machine actively refused it [tcp://127.0.0.1:6379]
```

Artinya Redis belum berjalan atau konfigurasi Redis di `.env` tidak sesuai. Jalankan Redis, atau untuk development sementara ubah:

```dotenv
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Lalu jalankan:

```bash
php artisan optimize:clear
```

## Testing dan Quality Check

Jalankan seluruh test:

```bash
php artisan test
```

Jalankan test tertentu:

```bash
php artisan test --filter=RpjmdAccessTest
```

Build asset production:

```bash
npm run build
```

Format frontend:

```bash
npm run format
```

Lint frontend:

```bash
npm run lint
```

Clear cache Laravel:

```bash
php artisan optimize:clear
```

## Instalasi Production

Contoh direktori production:

```bash
cd /var/www
git clone <url-repository> e-sakip-bna
cd e-sakip-bna
```

Install dependency:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

Buat environment:

```bash
cp .env.production.example .env
php artisan key:generate
```

Isi semua nilai `CHANGE_ME` di `.env`, terutama:

- `APP_URL`
- `APP_KEY`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `SUPER_ADMIN_PASSWORD`
- `REDIS_PASSWORD` jika Redis memakai password
- konfigurasi mail
- konfigurasi storage dokumen

Jalankan migration:

```bash
php artisan migrate --force
php artisan db:seed --force
```

Optimasi Laravel:

```bash
php artisan storage:link
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permission direktori:

```bash
sudo chown -R www-data:www-data /var/www/e-sakip-bna/storage /var/www/e-sakip-bna/bootstrap/cache
sudo chmod -R ug+rwX /var/www/e-sakip-bna/storage /var/www/e-sakip-bna/bootstrap/cache
```

Setiap deploy berikutnya, gunakan pola aman:

```bash
git pull
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

## Nginx Production

Contoh server block:

```nginx
server {
    listen 80;
    server_name e-sakip.banjarnegarakab.go.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name e-sakip.banjarnegarakab.go.id;
    root /var/www/e-sakip-bna/public;
    index index.php index.html;

    client_max_body_size 50M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Cek dan reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Queue Worker Production

Buat service systemd:

```bash
sudo nano /etc/systemd/system/e-sakip-queue.service
```

Isi:

```ini
[Unit]
Description=E-SAKIP Laravel Queue Worker
After=network.target redis-server.service postgresql.service

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/e-sakip-bna
ExecStart=/usr/bin/php artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=180 --memory=256
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Aktifkan:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now e-sakip-queue
sudo systemctl status e-sakip-queue
```

Lihat log:

```bash
journalctl -u e-sakip-queue -f
```

## Scheduler Production

Tambahkan cron untuk user web server:

```cron
* * * * * cd /var/www/e-sakip-bna && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## Backup dan Restore PostgreSQL

Backup:

```bash
mkdir -p /backup/e-sakip
PGPASSWORD='CHANGE_ME' pg_dump -h 127.0.0.1 -U e_sakip_user -Fc e_sakip_bna > /backup/e-sakip/e_sakip_bna_$(date +%F_%H%M).dump
```

Hapus backup lama lebih dari 14 hari:

```bash
find /backup/e-sakip -type f -name '*.dump' -mtime +14 -delete
```

Restore ke database kosong:

```bash
PGPASSWORD='CHANGE_ME' pg_restore -h 127.0.0.1 -U e_sakip_user -d e_sakip_bna --clean --if-exists /backup/e-sakip/file.dump
```

## Troubleshooting

### Halaman tidak berubah setelah edit frontend

Pastikan Vite berjalan:

```bash
npm run dev
```

Jika production:

```bash
npm run build
php artisan optimize:clear
```

### Error koneksi PostgreSQL

Periksa service PostgreSQL dan isi `.env`:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=e_sakip_bna
DB_USERNAME=postgres
DB_PASSWORD=
```

Lalu clear cache:

```bash
php artisan optimize:clear
```

### Error Redis refused connection

Jalankan Redis atau ubah sementara ke database queue/cache untuk development:

```dotenv
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Lalu:

```bash
php artisan optimize:clear
```

### Login gagal setelah ubah akun `.env`

Seeder tidak otomatis mengubah password jika tidak dijalankan ulang. Jalankan:

```bash
php artisan db:seed
```

Untuk development reset penuh:

```bash
php artisan migrate:fresh --seed
```

### File upload atau download gagal

Periksa permission folder:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Periksa juga `DOCUMENTS_DISK` dan konfigurasi S3/MinIO jika digunakan.

### Production masih memakai cache lama

Jalankan:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

## Dokumentasi Tambahan

Dokumentasi deployment production yang lebih fokus tersedia di:

```text
docs/deployment-production.md
```

## Catatan Keamanan

- Jangan commit file `.env`.
- Jangan gunakan password default di production.
- Jangan expose `storage/app/private` lewat Nginx.
- Jangan menjalankan `migrate:fresh` di production.
- Gunakan HTTPS di production.
- Pastikan Redis dan PostgreSQL tidak terbuka ke publik tanpa proteksi firewall.
- Lakukan backup PostgreSQL berkala.
