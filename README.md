# 🖼️ HS15 Web Gallery & Archive

Website arsip digital foto dan video kegiatan komunitas **HS15 - Komunitas Keliling Banjar**. Dibuat menggunakan **PHP Native** dan **MySQL** tanpa framework berat agar ringan, cepat, dan mudah di-hosting di mana saja.

---

## 📌 Ringkasan Proyek

Website ini berfungsi sebagai pusat dokumentasi privat bagi anggota komunitas HS15. Tampilan mengusung tema gelap (dark mode) dengan aksen merah khas, tata letak responsif untuk HP maupun desktop, serta sistem manajemen media yang praktis bagi admin.

---

## ✨ Fitur Utama

### 1. Galeri Foto
- **Thumbnail WebP Otomatis**: Foto otomatis dibuatkan thumbnail versi WebP (lebar 480px) menggunakan PHP GD saat pertama kali dimuat. File tersimpan di cache sehingga loading galeri berikutnya terasa instan.
- **Lazy Loading & Shimmer Effect**: Gambar dimuat secara bertahap saat di-scroll (`loading="lazy"` & `decoding="async"`) lengkap dengan animasi shimmer placeholder.
- **Lightbox Interaktif**: Klik foto untuk melihat ukuran penuh, melihat judul/tanggal/jam pengambilan, navigasi keyboard (panah kiri/kanan & ESC), serta tombol download foto asli.
- **Paginasi Rapi**: Pembagian halaman 16 foto per halaman (grid 4 kolom di desktop, 2 kolom di mobile).

### 2. Galeri Video
- **Thumbnail Video Tanpa FFmpeg**: Poster/sampul video dibuat otomatis langsung dari frame pertama video lewat browser (HTML5 Canvas) lalu disimpan di cache server sebagai file JPG. Tidak perlu instalasi FFmpeg di server hosting.
- **Streaming Lancar (HTTP 206)**: Mendukung *HTTP Range Requests* lewat gateway `media.php`, sehingga video bisa di-seek (maju/mundur) tanpa buffering ulang dari awal.
- **Smart Playback**: Saat salah satu video diputar, video lain yang sedang berjalan akan otomatis ter-pause.
- **Tampilan Adaptif**: Mendukung video lanskap maupun vertikal (potret/reels) tanpa terpotong.

### 3. Pengaturan Akun & Profil
- **Crop Foto Profil Interaktif**: Fitur pemotong foto profil persegi (rasio 1:1) dengan handle sudut bulat yang fleksibel, stage besar (500x500px), dan pratinjau langsung di dalam area crop.
- **Export HD**: Hasil crop diekspor dengan resolusi tajam 512x512 piksel dan foto profil lama langsung dibersihkan dari server.
- **Kelola Keamanan Akun**: Form ubah password dengan validasi password saat ini, serta opsi hapus akun mandiri dengan konfirmasi ketik.

### 4. Panel Admin
- **Buat Folder / Album Baru**: Admin bisa langsung membuat folder kegiatan baru dari halaman admin tanpa perlu buka file manager di server/hosting.
- **Upload Media Fleksibel**: Mendukung upload banyak file foto dan video sekaligus langsung ke folder yang dipilih.
- **Manajemen & Edit Data**: Ubah judul, deskripsi, tanggal, dan jam pengambilan media secara langsung.
- **Hapus Satuan & Borongan**: Pilihan hapus satu per satu atau centang banyak sekaligus. File asli beserta thumbnail di server otomatis ikut terhapus bersih dari disk.
- **Kelola Pengguna**: Pantau daftar member, ubah role (Admin / Member), atau hapus akun pengguna dengan tampilan tabel yang rapi dan sejajar.

### 5. Keamanan Sistem
- **Proteksi Media Tertutup**: Folder `media/` dilindungi oleh `.htaccess`. File foto dan video hanya bisa diakses lewat script `media.php` jika user sudah login.
- **Anti Brute-Force**: Percobaan login dibatasi maksimal 5 kali gagal dalam rentang 15 menit per alamat IP atau email.
- **Token CSRF**: Semua aksi penting (login, upload, edit, hapus) diverifikasi dengan token CSRF sekali pakai.
- **Keamanan Data**: Menggunakan query *Prepared Statements* (bebas SQL Injection) dan hashing password dengan algoritma Bcrypt standar industri.

---

## 🧰 Spesifikasi & Kebutuhan Sistem

- **PHP**: Versi 8.0, 8.1, atau 8.2
- **Ekstensi PHP Wajib**:
  - `mysqli` (koneksi database)
  - `gd` (pembuatan thumbnail WebP & resize foto profil)
  - `fileinfo` (validasi tipe mime file)
  - `mbstring` (pemrosesan teks UTF-8)
- **Database**: MySQL 5.7+ atau MariaDB 10.3+
- **Web Server**: Apache dengan modul `mod_rewrite` aktif (Laragon, XAMPP, atau cPanel)

---

## 📁 Struktur Direktori

```text
project_hs15/
├── index.php                 # Halaman awal / form login
├── README.md                 # Dokumentasi proyek
├── css/                      # File styling tampilan
│   ├── global.css            # Variabel warna, navbar, font, dan elemen global
│   ├── index.css             # Halaman login, register, dan reset password
│   ├── choose.css            # Halaman menu utama / beranda
│   ├── gallery.css           # Galeri foto, grid, dan lightbox
│   ├── vidgallery.css        # Galeri video dan video player
│   ├── account.css           # Pengaturan profil dan modal crop foto
│   └── admin.css             # Dashboard panel admin
├── js/                       # Logika JavaScript frontend
│   ├── nav.js                # Navigasi header dan menu dropdown titik 3 di HP
│   ├── gallery.js            # Lightbox foto dan navigasi keyboard
│   ├── vidgallery.js         # Autopause & generator thumbnail video (canvas)
│   ├── account.js            # Interaktivitas crop foto profil (drag, resize, canvas export)
│   ├── auth.js               # Toggle lihat password dan validasi form
│   └── choose.js             # Efek interaktif menu beranda
├── php/                      # Logika backend aplikasi
│   ├── connect.php           # Konfigurasi koneksi database
│   ├── security_helper.php   # Fungsi keamanan (CSRF, brute-force, sync media, dll)
│   ├── stats_helper.php      # Penghitung total foto dan video
│   ├── media.php             # Gateway pembaca file media (streaming & auth check)
│   ├── video_thumb.php       # Endpoint penerima dan penyimpan poster video
│   ├── choose.php            # Halaman menu beranda setelah login
│   ├── gallery.php           # Halaman galeri foto
│   ├── vidgallery.php        # Halaman galeri video
│   ├── account.php           # Halaman profil dan ganti avatar
│   ├── admin.php             # Panel admin dan manajemen file
│   ├── register.php          # Pendaftaran akun member baru
│   ├── fgpass.php            # Permintaan link lupa password
│   ├── reset_password.php    # Form penggantian password baru
│   └── logout.php            # Proses keluar sesi
├── media/                    # Direktori penyimpanan foto & video
│   ├── .htaccess             # Mengalihkan seluruh request file ke php/media.php
│   └── thumbs/               # Cache thumbnail foto (.webp) dan poster video (.jpg)
└── img/                      # Aset statis website
    ├── logo.jpg              # Logo resmi HS15
    └── profiles/             # Tempat penyimpanan foto profil pengguna
```

---

## 🚀 Panduan Pemasangan

### A. Menjalankan di Komputer Lokal (Laragon / XAMPP)

1. **Salin File Proyek**:
   Letakkan folder proyek di direktori web server:
   - Laragon: `C:\laragon\www\project_hs15`
   - XAMPP: `C:\xampp\htdocs\project_hs15`

2. **Jalankan Apache & MySQL**:
   Nyalakan service Apache dan MySQL melalui panel kontrol Laragon atau XAMPP.

3. **Siapkan Database**:
   - Buka phpMyAdmin (`http://localhost/phpmyadmin`).
   - Buat database baru, misalnya dengan nama `db_******` (ganti dengan nama yang kamu inginkan).
   - *Catatan*: Tabel database (`users`, `media`, `login_attempts`, `password_resets`) akan dibuat otomatis oleh sistem saat website pertama kali dibuka di browser.

4. **Atur Koneksi Database**:
   Buka file `php/connect.php`, sesuaikan data koneksi:
   ```php
   $host = "localhost";
   $user = "root";        // default Laragon/XAMPP
   $pass = "";            // default kosong
   $db   = "db_******";   // masukkan nama database yang kamu buat tadi
   ```

5. **Akses Website**:
   Buka browser dan kunjungi:
   ```text
   http://localhost/project_hs15/
   ```
   *(Atau `http://project_hs15.test/` jika menggunakan virtual host bawaan Laragon)*.

---

### B. Menjalankan di Hosting (cPanel)

1. **Kompres & Upload File**:
   - Jadikan seluruh isi folder proyek ke dalam satu file `.zip`.
   - Pastikan file `media/.htaccess` ikut ter-upload karena file ini penting untuk mengamankan foto dan video.
   - Buka **File Manager** di cPanel, masuk ke `public_html` (atau folder subdomain), lalu upload dan ekstrak file zip tersebut.

2. **Buat Database di cPanel**:
   - Masuk ke menu **MySQL Database Wizard**.
   - Buat nama database baru (misal: `usercpanel_******`).
   - Buat user database baru beserta password-nya.
   - Centang **ALL PRIVILEGES** agar akun bisa membaca dan menulis data tabel.

3. **Sesuaikan File Koneksi**:
   Edit file `php/connect.php` di cPanel File Manager:
   ```php
   $host = "localhost";
   $user = "usercpanel_******";  // user database cPanel kamu
   $pass = "password_kamu_disini";
   $db   = "usercpanel_******";  // nama database cPanel kamu
   ```

4. **Cek Ekstensi PHP**:
   - Masuk ke menu **Select PHP Version** di cPanel.
   - Pilih versi **PHP 8.1** atau **8.2**.
   - Pastikan ekstensi `gd`, `mysqli`, `fileinfo`, dan `mbstring` dalam kondisi aktif.

5. **Sesuaikan Batas Upload PHP**:
   Di menu **MultiPHP INI Editor**, atur batas ukuran upload agar bisa menerima file video:
   ```ini
   upload_max_filesize = 256M
   post_max_size = 256M
   memory_limit = 256M
   max_execution_time = 300
   ```

6. **Izin Folder (Permissions)**:
   Pastikan folder berikut memiliki izin tulis (`0755`):
   - `media/`
   - `media/thumbs/`
   - `img/profiles/`

7. **Buka Domain**:
   Akses domain kamu di browser untuk login. *(Jika belum ada akun sama sekali, kamu bisa akses langsung `php/register.php` sekali untuk mendaftarkan akun admin pertama).*

---

## 🔑 Catatan Role & Akun Admin

- Pengguna yang baru mendaftar akan otomatis memiliki peran sebagai **Member**.
- Akun pertama yang terdaftar di database akan otomatis dijadikan sebagai **Admin** oleh sistem.
- Akun Admin memiliki akses penuh ke menu **Panel Admin** untuk membuat folder, mengupload foto/video, menghapus file, serta mengatur peran member lainnya.

---

## 💬 Catatan Penggunaan

- Website ini dirancang untuk penggunaan privat dokumentasi internal komunitas **HS15 (Keliling Banjar)**.
- Seluruh file media dilindungi sesi login untuk menjaga kenyamanan dan privasi seluruh anggota.
