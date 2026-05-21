# Deployment Production E-SAKIP Kabupaten Banjarnegara

Dokumen ini menyiapkan baseline deployment untuk Linux server dengan Nginx, PHP-FPM, PostgreSQL, Redis, Laravel queue worker, scheduler, dan private storage.

## Komponen Server

- Linux server LTS.
- Nginx.
- PHP 8.3+ dengan ekstensi: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo_pgsql`, `pgsql`, `session`, `tokenizer`, `xml`, `zip`.
- Composer 2.
- Node.js LTS dan npm untuk build asset.
- PostgreSQL.
- Redis.

## Environment

1. Salin `.env.production.example` menjadi `.env`.
2. Isi `APP_KEY` dengan hasil:

```bash
php artisan key:generate --show
```

3. Ganti semua nilai `CHANGE_ME`.
4. Pastikan:

```dotenv
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
DOCUMENTS_DISK=local
```

Jika dokumen memakai MinIO/S3-compatible, ubah:

```dotenv
DOCUMENTS_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=e-sakip-documents
AWS_ENDPOINT=https://minio.example.go.id
AWS_USE_PATH_STYLE_ENDPOINT=true
```

## Build dan Release

Jangan menjalankan `migrate:fresh` di production.

```bash
cd /var/www/e-sakip-bna
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
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

## Nginx

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

Aktifkan site lalu reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## Queue Worker

LKJIP/LHE export dan proses berat harus lewat queue. Buat `/etc/systemd/system/e-sakip-queue.service`:

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

Setelah deploy kode baru:

```bash
php artisan queue:restart
```

## Scheduler

Tambahkan cron untuk user web server:

```cron
* * * * * cd /var/www/e-sakip-bna && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## PostgreSQL Backup

Contoh backup harian:

```bash
mkdir -p /backup/e-sakip
PGPASSWORD='CHANGE_ME' pg_dump -h 127.0.0.1 -U e_sakip_user -Fc e_sakip_bna > /backup/e-sakip/e_sakip_bna_$(date +%F_%H%M).dump
find /backup/e-sakip -type f -name '*.dump' -mtime +14 -delete
```

Restore ke database kosong:

```bash
PGPASSWORD='CHANGE_ME' pg_restore -h 127.0.0.1 -U e_sakip_user -d e_sakip_bna --clean --if-exists /backup/e-sakip/file.dump
```

## Redis

Minimal Redis dipakai untuk:

- cache dashboard dan query berat,
- queue dokumen,
- maintenance cache.

Pastikan Redis hanya listen lokal atau dilindungi firewall. Jika Redis memakai password, isi `REDIS_PASSWORD`.

## Private Storage

Default `local` menyimpan dokumen di `storage/app/private`. Download harus lewat route Laravel yang sudah menerapkan authorization. Jangan expose direktori `storage/app/private` lewat Nginx.

Untuk MinIO/S3-compatible, gunakan bucket private dan download tetap lewat controller Laravel.

## Checklist Smoke Test Setelah Deploy

1. Login sebagai super admin.
2. Buka dashboard.
3. Buka master OPD dan role permission.
4. Upload dokumen bukti dukung kecil.
5. Jalankan export LKJIP/LHE dan pastikan queue worker membuat dokumen.
6. Cek notifikasi masuk dan badge unread muncul.
7. Cek log:

```bash
tail -f storage/logs/laravel.log
journalctl -u e-sakip-queue -f
```
