# Deployment Uji Coba via aaPanel

Panduan ini untuk menjalankan E-SAKIP di server aaPanel sebagai uji coba. Untuk production resmi, tetap gunakan hardening pada `docs/deployment-production.md`.

## 1. Kebutuhan aaPanel

Install komponen berikut dari App Store aaPanel:

- Nginx.
- PHP 8.3 atau lebih baru.
- PostgreSQL.
- Redis.
- Node.js LTS dan npm.
- Composer 2.
- Supervisor Manager, jika tersedia, untuk queue worker.

Aktifkan extension PHP minimal:

- `bcmath`
- `ctype`
- `curl`
- `dom`
- `fileinfo`
- `mbstring`
- `openssl`
- `pdo_pgsql`
- `pgsql`
- `session`
- `tokenizer`
- `xml`
- `zip`

Jika ada upload/generate dokumen bergambar, aktifkan juga `gd`.

## 2. Buat Website di aaPanel

Di aaPanel:

1. Buka `Website`.
2. Klik `Add site`.
3. Isi domain/subdomain uji coba, misalnya `esakip-demo.domain.go.id`.
4. Pilih PHP 8.3+.
5. Root awal boleh dibuat default dulu.

Setelah project diupload, ubah document root menjadi:

```text
/www/wwwroot/e-sakip-bna/public
```

Jangan arahkan website ke root project `/www/wwwroot/e-sakip-bna`, karena Laravel harus masuk dari folder `public`.

## 3. Upload Project

Opsi paling rapi adalah pakai Git:

```bash
cd /www/wwwroot
git clone URL_REPOSITORY_ANDA e-sakip-bna
cd /www/wwwroot/e-sakip-bna
```

Jika belum punya repository Git online, upload ZIP project lewat File Manager aaPanel, lalu extract ke:

```text
/www/wwwroot/e-sakip-bna
```

Untuk upload ZIP, tidak perlu ikutkan:

- `node_modules`
- `vendor`
- `.env`
- `storage/logs/*.log`

## 4. Install Dependency

Masuk ke Terminal aaPanel:

```bash
cd /www/wwwroot/e-sakip-bna
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

Jika server uji coba tidak kuat menjalankan `npm ci && npm run build`, build asset bisa dilakukan di laptop lalu upload folder `public/build`.

## 5. Buat Database PostgreSQL

Di aaPanel PostgreSQL:

1. Buat database, misalnya `e_sakip_bna`.
2. Buat user, misalnya `e_sakip_user`.
3. Set password kuat.
4. Pastikan user punya akses ke database tersebut.

Contoh nilai yang nanti dipakai di `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=e_sakip_bna
DB_USERNAME=e_sakip_user
DB_PASSWORD=ISI_PASSWORD_DATABASE
```

## 6. Konfigurasi .env

Di server:

```bash
cd /www/wwwroot/e-sakip-bna
cp .env.production.example .env
php artisan key:generate
```

Untuk uji coba tanpa SSL, gunakan:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://DOMAIN_ATAU_IP_ANDA

SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database

FILESYSTEM_DISK=local
DOCUMENTS_DISK=local

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Jika sudah pakai SSL/HTTPS:

```dotenv
APP_URL=https://DOMAIN_ANDA
SESSION_DOMAIN=DOMAIN_ANDA
SESSION_SECURE_COOKIE=true
```

Ganti juga password awal super admin sebelum seed:

```dotenv
SUPER_ADMIN_EMAIL=admin@example.test
SUPER_ADMIN_PASSWORD=GANTI_PASSWORD_KUAT
```

## 7. Migration dan Seeder

Untuk server uji coba pertama kali:

```bash
php artisan migrate --seed
```

Jangan pakai `migrate:fresh` kalau database sudah berisi data penting.

Setelah itu jalankan cache Laravel:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

## 8. Permission Folder

Sesuaikan user web server aaPanel. Umumnya `www`.

```bash
cd /www/wwwroot/e-sakip-bna
chown -R www:www storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

Jika upload dokumen gagal, cek permission `storage`.

## 9. Rewrite Nginx Laravel

Di aaPanel:

1. Buka `Website`.
2. Pilih site E-SAKIP.
3. Buka `Rewrite` atau `Config`.
4. Gunakan rule Laravel berikut:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ /\.(?!well-known).* {
    deny all;
}
```

Pastikan `index` berisi:

```nginx
index index.php index.html;
```

Pastikan root site:

```nginx
root /www/wwwroot/e-sakip-bna/public;
```

## 10. Queue Worker

Queue diperlukan untuk proses berat seperti export dokumen.

Jika ada Supervisor Manager di aaPanel, buat program:

```text
Name: e-sakip-queue
Directory: /www/wwwroot/e-sakip-bna
Command: php artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=180 --memory=256
User: www
Autostart: yes
Autorestart: yes
```

Jika tidak ada Supervisor Manager, sementara untuk uji coba bisa jalankan manual:

```bash
cd /www/wwwroot/e-sakip-bna
php artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=180 --memory=256
```

Manual worker akan berhenti kalau terminal ditutup, jadi ini hanya untuk uji coba cepat.

## 11. Scheduler

Tambahkan cron job di aaPanel `Cron`:

```bash
cd /www/wwwroot/e-sakip-bna && php artisan schedule:run >> /dev/null 2>&1
```

Set interval setiap 1 menit.

## 12. SSL

Untuk uji coba publik, sebaiknya tetap aktifkan SSL:

1. aaPanel `Website`.
2. Pilih site.
3. Buka `SSL`.
4. Gunakan Let's Encrypt.
5. Setelah SSL aktif, ubah `.env`:

```dotenv
APP_URL=https://DOMAIN_ANDA
SESSION_DOMAIN=DOMAIN_ANDA
SESSION_SECURE_COOKIE=true
```

Lalu:

```bash
php artisan optimize:clear
php artisan config:cache
```

## 13. Smoke Test

Setelah site bisa dibuka:

1. Buka halaman publik.
2. Klik `Login`.
3. Login dengan super admin.
4. Buka Dashboard.
5. Buka Master OPD.
6. Upload dokumen kecil.
7. Cek halaman publik apakah dokumen tampil.
8. Cek log:

```bash
tail -f storage/logs/laravel.log
```

## 14. Masalah Umum

Jika muncul 403 atau halaman kosong:

- Document root belum diarahkan ke `public`.
- Permission `storage` atau `bootstrap/cache` belum benar.

Jika asset CSS/JS tidak tampil:

- `npm run build` belum dijalankan.
- Folder `public/build` belum ada.
- `APP_URL` tidak sesuai domain.

Jika login kembali ke halaman login terus:

- Cek `SESSION_DOMAIN`.
- Untuk HTTP, pakai `SESSION_SECURE_COOKIE=false`.
- Untuk HTTPS, pakai `SESSION_SECURE_COOKIE=true`.
- Jalankan `php artisan optimize:clear`.

Jika error Redis connection refused:

- Redis belum berjalan di aaPanel.
- Atau sementara ubah:

```dotenv
CACHE_STORE=file
QUEUE_CONNECTION=database
```

Lalu jalankan:

```bash
php artisan optimize:clear
php artisan queue:work database
```

Untuk kondisi normal, tetap direkomendasikan Redis.

