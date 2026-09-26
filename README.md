# 🖼️ HS15 Web Gallery & Archive

Website arsip digital foto dan video kegiatan komunitas **HS15 - Komunitas Keliling Banjar**. Dibangun menggunakan **PHP Native** dan **MySQL** tanpa framework berat agar ringan, cepat, dan mudah di-deploy di berbagai lingkungan hosting.

---

## 📌 Ringkasan Proyek

Website ini berfungsi sebagai pusat dokumentasi privat bagi seluruh anggota komunitas HS15. Tampilan mengusung tema gelap (*dark mode*) modern dengan palet warna terstandarisasi berdasarkan peran pengguna, tata letak sepenuhnya responsif untuk perangkat mobile maupun desktop, serta sistem manajemen media yang praktis dan terstruktur bagi administrator.

---

## ✨ Fitur Utama

### 1. Galeri Foto
- **Thumbnail WebP Otomatis**: Foto otomatis dibuatkan thumbnail versi WebP (lebar 480px) menggunakan PHP GD saat pertama kali diakses. Hasil thumbnail tersimpan di cache server (`media/thumbs/`) sehingga pemuatan halaman galeri berikutnya berlangsung instan.
- **Lazy Loading & Shimmer Effect**: Gambar dimuat secara bertahap saat di-scroll (`loading="lazy"` & `decoding="async"`) dilengkapi animasi placeholder shimmer halus.
- **Lightbox Interaktif**: Klik foto untuk membuka tampilan ukuran penuh, membaca judul, tanggal, jam pengambilan, dan deskripsi kegiatan. Mendukung navigasi keyboard (panah kiri/kanan & ESC) serta tombol unduh file foto resolusi asli.
- **Filter Folder & Paginasi Rapi**: Navigasi media per album/folder kegiatan serta paginasi halaman (18 foto per halaman) dengan grid adaptif.

### 2. Galeri Video
- **Thumbnail Video Otomatis (Tanpa FFmpeg)**: Poster/sampul video diekstrak otomatis langsung dari frame pertama video melalui browser (HTML5 Canvas) lalu dikirim dan disimpan sebagai file JPG di cache server. Tidak memerlukan instalasi ekstensi FFmpeg pada server hosting.
- **Streaming Lancar (HTTP 206 Partial Content)**: Mendukung *HTTP Byte Range Requests* melalui gateway `media.php`, memungkinkan fitur seeking (lompat menit/detik) tanpa perlu mengunduh ulang seluruh file video dari awal.
- **Smart Playback**: Saat salah satu video diputar, video lain yang sedang aktif akan otomatis terhenti (*auto-pause*).
- **Tampilan Adaptif**: Mendukung video berorientasi lanskap maupun potret (*reels/story*) tanpa terpotong.

### 3. Pengaturan Akun & Profil
- **Indikator Visual Berdasarkan Role**: Border foto profil dan badge status dibedakan secara visual:
  - **Administrator**: Border ungu bercahaya dan badge ungu (`Administrator`).
  - **Member**: Border biru cerah dan badge biru (`Member`).
- **Shortcut Panel Admin**: Tombol akses cepat ke Panel Administrasi khusus untuk akun administrator dengan gradasi ungu elegan.
- **Crop Foto Profil Interaktif**: Pemotong foto profil rasio 1:1 dengan handle sudut bulat presisi, panggung pratinjau (stage) besar, dan ekspor tajam 512x512 piksel. Foto profil lama otomatis dibersihkan dari server.
- **Manajemen Keamanan Akun**: Form penggantian password aman serta opsi penghapusan akun mandiri dengan konfirmasi ketik.

### 4. Panel Admin Terpadu
- **Koleksi Media**:
  - Tabel data lengkap dengan pratinjau thumbnail, status tipe, tanggal, waktu, dan folder kegiatan.
  - Dropdown filter kustom (*animated select*) dengan efek border hover/focus putih bersih dan penanda pilihan aktif yang rapi.
  - Pencarian instan dan sortir data (*Terbaru*, *Terlama*, *A-Z*, *Z-A*).
  - **Tombol Hapus Dinamis**:
    - Default (belum ada yang dipilih): tombol menampilkan **Hapus**.
    - Sebagian media dicentang (misal 10 dari 15): tombol otomatis berubah menjadi **Hapus Dipilih**.
    - Semua media di halaman dicentang: tombol otomatis berubah menjadi **Hapus Semua**.
- **Unggah Media Baru**:
  - Mendukung upload banyak file foto dan video sekaligus.
  - Pilihan alokasi ke album/folder yang sudah ada atau membuat folder/album baru secara instan langsung dari antarmuka web.
- **Kelola Akun Pengguna**:
  - Daftar seluruh member terdaftar, pengubahan hak akses (Admin / Member), serta penghapusan akun dengan konfirmasi keamanan.

### 5. Keamanan Sistem & Arsitektur
- **Akses Media Tertutup**: Folder fisik `media/` dilindungi oleh berkas `.htaccess`. Akses langsung ke file dicegah; seluruh streaming file foto dan video wajib melalui gateway `media.php` dengan verifikasi sesi aktif.
- **Anti Brute-Force Login**: Pembatasan percobaan login gagal maksimal 5 kali berturut-turut dalam rentang waktu 15 menit per IP/akun.
- **Proteksi CSRF**: Seluruh form aksi (login, upload, edit, hapus) dilindungi token CSRF kriptografis.
- **Akses Terbatas Pendaftaran**: Halaman pendaftaran member baru (`register.php`) dan reset password hanya dapat diakses oleh Administrator yang sedang login (kecuali saat inisialisasi awal database kosong).
- **Prepared Statements**: Seluruh interaksi database menggunakan MySQL Prepared Statements untuk mengeliminasi celah SQL Injection.

---

## 🧰 Spesifikasi & Kebutuhan Sistem

- **Web Server**: Apache 2.4.5x s.d. **Apache 2.4.68+** (dengan modul `mod_rewrite` aktif).
- **PHP**: PHP 8.0, 8.1, 8.2, 8.3, 8.4, hingga **PHP 8.5.11+** *(resmi teruji & kompatibel penuh)*.
- **Ekstensi PHP Wajib**:
  - `mysqli` (koneksi database & prepared statements)
  - `gd` (pembuatan thumbnail WebP, poster video, dan crop avatar)
  - `fileinfo` (validasi MIME type berkas unggahan)
  - `mbstring` (pemrosesan string UTF-8)
  - `curl` & `openssl` (keamanan dan komunikasi data)
  - `session` (manajemen autentikasi pengguna)
- **Database**: MySQL 5.7+, MySQL 8.0, **MySQL 8.4 LTS** *(kompatibel dengan strict sql_mode)*, atau MariaDB 10.3+.

---

## 📁 Struktur Direktori

```text
project_hs15/
├── index.php                 # Halaman pengarah utama
├── README.md                 # Dokumentasi proyek
├── css/                      # Lembar gaya tampilan (CSS)
│   ├── global.css            # Variabel warna tema, role badge, avatar border, navbar
│   ├── auth.css              # Styling halaman autentikasi (login, register, reset pass)
│   ├── choose.css            # Styling beranda utama setelah login
│   ├── gallery.css           # Styling galeri foto, grid responsif, dan lightbox
│   ├── vidgallery.css        # Styling galeri video dan pemutar media
│   ├── account.css           # Styling setelan profil dan modal crop foto
│   ├── admin.css             # Styling panel administrasi dan tabel media
│   └── background.css        # Slideshow latar belakang dinamis
├── js/                       # Skrip interaktivitas frontend (JavaScript)
│   ├── nav.js                # Navigasi header dan menu dropdown 3 titik di mobile
│   ├── gallery.js            # Lightbox foto, navigasi keyboard panah & ESC
│   ├── vidgallery.js         # Autopause pemutar video & generator poster canvas
│   ├── account.js            # Sistem pemotong foto profil (drag, resize, canvas export)
│   ├── auth.js               # Toggle visibilitas password dan validasi form
│   ├── choose.js             # Efek interaktif dan animasi menu beranda
│   └── interactions.js       # Helper interaksi umum
├── php/                      # Logika backend aplikasi
│   ├── connect.php           # Konfigurasi koneksi database MySQL
│   ├── security_helper.php   # Keamanan (CSRF, brute-force, thumbnail, path traversal)
│   ├── stats_helper.php      # Penghitung statistik jumlah foto dan video
│   ├── media.php             # Gateway penyaji berkas media (HTTP 206 streaming & auth)
│   ├── video_thumb.php       # Endpoint penerima dan penyimpan poster video
│   ├── choose.php            # Halaman menu beranda utama
│   ├── gallery.php           # Halaman galeri foto komunitas
│   ├── vidgallery.php        # Halaman galeri video kegiatan
│   ├── account.php           # Halaman setelan akun & profil pengguna
│   ├── admin.php             # Panel administrasi media dan pengguna
│   ├── index.php             # Form login akun
│   ├── register.php          # Pendaftaran akun member baru (khusus admin)
│   ├── fgpass.php            # Permintaan pemulihan password
│   ├── reset_password.php    # Form penggantian password baru
│   ├── profile_image.php     # Endpoint JSON foto profil pengguna aktif
│   └── logout.php            # Proses keluar sesi aman
├── media/                    # Direktori penyimpanan berkas foto & video
│   ├── .htaccess             # Proteksi akses langsung ke berkas media
│   └── thumbs/               # Cache thumbnail foto (.webp) dan poster video (.jpg)
└── img/                      # Aset statis website
    ├── logo.jpg              # Logo identitas HS15
    └── profiles/             # Direktori penyimpanan foto profil pengguna
```

---

## 🚀 Panduan Pemasangan & Konfigurasi

### A. Menjalankan di Komputer Lokal (Laragon / XAMPP)

1. **Salin Berkas Proyek**:
   Letakkan folder proyek di dalam direktori root server:
   - Laragon: `C:\laragon\www\project_hs15` (atau `D:\laragon\www\project_hs15`)
   - XAMPP: `C:\xampp\htdocs\project_hs15`

2. **Nyalakan Web Server & Database**:
   Jalankan service **Apache** dan **MySQL** melalui panel kontrol Laragon atau XAMPP.

3. **Buat Database Baru**:
   - Buka phpMyAdmin (`http://localhost/phpmyadmin`).
   - Buat database baru dengan nama pilihan Anda (contoh: `db_******`).
   - *Catatan*: Skema tabel (`users`, `media`, `login_attempts`, `password_resets`) akan digenerate otomatis oleh sistem saat aplikasi pertama kali dibuka.

4. **Konfigurasi Koneksi Database**:
   Buka file `php/connect.php`, sesuaikan kredensial database Anda:
   ```php
   $host = "localhost";
   $user = "root";              // Akun default Laragon / XAMPP
   $pass = "";                  // Password default kosong
   $db   = "db_******";         // Masukkan nama database yang telah Anda buat
   ```

5. **Catatan Khusus MySQL 8.4 LTS (Jika Menggunakan Versi Baru)**:
   Jika menggunakan MySQL 8.4+ di Laragon, pastikan parameter berikut aktif di file `my.ini` jika akun root Anda menggunakan autentikasi native:
   ```ini
   [mysqld]
   mysql_native_password=ON
   ```

6. **Akses Aplikasi**:
   Buka peramban dan kunjungi:
   ```text
   http://localhost/project_hs15/
   ```
   *(Atau `http://project_hs15.test/` jika menggunakan fitur Pretty URLs Laragon)*.

---

### B. Menjalankan di Hosting / Server Produksi (cPanel)

1. **Unggah & Ekstrak Berkas**:
   - Kompres seluruh isi folder proyek menjadi berkas `.zip`.
   - Pastikan berkas tersembunyi `media/.htaccess` disertakan.
   - Buka **File Manager** cPanel, masuk ke folder `public_html` (atau folder subdomain), unggah dan ekstrak berkas zip tersebut.

2. **Buat Database di cPanel**:
   - Masuk ke menu **MySQL Database Wizard**.
   - Buat nama database baru (misal: `usercpanel_******`).
   - Buat akun pengguna database baru dan simpan password-nya dengan aman.
   - Tetapkan hak akses **ALL PRIVILEGES** untuk pengguna tersebut ke database terkait.

3. **Sesuaikan File Koneksi Database**:
   Buka berkas `php/connect.php` melalui editor berkas cPanel:
   ```php
   $host = "localhost";
   $user = "usercpanel_******";  // Akun database cPanel Anda
   $pass = "****************";   // Password akun database cPanel Anda
   $db   = "usercpanel_******";  // Nama database cPanel Anda
   ```

4. **Konfigurasi Versi & Ekstensi PHP**:
   - Masuk ke menu **Select PHP Version** di cPanel.
   - Pilih versi **PHP 8.1, 8.2, 8.3, atau 8.4+**.
   - Pastikan ekstensi `mysqli`, `gd`, `fileinfo`, `mbstring`, `curl`, dan `openssl` dalam status aktif (*checked*).

5. **Batas Ukuran Unggahan PHP**:
   Di menu **MultiPHP INI Editor**, sesuaikan batas ukuran unggah agar server dapat menerima file video berukuran besar:
   ```ini
   upload_max_filesize = 512M
   post_max_size = 512M
   memory_limit = 512M
   max_execution_time = 300
   max_input_time = 300
   ```

6. **Hak Akses Folder (Permissions)**:
   Pastikan folder berikut memiliki izin tulis (`0755`):
   - `media/`
   - `media/thumbs/`
   - `img/profiles/`

---

## 🔑 Manajemen Peran (Roles) & Akun

- **Member**:
  - Hak akses: Melihat Galeri Foto, menonton Galeri Video, mengunduh media resolusi asli, serta mengatur profil pribadi (crop avatar & ganti password).
  - Indikator: Border foto profil dan badge berwarna **Biru**.
- **Administrator**:
  - Hak akses: Seluruh hak akses member ditambah hak kelola penuh melalui **Panel Admin** (membuat folder baru, mengunggah media, mengedit metadata, menghapus media satuan/borongan, serta mengelola hak akses pengguna lain).
  - Indikator: Border foto profil dan badge berwarna **Ungu**, dilengkapi tombol shortcut ungu di setelan akun.
- **Inisialisasi Admin Pertama**:
  - Akun pertama yang terdaftar pada database kosong akan otomatis diangkat menjadi **Admin** oleh sistem.

---

## 💬 Informasi Lisensi & Penggunaan

- Website ini dikembangkan khusus untuk dokumentasi arsip kegiatan internal komunitas **HS15 - Komunitas Keliling Banjar**.
- Seluruh aset foto dan video dilindungi oleh sistem autentikasi sesi privat untuk menjaga keamanan privasi seluruh anggota.