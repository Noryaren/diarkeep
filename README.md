# DiarKeep

Papan catatan ala Google Keep yang ringan, self-hosted, dan ramah server kecil.
Dibuat dengan PHP 8.5 murni (tanpa framework/Composer), MariaDB, dan JavaScript
vanilla. Tidak ada build step, tidak ada layanan pihak ketiga.

## Fitur

- Catatan teks dan checklist dalam satu papan.
- Warna catatan, label, pin, arsip, dan sampah.
- Lampiran gambar dengan thumbnail otomatis (GD), maksimal 5 MB.
- Pencarian judul, isi, checklist, dan label.
- Editor autosave, pintasan keyboard (`/` cari, `n` catatan baru).
- Multi-user: akun dibuat dari server; setiap akun hanya bisa mengakses catatannya sendiri.
- Tema terang/gelap, layout responsif, dan landing page.
- Keamanan dasar: `password_hash`, prepared statement, CSRF token, CSP,
  cookie `HttpOnly` + `SameSite`, validasi unggahan gambar.

## Kebutuhan server

- PHP 8.5+ dengan ekstensi: `pdo_mysql`, `gd`, `mbstring`, `json`, `openssl`,
  `fileinfo`, `session`.
- MariaDB 10.6+ / MySQL 8+ (boleh di Docker).
- Web server yang bisa PHP-FPM (contoh: Caddy).
- Ruang tulis untuk `public/uploads/`.

Target yang sudah dicek: PHP 8.5.12, Caddy 2.11, MariaDB 12 di container.

## Menjalankan lokal

Database lokal memakai container `diarkeep-db` di port `3307`, dan
`config/config.php` sudah diarahkan ke sana (file ini diabaikan Git).

```bash
php -S 127.0.0.1:8099 -t public public/index.php
# buka http://127.0.0.1:8099
```

Impor ulang skema bila database lokal masih kosong:

```bash
docker exec -i diarkeep-db mariadb -udiarkeep -p diarkeep < database/schema.sql
```

Buat akun lokal (tidak ada pendaftaran lewat web):

```bash
php bin/create-user.php "Nama Lokal" lokal@contoh.id
```

## Struktur

```
diarkeep/
├── app/                 # bootstrap, auth, API, view PHP
├── bin/                 # CLI, termasuk create-user.php
├── config/              # config.example.php -> config.php
├── database/schema.sql  # skema MariaDB
├── deploy/              # contoh Caddyfile + tuning PHP-FPM
├── public/              # document root
│   ├── index.php        # front controller
│   ├── assets/          # CSS, JS, logo
│   └── uploads/         # gambar pengguna (jangan dieksekusi)
└── storage/             # cadangan file runtime
```

## Instalasi di server

### 1. Salin aplikasi

Contoh di `/home/diarkeep`:

```bash
sudo mkdir -p /home/diarkeep
sudo rsync -a --delete --exclude 'config/config.php' --exclude 'public/uploads/*' \
  ./ /home/diarkeep/
sudo chown -R www-data:www-data /home/diarkeep/public/uploads
sudo chmod 775 /home/diarkeep/public/uploads
```

Di laptop ini, folder proyeknya adalah `~/Documents/PRIBADI/diarkeep`.
Hindari menaruh `config.php` atau folder `app/` di dalam document root publik.

### 2. Siapkan MariaDB (contoh container)

Di server ini MariaDB berjalan di container `mariadb-main` dengan port host
`3307`. Buat database dan user khusus:

```bash
sudo docker exec -it mariadb-main mariadb -uroot -p
```

```sql
CREATE DATABASE diarkeep CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'diarkeep'@'%' IDENTIFIED BY 'ganti-dengan-password-kuat';
GRANT ALL PRIVILEGES ON diarkeep.* TO 'diarkeep'@'%';
FLUSH PRIVILEGES;
EXIT;
```

Impor skema (dari host, port 3307):

```bash
mariadb -h 127.0.0.1 -P 3307 -u diarkeep -p diarkeep < /home/diarkeep/database/schema.sql
```

Jika MariaDB langsung di host (tanpa container), gunakan port `3306` atau socket.

### 3. Konfigurasi

```bash
sudo cp /home/diarkeep/config/config.example.php /home/diarkeep/config/config.php
sudo nano /home/diarkeep/config/config.php
```

Sesuaikan:

```php
'db' => [
    'dsn'  => 'mysql:host=127.0.0.1;port=3307;dbname=diarkeep;charset=utf8mb4',
    'user' => 'diarkeep',
    'pass' => 'password-yang-tadi',
],
```

Catatan:

- Gunakan `127.0.0.1`, bukan `localhost`, agar PDO memakai TCP ke port container.
- `config/config.php` berisi kredensial dan **tidak boleh** masuk repository
  (sudah ada di `.gitignore`).
- `trust_proxy` biarkan `true` jika di depan Cloudflare Tunnel/Zero Trust.
- `debug` harus `false` di produksi.

### 4. Membuat akun

Pendaftaran lewat web sengaja tidak tersedia. Buat akun dari server:

```bash
sudo php8.5 /home/diarkeep/bin/create-user.php "Nama Kamu" kamu@contoh.id
```

Kata sandi diminta secara interaktif. Untuk otomatisasi, set `DIAR_PASSWORD`
sebelum menjalankan perintah. Ulangi perintah ini untuk setiap akun.

### 5. Caddy

Gabungkan blok dari `deploy/Caddyfile.example` ke `/etc/caddy/Caddyfile`,
ganti domain dan path. Lalu:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

Aplikasi memakai front controller di `public/index.php`. `php_fastcgi` Caddy
sudah menangani fallback ke `index.php`; `file_server` menyajikan aset dan
gambar.

Contoh domain Cloudflare Zero Trust:

```caddyfile
http://keep.contoh.id {
    root * /home/diarkeep/public
    encode gzip zstd
    header {
        X-Forwarded-Proto "https"
        X-Forwarded-For {http.request.remote.host}
        CF-Connecting-IP {http.request.remote.host}
    }
    @blocked path /.* /uploads/*.php /uploads/*.phtml /uploads/*.phar
    respond @blocked 404
    php_fastcgi unix//run/php/php8.5-fpm.sock {
        dial_timeout 30s
        resolve_root_symlink
    }
    file_server
}
```

### 6. Selesai

Buka domain di browser dan masuk memakai akun yang sudah dibuat lewat CLI.
Tidak ada akun bawaan dan tidak ada pendaftaran publik.

Cek kesehatan server:

```bash
curl -s https://keep.contoh.id/api/health
# {"ok":true,"db":true,...}
```

## Tuning untuk RAM 2 GB

Lihat `deploy/php-fpm-tuning.conf`. Intinya:

- `pm = ondemand`, `pm.max_children = 4` (turunkan bila perlu).
- `memory_limit = 128M` cukup untuk unggahan gambar.
- Ukuran unggahan 5 MB di aplikasi; samakan `upload_max_filesize` dan
  `post_max_size` di PHP.
- MariaDB container sudah berjalan; tidak perlu menjalankan instance kedua.

Jangan menambahkan framework/build tool di server. Semua aset sudah statis.

## Backup

Database:

```bash
sudo docker exec mariadb-main mariadb-dump -u diarkeep -p diarkeep \
  > ~/backup-diarkeep-$(date +%F).sql
```

Gambar:

```bash
tar -czf ~/backup-diarkeep-uploads-$(date +%F).tar.gz /home/diarkeep/public/uploads
```

Simpan keduanya di mesin lain. Catatan tanpa gambar tetap bisa dipulihkan dari
dump database.

## Update aplikasi

```bash
# dari laptop
rsync -a --delete --exclude 'config/config.php' --exclude 'public/uploads/*' \
  ./ user@server:/home/diarkeep/
```

Tidak ada build step. Reload Caddy/PHP-FPM hanya diperlukan jika mengubah
konfigurasi server, bukan setiap update aplikasi.

## Catatan keamanan

- Pendaftaran publik dimatikan. Buat akun hanya lewat `bin/create-user.php`.
- Jangan mengarahkan document root ke root proyek. Gunakan `public/`.
- Folder `public/uploads/` hanya berisi gambar dengan nama acak; blokir eksekusi
  PHP di sana lewat Caddy.
- Set `debug => false`. Log PHP tetap ditulis ke log PHP-FPM.
- Gunakan HTTPS di edge (Cloudflare) dan batasi akses bila perlu dengan
  Cloudflare Zero Trust.
- Ganti password database contoh. Jangan memakai password SSH/sudo yang sama.

## Pintasan

| Tombol | Fungsi |
| --- | --- |
| `/` | Fokus ke pencarian |
| `n` | Catatan baru |
| `Esc` | Tutup editor/popover/menu |
| `Ctrl`/`Cmd` + `Enter` | Tutup editor (menyimpan) |
