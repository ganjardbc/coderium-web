# Deploy Laravel Project ke VPS dengan Nginx

Panduan ini merangkum langkah-langkah deploy project Laravel (contoh: `coderium-web`) ke VPS menggunakan **Nginx + PHP-FPM**.

---

# 1. Persiapan Server

Pastikan beberapa komponen sudah terinstall di VPS:

* Nginx
* PHP + PHP-FPM
* Composer
* Node.js + NPM
* Database (MySQL / PostgreSQL)

Cek versi penting:

```bash
php -v
composer -V
node -v
nginx -v
```

---

# 2. Upload / Clone Project

Masukkan project ke server, misalnya:

```bash
git clone https://github.com/username/coderium-web.git
```

Contoh lokasi project:

```
/home/ubuntu/coderium-web
```

> Project **tidak harus berada di `/var/www`**, bisa di folder mana saja.

---

# 3. Install Dependency

Masuk ke folder project:

```bash
cd coderium-web
```

Install dependency backend:

```bash
composer install
```

Install dependency frontend:

```bash
npm install
```

Build asset:

```bash
npm run build
```

---

# 4. Setup Environment

Copy file environment:

```bash
cp .env.example .env
```

Generate key Laravel:

```bash
php artisan key:generate
```

---

# 5. Migrasi Database

Jalankan migrasi database:

```bash
php artisan migrate
```

Jika ada seeder:

```bash
php artisan db:seed
```

---

# 6. Konfigurasi Permission Laravel

Laravel membutuhkan permission khusus untuk folder tertentu.

```bash
sudo chown -R www-data:www-data /home/ubuntu/coderium-web
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache
```

---

# 7. Konfigurasi Nginx

Masuk ke folder konfigurasi nginx:

```bash
cd /etc/nginx/sites-available
```

Buat file config:

```bash
sudo nano coderium-web
```

Isi konfigurasi:

```nginx
server {
    listen 80;
    server_name _;

    root /home/ubuntu/coderium-web/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

> Penting: Laravel harus diarahkan ke folder **`public`**

---

# 8. Aktifkan Site di Nginx

Buat symbolic link:

```bash
sudo ln -s /etc/nginx/sites-available/coderium-web /etc/nginx/sites-enabled/
```

---

# 9. Test Konfigurasi Nginx

Jalankan:

```bash
sudo nginx -t
```

Jika berhasil akan muncul:

```
syntax is ok
test is successful
```

---

# 10. Restart Nginx

```bash
sudo systemctl restart nginx
```

---

# 11. Cek PHP-FPM Socket

Kadang error terjadi karena versi PHP berbeda.

Cek socket PHP:

```bash
ls /run/php
```

Contoh output:

```
php8.2-fpm.sock
php8.3-fpm.sock
```

Jika yang tersedia:

```
php8.3-fpm.sock
```

ubah konfigurasi nginx menjadi:

```nginx
fastcgi_pass unix:/run/php/php8.3-fpm.sock;
```

---

# 12. Error Umum dan Solusinya

## Error: 502 Bad Gateway

Biasanya PHP-FPM tidak berjalan.

Restart PHP-FPM:

```bash
sudo systemctl restart php8.2-fpm
```

---

## Error: 403 Forbidden

Biasanya karena permission folder.

Perbaiki permission:

```bash
sudo chown -R www-data:www-data project-folder
```

---

## Error: 404 Laravel

Biasanya karena konfigurasi `try_files` salah.

Pastikan ada:

```nginx
try_files $uri $uri/ /index.php?$query_string;
```

---

# 13. Error Nginx: sites-enabled/default

Error:

```
open() "/etc/nginx/sites-enabled/default" failed
```

Artinya nginx mencoba membaca file `default` yang tidak ada.

Solusi:

Hapus symlink default:

```bash
sudo rm /etc/nginx/sites-enabled/default
```

Test lagi:

```bash
sudo nginx -t
```

---

# 14. Debug Log Nginx

Untuk melihat error secara realtime:

```bash
sudo tail -f /var/log/nginx/error.log
```

atau

```bash
sudo tail -f /var/log/nginx/access.log
```

---

# 15. Struktur Nginx yang Umum

```
/etc/nginx
 ├── nginx.conf
 ├── sites-available
 │    └── coderium-web
 └── sites-enabled
      └── coderium-web -> ../sites-available/coderium-web
```

---

# 16. Akses Website

Jika belum menggunakan domain, akses melalui IP VPS:

```
http://IP_SERVER
```

---

# 17. Improvement untuk Production

Untuk production environment, biasanya ditambahkan:

* SSL (Let's Encrypt)
* Queue Worker
* Supervisor
* CI/CD Deployment
* Auto Deploy dari Git
* Caching (Redis)

---

# Selesai 🎉

Jika semua langkah benar, project Laravel sudah bisa diakses melalui **IP VPS atau domain** menggunakan **Nginx + PHP-FPM**.
