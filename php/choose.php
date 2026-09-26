<?php
// choose.php - Halaman beranda privat HS15 dengan proteksi autentikasi session
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/stats_helper.php';

// Proteksi halaman privat: Wajib login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$currentUserEmail = $_SESSION['email'] ?? 'User';
$userNameParts    = explode('@', $currentUserEmail);
$displayName      = ucwords(str_replace(['.', '_', '-'], ' ', $userNameParts[0]));
$currentUserRole  = $_SESSION['role'] ?? 'member';
$isSuperAdmin     = ($currentUserRole === 'admin');
$roleBadgeText    = $isSuperAdmin ? 'Administrator' : 'Member';

$stats = getGalleryStats();

$chooseVer = file_exists(__DIR__ . '/../css/choose.css') ? filemtime(__DIR__ . '/../css/choose.css') : time();
$globalVer = file_exists(__DIR__ . '/../css/global.css') ? filemtime(__DIR__ . '/../css/global.css') : time();
$bkgdVer   = file_exists(__DIR__ . '/../css/background.css') ? filemtime(__DIR__ . '/../css/background.css') : time();
$jsVer     = file_exists(__DIR__ . '/../js/choose.js') ? filemtime(__DIR__ . '/../js/choose.js') : time();
$navVer    = file_exists(__DIR__ . '/../js/nav.js') ? filemtime(__DIR__ . '/../js/nav.js') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HS15 - Jelajahi Galeri</title>
  <link rel="stylesheet" href="../css/background.css?v=<?= $bkgdVer ?>">
  <link rel="stylesheet" href="../css/global.css?v=<?= $globalVer ?>">
  <link rel="stylesheet" href="../css/choose.css?v=<?= $chooseVer ?>">
  <link rel="icon" href="../img/logo.jpg" type="image/jpeg">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body class="choose-page">

  <!-- Header Navigasi -->
  <header>
    <nav>
      <a href="choose.php" class="logo" id="logo-link">
        <img src="../img/logo.jpg" alt="HS15 Logo" class="logo-img">
        <span class="logo-title">HS15<span class="logo-sub"> - Komunitas Keliling Banjar</span></span>
      </a>

      <div class="admin-nav-links">
        <a href="choose.php" class="admin-nav-link active" title="Ke Beranda Utama">
          <ion-icon name="home-outline"></ion-icon> <span>Beranda</span>
        </a>
        <a href="gallery.php" class="admin-nav-link" title="Buka Galeri Foto">
          <ion-icon name="images-outline"></ion-icon> <span>Foto</span>
        </a>
        <a href="vidgallery.php" class="admin-nav-link" title="Buka Galeri Video">
          <ion-icon name="videocam-outline"></ion-icon> <span>Video</span>
        </a>
        <a href="account.php" class="admin-nav-link nav-desktop-only" title="Pengaturan Akun">
          <ion-icon name="person-circle-outline"></ion-icon> <span>Akun</span>
        </a>
        <?php if ($isSuperAdmin): ?>
          <a href="admin.php" class="admin-nav-link nav-desktop-only" style="color:#c084fc;" title="Panel Administrasi">
            <ion-icon name="shield-checkmark-outline"></ion-icon> <span>Admin</span>
          </a>
        <?php endif; ?>
      </div>

      <!-- Desktop User Profile -->
      <div class="admin-nav-user nav-desktop-only">
        <img src="<?= htmlspecialchars(get_current_user_avatar($conn)) ?>" alt="Foto profil" class="admin-header-avatar <?= $isSuperAdmin ? 'avatar-role-admin' : 'avatar-role-member' ?>" onerror="this.onerror=null; this.src='../img/logo.jpg';">
        <div class="user-pill">
          <span class="user-email"><?= htmlspecialchars($currentUserEmail) ?></span>
          <span class="user-role-badge <?= $isSuperAdmin ? 'badge-role-admin' : 'badge-role-member' ?>">
            <ion-icon name="<?= $isSuperAdmin ? 'shield-checkmark' : 'person' ?>"></ion-icon>
            <?= $roleBadgeText ?>
          </span>
        </div>
        <a href="logout.php" class="btn-logout <?= $isSuperAdmin ? 'btn-logout-admin' : 'btn-logout-member' ?>" title="Log Out Sesi">
          <ion-icon name="log-out-outline"></ion-icon>
        </a>
      </div>

      <!-- Mobile 3-Dots Menu -->
      <div class="mobile-menu-wrap">
        <button type="button" class="mobile-menu-btn" aria-label="Menu akun dan opsi" aria-expanded="false">
          <ion-icon name="ellipsis-vertical"></ion-icon>
        </button>
        <div class="mobile-dropdown">
          <div class="mobile-dropdown-user">
            <img src="<?= htmlspecialchars(get_current_user_avatar($conn)) ?>" alt="Foto profil" class="mobile-dropdown-avatar <?= $isSuperAdmin ? 'avatar-role-admin' : 'avatar-role-member' ?>" onerror="this.onerror=null; this.src='../img/logo.jpg';">
            <div class="mobile-dropdown-info">
              <span class="mobile-dropdown-email"><?= htmlspecialchars($currentUserEmail) ?></span>
              <span class="mobile-dropdown-role <?= $isSuperAdmin ? 'badge-role-admin' : 'badge-role-member' ?>">
                <ion-icon name="<?= $isSuperAdmin ? 'shield-checkmark' : 'person' ?>"></ion-icon>
                <?= $roleBadgeText ?>
              </span>
            </div>
          </div>
          <div class="mobile-dropdown-divider"></div>
          <a href="account.php" class="mobile-dropdown-item">
            <ion-icon name="person-circle-outline"></ion-icon> Setelan Akun
          </a>
          <?php if ($isSuperAdmin): ?>
            <a href="admin.php" class="mobile-dropdown-item" style="color:#c084fc;">
              <ion-icon name="shield-checkmark-outline"></ion-icon> Panel Admin
            </a>
          <?php endif; ?>
          <div class="mobile-dropdown-divider"></div>
          <a href="logout.php" class="mobile-dropdown-item mobile-logout <?= $isSuperAdmin ? 'mobile-logout-admin' : 'mobile-logout-member' ?>">
            <ion-icon name="log-out-outline"></ion-icon> Log Out
          </a>
        </div>
      </div>
    </nav>
  </header>

  <!-- Latar Belakang Slideshow & Overlay -->
  <div id="bkgd1"></div>
  <div id="bkgd2"></div>
  <div class="bkgd-overlay"></div>

  <!-- Konten Utama Landing Page -->
  <main class="choose-main">
    <div class="hero-section">
      <div class="welcome-chip">
        <span class="chip-avatar-dot"></span>
        <span>Halo, <strong><?= htmlspecialchars($displayName) ?></strong></span>
        <span class="chip-role-badge <?= $isSuperAdmin ? 'chip-role-admin' : 'chip-role-member' ?>">
          <ion-icon name="<?= $isSuperAdmin ? 'shield-checkmark' : 'person' ?>"></ion-icon>
          <?= $roleBadgeText ?>
        </span>
      </div>

      <h1>Arsip Dokumentasi HS15</h1>
      <p class="hero-intro">
        Selamat datang di pusat dokumentasi resmi <strong>Komunitas Keliling Banjar</strong>. 
        Jelajahi setiap jejak perjalanan, kebersamaan, dan keindahan sudut Banjar yang tersimpan dalam arsip foto dan rekaman video kami.
      </p>

      <?php if ($isSuperAdmin): ?>
        <div class="admin-notice-pill">
          <ion-icon name="shield-checkmark-outline"></ion-icon>
          <span>Mode Administrator Aktif — Anda memiliki hak akses penuh untuk mengelola unggahan arsip, membuat folder, dan mengatur anggota komunitas.</span>
        </div>
      <?php endif; ?>
    </div>

    <!-- Statistik Galeri HS15 -->
    <section class="stats-container" aria-label="Statistik Galeri HS15">
      <div class="stat-card">
        <div class="stat-icon stat-icon-photo">
          <ion-icon name="images-outline"></ion-icon>
        </div>
        <div class="stat-info">
          <span class="stat-label">Total Foto</span>
          <span class="stat-number" id="statPhotos"><?= $stats['photos'] ?></span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon stat-icon-video">
          <ion-icon name="videocam-outline"></ion-icon>
        </div>
        <div class="stat-info">
          <span class="stat-label">Total Video</span>
          <span class="stat-number" id="statVideos"><?= $stats['videos'] ?></span>
        </div>
      </div>
    </section>

    <!-- Menu Kartu Pilihan -->
    <div class="menu <?= $isSuperAdmin ? 'menu-admin' : 'menu-member' ?>">
      <a href="gallery.php" class="card card-photo">
        <div class="card-badge">📸 Koleksi Foto</div>
        <div class="content">
          <div class="icon">
            <ion-icon name="images-outline"></ion-icon>
          </div>
          <h2>Galeri Foto</h2>
          <p>Lihat ratusan potret perjalanan dan kenangan kebersamaan dengan pratinjau WebP cepat & lightbox interaktif.</p>
          <span class="card-action">Buka Galeri Foto <ion-icon name="arrow-forward-outline"></ion-icon></span>
        </div>
      </a>

      <a href="vidgallery.php" class="card card-video">
        <div class="card-badge">🎬 Rekaman Video</div>
        <div class="content">
          <div class="icon">
            <ion-icon name="videocam-outline"></ion-icon>
          </div>
          <h2>Galeri Video</h2>
          <p>Tonton kembali keseruan dan rekaman video perjalanan kegiatan dengan pemutaran lancar tanpa jeda.</p>
          <span class="card-action">Buka Galeri Video <ion-icon name="arrow-forward-outline"></ion-icon></span>
        </div>
      </a>

      <?php if ($isSuperAdmin): ?>
        <a href="admin.php" class="card card-admin">
          <div class="card-badge card-badge-admin">🛡️ Panel Admin</div>
          <div class="content">
            <div class="icon icon-admin">
              <ion-icon name="shield-checkmark-outline"></ion-icon>
            </div>
            <h2>Panel Admin</h2>
            <p>Kelola unggahan media baru, buat folder kegiatan, edit keterangan dokumentasi, dan atur akun anggota.</p>
            <span class="card-action card-action-admin">Buka Dashboard <ion-icon name="arrow-forward-outline"></ion-icon></span>
          </div>
        </a>
      <?php endif; ?>
    </div>
  </main>

  <!-- Footer Copyright -->
  <footer class="main-footer">
    <p>&copy; 2026 HS15 - Komunitas Keliling Banjar. All rights reserved.</p>
  </footer>

  <!-- Skrip Slideshow & Navigasi -->
  <script src="../js/choose.js?v=<?= $jsVer ?>"></script>
  <script src="../js/nav.js?v=<?= $navVer ?>"></script>
</body>
</html>
