# 🖼️ HS15 Web Gallery & Archive

> Website arsip digital foto dan video kegiatan komunitas **HS15 - Komunitas Keliling Banjar**.

![Status](https://img.shields.io/badge/status-active-2f855a?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?style=for-the-badge&logo=php&logoColor=white)
![Database](https://img.shields.io/badge/MySQL-MariaDB-4479a1?style=for-the-badge&logo=mysql&logoColor=white)
![Frontend](https://img.shields.io/badge/Vanilla-JS%20%7C%20CSS3-f7df1e?style=for-the-badge)

---

## 📌 Tentang Proyek

**HS15 Web Gallery** berawal dari galeri sederhana yang kemudian dikembangkan menjadi platform arsip digital privat komunitas. Dibuat murni menggunakan **PHP Native** dan **MySQL** tanpa framework berat, web ini punya performa gesit, hemat resource, dan mudah dimodifikasi sesuai kebutuhan.

Tampilannya mengusung konsep **Dark Glassmorphism** modern bernuansa merah-hitam, navigasi yang intuitif, serta tata letak yang sudah dioptimalkan agar tetap rapi saat diakses dari layar smartphone.

---

## ✨ Fitur-Fitur Utama

### 📸 1. Galeri Foto
- **Auto Thumbnail WebP**: Gambar otomatis dikompres dan dibuatkan thumbnail format WebP lewat PHP GD, jadi loading halaman tetap ngebut tanpa nguras kuota.
- **Lightbox Interaktif**: Klik foto untuk melihat ukuran penuh, lengkap dengan info tanggal, jam, judul, tombol download, serta navigasi keyboard (panah kiri/kanan & ESC).
- **Paginasi Rapi**: Pembagian halaman yang konsisten dengan tombol navigasi lengkap (awal, sebelumnya, nomor halaman, berikutnya, akhir).

### 🎬 2. Galeri Video
- **Smooth Streaming**: Pemutaran video lancar dengan dukungan *HTTP Range Requests*, bikin video bisa di-*seek* maju-mundur tanpa macet.
- **Support Video Portrait & Landscape**: Video format vertikal (9:16 ala TikTok/Reels) tetap tampil proporsional tanpa terpotong berkat area pemutar adaptif.
- **Poster Thumbnail Otomatis**: Bisa otomatis mengambil cuplikan frame video jadi gambar sampul jika FFmpeg terpasang.

### 👑 3. Panel Admin (Khusus Admin)
- **Manajemen Media Lengkap**: Upload foto/video banyak sekaligus (batch upload), edit judul, tanggal kegiatan, jam, serta deskripsi momen.
- **Filter & Pencarian Cepat**: Cari media berdasarkan judul, filter tipe (foto/video), dan urutkan (terbaru, terlama, atau abjad A-Z).
- **Hapus Media & Bersih Otomatis**: Hapus satu per satu atau borongan. File asli beserta thumbnail di folder server otomatis ikut terhapus bersih.
- **Kelola Pengguna & Hak Akses**: Lihat daftar member yang terdaftar, ubah role akun (Admin ↔ Member), dan hapus akun pengguna yang tidak aktif.
- **Tabel Rapi & Presisi**: Tampilan daftar pengguna dengan avatar dan email yang sejajar lurus ke bawah.

### 🛡️ 4. Keamanan & Proteksi Data
- **Anti Brute-force Login**: Dibatasi maksimal 5x percobaan gagal per 15 menit per IP/email untuk mencegah pembobolan akun.
- **CSRF Token Guard**: Semua form penting (login, register, ganti data, upload, hapus) dilindungi token CSRF sekali pakai.
- **Proteksi Media Langsung**: Folder media diproteksi `.htaccess` dan dialirkan lewat `media.php`. Orang luar tidak bisa asal copas link file tanpa login.
- **Password Aman**: Hashing password menggunakan algoritma standar industri `password_hash()` (Bcrypt).
- **Prepared Statements**: Mencegah celah SQL Injection di seluruh query database.

### 👤 5. Profil & Akun Pengguna
- Halaman pengaturan akun untuk ganti foto profil, ubah email, dan ganti password.
- Fitur hapus akun mandiri dengan verifikasi password demi keamanan.
- Sistem menu titik tiga (⋮) yang ringkas di mobile agar tampilan header tidak sesak.

---

## 🧰 Teknologi yang Dipakai

| Bagian | Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend** | PHP 8.0+ | Native PHP, OOP & Procedural |
| **Database** | MySQL / MariaDB | Relasional database dengan indexing optimal |
| **Frontend** | HTML5, CSS3, Vanilla JS | Desain glassmorphism, responsive, tanpa dependency luar |
| **Icons** | Ionicons (CDN) | Ikon modern dan tajam di segala resolusi |
| **Pengolahan Gambar**| PHP GD Extension | Resize gambar & convert WebP otomatis |
| **Thumbnail Video** | FFmpeg *(opsional)* | Mengambil frame poster otomatis dari file video |
| **Environment** | Laragon / XAMPP | Web server Apache/Nginx di Windows/Linux |

---

## 📁 Struktur Direktori

```text
project_hs15/
├── index.php                 # Pintu masuk utama (redirect otomatis ke login/beranda)
├── README.md                 # Dokumentasi proyek yang sedang kamu baca ini
├── css/                      # Kumpulan stylesheet tampilan
│   ├── background.css        # Efek latar belakang & partikel
│   ├── global.css            # Variabel warna, font, navbar, & komponen umum
│   ├── index.css             # Desain halaman login, register, & lupa password
│   ├── choose.css            # Desain halaman beranda / pilih galeri
│   ├── gallery.css           # Desain grid galeri foto & lightbox
│   ├── vidgallery.css        # Desain galeri video & pemutar player
│   ├── admin.css             # Desain dashboard panel kontrol admin
│   └── account.css           # Desain halaman setting akun profil
├── js/                       # Kumpulan script interaktivitas
│   ├── nav.js                # Logika menu responsif & dropdown titik 3
│   ├── gallery.js            # Lightbox foto, shortcut keyboard, swipe
│   ├── vidgallery.js         # Kontrol kustom player video
│   ├── auth.js               # Validasi form autentikasi & toggle password
│   └── choose.js             # Efek hover & animasi kartu beranda
├── php/                      # Logika backend & halaman aplikasi
│   ├── index.php             # Form login akun
│   ├── register.php          # Form pendaftaran akun baru
│   ├── fgpass.php            # Halaman permintaan reset password
│   ├── reset_password.php    # Form input password baru via token
│   ├── login.php             # Handler proses autentikasi login
│   ├── logout.php            # Handler keluar sesi / destroy session
│   ├── choose.php            # Halaman beranda utama setelah login
│   ├── gallery.php           # Halaman galeri foto
│   ├── vidgallery.php        # Halaman galeri video
│   ├── admin.php             # Dashboard panel admin & manajemen media
│   ├── account.php           # Pengaturan profil, ganti foto, & hapus akun
│   ├── media.php             # Gateway streaming aman untuk file media
│   ├── profile_image.php     # Endpoint serving foto profil pengguna
│   ├── connect.php           # Konfigurasi koneksi database MySQL
│   ├── security_helper.php   # Fungsi keamanan (CSRF, brute-force, RBAC, migrasi)
│   └── stats_helper.php      # Helper kalkulasi jumlah foto & video
├── gallery/                  # Folder penyimpanan foto & video yang diupload
│   ├── .htaccess             # Blokir akses langsung dari luar (forward ke media.php)
│   └── thumbs/               # Cache thumbnail gambar (.webp) & video (.jpg)
└── img/                      # Aset gambar statis website
    ├── logo.jpg              # Logo resmi HS15
    └── profiles/             # Folder foto profil pengguna
```

---

## 🚀 Panduan Instalasi & Menjalankan Web

Kamu bisa menjalankan web ini di komputer lokal (Localhost) maupun langsung dideploy ke server hosting (cPanel/VPS). Pilih panduan yang sesuai di bawah ini:

---

### 💻 A. Menjalankan di Komputer Lokal (Laragon / XAMPP)

1. **Letakkan Folder Proyek**
   Pindahkan atau clone repositori ini ke folder root web server kamu:
   - **Laragon**: `C:\laragon\www\project_hs15\`
   - **XAMPP**: `C:\xampp\htdocs\project_hs15\`

2. **Nyalakan Web Server & Database**
   Buka aplikasi Laragon atau XAMPP Control Panel, lalu klik **Start All** (Apache & MySQL).

3. **Buat Database Baru**
   - Buka **phpMyAdmin** di browser (`http://localhost/phpmyadmin`) atau aplikasi database favoritmu (HeidiSQL / DBeaver).
   - Buat database baru dengan nama bebas (contoh: `db_hs15_gallery` atau `nama_db_kamu`).
   - 🎉 **Tabel Otomatis Dibuat**: Kamu tidak perlu pusing import file `.sql` secara manual! Skrip di `security_helper.php` akan otomatis membuat tabel `users`, `media`, `login_attempts`, dan `password_resets` begitu web pertama kali diakses.

   > 💡 Jika ingin membuat tabel `users` secara manual terlebih dahulu, berikut skemanya:
   > ```sql
   > CREATE TABLE users (
   >     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   >     email VARCHAR(255) NOT NULL UNIQUE,
   >     password VARCHAR(255) NOT NULL,
   >     role ENUM('admin', 'member') DEFAULT 'member',
   >     profile_photo VARCHAR(255) NULL,
   >     created_at DATETIME DEFAULT CURRENT_TIMESTAMP
   > );
   > ```

4. **Sesuaikan Konfigurasi Database**
   Buka file `php/connect.php` dengan teks editor, lalu sesuaikan koneksi database kamu:
   ```php
   $host = "localhost";
   $user = "root";               // default Laragon/XAMPP
   $pass = "";                   // default kosong
   $db   = "db_hs15_gallery";    // masukkan nama database yang tadi kamu buat
   ```

5. **Buka di Browser**
   Tinggal akses via browser:
   ```text
   http://localhost/project_hs15/
   ```
   *(Atau kalau pakai Laragon Virtual Host: `http://project_hs15.test/`)*

---

### 🌐 B. Menjalankan di Server Hosting (cPanel / Shared Hosting)

Mau pasang galeri ini biar bisa diakses online bareng teman-teman komunitas? Caranya gampang banget:

1. **Siapkan File Web (.zip)**
   - Masukkan seluruh file dan folder proyek ke dalam file arsip `.zip`.
   - ⚠️ **Penting**: Pastikan file tersembunyi seperti `gallery/.htaccess` ikut ter-zip, karena file ini wajib ada untuk memproteksi media dari akses luar.

2. **Upload ke File Manager Hosting**
   - Login ke **cPanel** hosting kamu.
   - Buka menu **File Manager**, lalu masuk ke folder `public_html` (atau folder subdomain kamu, misal: `galeri.domainkamu.com`).
   - Klik tombol **Upload**, pilih file `.zip` tadi, lalu setelah selesai klik kanan file dan pilih **Extract**.

3. **Buat Database & User di cPanel**
   - Di dashboard cPanel, masuk ke menu **MySQL Database Wizard**.
   - **Langkah 1**: Beri nama database baru (misal: `usernamehosting_dbhs15`).
   - **Langkah 2**: Buat user database baru dan password yang kuat. Catat username dan password ini baik-baik.
   - **Langkah 3**: Berikan centang pada opsi **ALL PRIVILEGES** (Semua Hak Akses), lalu klik *Next Step*.

4. **Hubungkan Database di `php/connect.php`**
   - Di File Manager cPanel, cari dan edit file `php/connect.php`.
   - Masukkan detail database yang baru saja kamu buat:
     ```php
     $host = "localhost";                     // biasanya tetap localhost di sebagian besar cPanel
     $user = "usernamehosting_userhs15";      // user database cPanel kamu
     $pass = "PasswordKuatDatabaseKamu123!";  // password user database
     $db   = "usernamehosting_dbhs15";        // nama database cPanel kamu
     ```
   - Klik **Save Changes**.

5. **Pastikan Versi PHP & Ekstensi Aktif**
   - Di cPanel, cari menu **Select PHP Version** atau **MultiPHP Manager**.
   - Pilih versi **PHP 8.0, 8.1, atau 8.2**.
   - Di tab *Extensions*, pastikan ekstensi berikut dicentang/aktif:
     - `mysqli` (koneksi database)
     - `gd` (kompresi & generate thumbnail foto WebP)
     - `fileinfo` (validasi tipe file media)
     - `mbstring` (keperluan string UTF-8)

6. **Atur Izin Folder (Permissions)**
   - Pastikan folder tempat menyimpan upload foto & video memiliki izin tulis (*writeable*).
   - Di File Manager, cek permission folder berikut (biasanya bernilai `0755`):
     - `gallery/`
     - `gallery/thumbs/`
     - `img/profiles/`

7. **Naikkan Batas Upload Media (Biar Bisa Upload Video Besar)**
   - Masuk ke menu **MultiPHP INI Editor** di cPanel.
   - Pilih domain/lokasi website kamu, lalu sesuaikan nilai berikut:
     ```ini
     upload_max_filesize = 256M   ; atau 512M sesuai kebutuhan ukuran video
     post_max_size = 256M
     memory_limit = 256M
     max_execution_time = 300
     ```
   - Klik **Apply**.

8. **Selesai & Coba Akses Website!**
   - Buka domain kamu di browser, misalnya `https://galeri.domainkamu.com` atau `https://domainkamu.com`.
   - Begitu halaman pertama terbuka, sistem akan otomatis menginisialisasi tabel-tabel database yang diperlukan.
   - Daftarkan akun pertama kamu lewat menu Register, dan web siap dipakai! 🚀

---

## 🔑 Hak Akses & Akun Admin

- Setiap akun baru yang mendaftar via menu register secara default berstatus sebagai **Member**.
- Akun pertama yang terdaftar atau akun dengan email admin utama (misalnya `adm_******@gmail.com`) akan otomatis diangkat sebagai **Admin** oleh sistem.
- Admin punya hak akses ke **Panel Admin** untuk upload foto/video baru, edit keterangan dokumentasi, kelola data pengguna, serta menaikkan/menurunkan peran akun lain.

---

## 💡 Tips & Catatan Tambahan

1. **Izin Folder Server**:
   Pastikan folder `gallery/`, `gallery/thumbs/`, dan `img/profiles/` tetap writeable agar proses upload foto, video, dan pembuatan thumbnail berjalan lancar tanpa error `Permission Denied`.
2. **Thumbnail Video dengan FFmpeg (Opsional)**:
   - **Di Lokal**: Kamu bisa meletakkan `ffmpeg.exe` di dalam folder `php/`.
   - **Di Hosting**: Biasanya shared hosting tidak mengizinkan binary kustom. Namun jangan khawatir, video tetap berjalan normal dan sistem akan menggunakan thumbnail poster default yang rapi.
3. **Keamanan Tambahan di Hosting**:
   Disarankan selalu mengaktifkan sertifikat **SSL (HTTPS)** gratis (seperti Let's Encrypt di cPanel) agar transmisi data login dan streaming media terenkripsi dengan aman.

---

## 📜 Lisensi & Catatan Komunitas

Aplikasi ini dikembangkan untuk arsip dan dokumentasi internal keluarga besar komunitas **HS15 (Keliling Banjar)**. Seluruh kenangan dan media yang ada di dalamnya dijaga bersama untuk kebersamaan.

