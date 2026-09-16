# 🖼️ HS15 Web Gallery

> Arsip digital foto dan video untuk mendokumentasikan momen kebersamaan serta perjalanan Komunitas HS15 - Komunitas Keliling Banjar.

![Status](https://img.shields.io/badge/status-active-2f855a?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-8%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![Database](https://img.shields.io/badge/MySQL-compatible-4479a1?style=flat-square&logo=mysql&logoColor=white)

## ✨ Tentang Proyek

HS15 Web Gallery adalah aplikasi galeri berbasis PHP yang awalnya dibuat pada 5 Juni 2023 sebagai galeri ilustrasi. Setelah sempat berhenti, proyek ini dilanjutkan dan diarahkan menjadi arsip dokumentasi foto dan video Komunitas HS15.

Tampilan aplikasi terinspirasi dari situs galeri modern, dengan fokus pada navigasi sederhana, akses media yang nyaman, dan pengelolaan akun pengguna.

## 🚀 Fitur

- 🔐 **Autentikasi pengguna**: daftar akun, login, logout, dan reset password.
- 🏠 **Beranda galeri**: akses cepat menuju galeri foto dan video.
- 📸 **Galeri foto**: thumbnail otomatis, caption dari nama file, pagination, dan lightbox fullscreen.
- 🎬 **Galeri video**: pemutar video bawaan browser, poster thumbnail, dan pagination.
- 👤 **Setelan akun**: ganti foto profil, email, password, serta hapus akun.
- 🛡️ **Keamanan dasar**: password disimpan menggunakan `password_hash()` dan query database menggunakan prepared statement.
- 📱 **Tampilan responsif**: tersedia stylesheet terpisah untuk halaman autentikasi, galeri, akun, dan navigasi.

## 🧰 Teknologi

| Komponen | Teknologi |
| --- | --- |
| Backend | PHP 8 atau lebih baru |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript |
| Pemrosesan gambar | PHP GD |
| Ikon | Ionicons melalui CDN |
| Server lokal | Laragon atau web server PHP lain |
| Thumbnail video | FFmpeg (opsional) |

## 📁 Struktur Folder

```text
project-root/
├── account.php          # Setelan dan pengelolaan akun
├── connect.php          # Konfigurasi koneksi database
├── fgpass.php           # Reset password
├── gallery.php          # Galeri foto
├── index.php            # Halaman login
├── login.php            # Proses login
├── logout.php           # Proses logout
├── register.php         # Pendaftaran akun
├── vidgallery.php       # Galeri video
├── choose.html          # Beranda setelah login
├── *.css                # Style tiap halaman
├── *.js                 # Interaksi frontend dan navigasi
├── gallery/             # File foto dan video
│   └── thumbs/          # Thumbnail yang dibuat otomatis
└── img/
    └── profiles/        # Foto profil pengguna
```

## ✅ Persyaratan

Pastikan perangkat sudah memiliki:

- Web server lokal seperti **Laragon**, **XAMPP**, **WAMP**, atau konfigurasi Apache/Nginx setara.
- MySQL atau MariaDB yang sedang berjalan.
- PHP 8+ dengan ekstensi `mysqli` dan `gd` aktif.
- Browser modern yang mendukung pemutar video HTML5.
- FFmpeg jika ingin membuat thumbnail video secara otomatis.

## 🛠️ Instalasi

1. Salin seluruh folder proyek ke **document root** web server lokal yang digunakan. Contohnya adalah folder `htdocs`, `www`, atau folder root lain sesuai konfigurasi server.
2. Jalankan layanan **web server** dan **MySQL/MariaDB**.
3. Buat satu database kosong melalui phpMyAdmin, HeidiSQL, Adminer, atau perangkat administrasi database lain.
4. Pilih database tersebut, lalu buat tabel pengguna dengan SQL berikut:

```sql
CREATE TABLE users (
	id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	email VARCHAR(255) NOT NULL UNIQUE,
	password VARCHAR(255) NOT NULL,
	profile_photo VARCHAR(255) NULL,
	role VARCHAR(30) NOT NULL DEFAULT 'member',
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

5. Buka `connect.php`, lalu sesuaikan host, username, password, dan nama database dengan konfigurasi lokal Anda:

```php
$host = "alamat-host-database";
$user = "username-database";
$pass = "password-database";
$db   = "nama-database";
```

6. Buka alamat lokal proyek melalui browser, misalnya `http://localhost/nama-folder-proyek/`.

> 💡 `account.php` juga dapat menambahkan kolom akun yang belum tersedia secara otomatis, tetapi pembuatan tabel dasar tetap perlu dilakukan terlebih dahulu.

## 🗂️ Menambahkan Media

### Foto

Simpan foto dengan format `JPG`, `JPEG`, `PNG`, `GIF`, atau `WEBP` ke folder `gallery/`. Thumbnail akan dibuat otomatis di `gallery/thumbs/` saat galeri dibuka.

### Video

Simpan video dengan format `MP4`, `WEBM`, atau `OGG` ke folder `gallery/`. Jika ingin menampilkan poster thumbnail, letakkan `ffmpeg.exe` di folder utama proyek. Tanpa FFmpeg, video tetap dapat diputar tetapi tidak memiliki thumbnail otomatis.

Nama file bertimestamp seperti `IMG_20240915_103000.jpg` atau `VID_20240915_103000.mp4` akan ditampilkan sebagai caption tanggal dan waktu.

## 🧭 Alur Penggunaan

```text
Daftar akun -> Login -> Beranda
                       ├── Galeri Foto
                       ├── Galeri Video
                       └── Setelan Akun
```

## 📝 Catatan Pengembangan

- Jangan menyimpan file rahasia atau kredensial produksi di repository.
- Pastikan folder `gallery/` dan `img/profiles/` memiliki izin tulis agar thumbnail serta foto profil dapat dibuat.
- Untuk deployment publik, gunakan password database yang kuat dan nonaktifkan konfigurasi development yang tidak diperlukan.

## 📄 Lisensi

Proyek ini dibuat untuk kebutuhan dokumentasi Komunitas HS15. Aturan penggunaan dan distribusi dapat ditambahkan sesuai kesepakatan pemilik proyek.
