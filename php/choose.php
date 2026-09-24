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
$currentUserRole  = $_SESSION['role'] ?? 'member';
$isSuperAdmin     = ($currentUserRole === 'admin');
$roleBadgeText    = $isSuperAdmin ? 'Super Admin' : 'Member';

$stats = getGalleryStats();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HS15 - Jelajahi Galeri</title>
  <link rel="stylesheet" href="../css/background.css?v=20260924_mobile_v4">
  <link rel="stylesheet" href="../css/global.css?v=20260924_mobile_v4">
  <link rel="stylesheet" href="../css/choose.css?v=20260924_mobile_v4">
  <link rel="icon" href="../img/logo.jpg" type="image/jpeg">
  <style>

    /* Critical stats styling */
    .stats-container {
      display: flex !important;
      gap: 16px !important;
      justify-content: center !important;
      align-items: center !important;
      width: 100% !important;
      max-width: 520px !important;
      margin: 0 auto 36px auto !important;
      box-sizing: border-box !important;
    }
    .stat-card {
      flex: 1 1 0 !important;
      background: rgba(18, 20, 26, 0.85) !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      border-radius: 14px !important;
      padding: 14px 18px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: flex-start !important;
      gap: 14px !important;
      backdrop-filter: blur(12px) !important;
      -webkit-backdrop-filter: blur(12px) !important;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
      transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease !important;
      box-sizing: border-box !important;
    }
    .stat-card:hover {
      transform: translateY(-3px) !important;
      border-color: rgba(229, 9, 20, 0.5) !important;
      box-shadow: 0 8px 24px rgba(229, 9, 20, 0.25) !important;
    }
    .stat-icon {
      width: 44px !important;
      height: 44px !important;
      border-radius: 12px !important;
      background: rgba(229, 9, 20, 0.15) !important;
      border: 1px solid rgba(229, 9, 20, 0.35) !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      flex-shrink: 0 !important;
    }
    .stat-icon ion-icon {
      font-size: 1.5rem !important;
      color: #ff3b47 !important;
    }
    .stat-info {
      flex: 1 !important;
      display: flex !important;
      flex-direction: column !important;
      justify-content: center !important;
      align-items: center !important;
      text-align: center !important;
      padding-right: 18px !important;
      min-width: 0 !important;
    }
    .stat-number {
      font-size: 1.65rem !important;
      font-weight: 700 !important;
      color: #ffffff !important;
      line-height: 1.15 !important;
      letter-spacing: -0.01em !important;
      text-align: center !important;
      margin-top: 2px !important;
    }
    .stat-label {
      font-size: 0.76rem !important;
      font-weight: 600 !important;
      color: rgba(255, 255, 255, 0.65) !important;
      text-transform: uppercase !important;
      letter-spacing: 0.05em !important;
      margin-bottom: 2px !important;
      text-align: center !important;
    }
    @media (max-width: 600px) {
      .stats-container {
        max-width: 100% !important;
        gap: 10px !important;
        margin-bottom: 28px !important;
      }
      .stat-card {
        padding: 12px 12px !important;
        gap: 10px !important;
        border-radius: 12px !important;
        justify-content: flex-start !important;
      }
      .stat-icon {
        width: 38px !important;
        height: 38px !important;
        border-radius: 10px !important;
      }
      .stat-icon ion-icon {
        font-size: 1.25rem !important;
      }
      .stat-info {
        flex: 1 !important;
        align-items: center !important;
        text-align: center !important;
        padding-right: 8px !important;
      }
      .stat-number {
        font-size: 1.35rem !important;
        text-align: center !important;
        margin-top: 2px !important;
      }
      .stat-label {
        font-size: 0.68rem !important;
        text-align: center !important;
        margin-bottom: 2px !important;
      }
    }
  </style>
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
          <a href="admin.php" class="admin-nav-link nav-desktop-only" style="color:#ff3b47;" title="Panel Administrasi">
            <ion-icon name="shield-checkmark-outline"></ion-icon> <span>Admin</span>
          </a>
        <?php endif; ?>
      </div>

      <!-- Desktop User Profile -->
      <div class="admin-nav-user nav-desktop-only">
        <img src="<?= htmlspecialchars(get_current_user_avatar($conn)) ?>" alt="Foto profil" class="admin-header-avatar" onerror="this.onerror=null; this.src='../img/logo.jpg';">
        <div class="user-pill">
          <span class="user-email"><?= htmlspecialchars($currentUserEmail) ?></span>
          <span class="user-role-badge" style="<?= !$isSuperAdmin ? 'color: #60a5fa;' : '' ?>"><?= $roleBadgeText ?></span>
        </div>
        <a href="logout.php" class="btn-logout" title="Log Out Sesi">
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
            <img src="<?= htmlspecialchars(get_current_user_avatar($conn)) ?>" alt="Foto profil" class="mobile-dropdown-avatar" onerror="this.onerror=null; this.src='../img/logo.jpg';">
            <div class="mobile-dropdown-info">
              <span class="mobile-dropdown-email"><?= htmlspecialchars($currentUserEmail) ?></span>
              <span class="mobile-dropdown-role" style="<?= !$isSuperAdmin ? 'color: #60a5fa;' : '' ?>"><?= $roleBadgeText ?></span>
            </div>
          </div>
          <div class="mobile-dropdown-divider"></div>
          <a href="account.php" class="mobile-dropdown-item">
            <ion-icon name="person-circle-outline"></ion-icon> Setelan Akun
          </a>
          <?php if ($isSuperAdmin): ?>
            <a href="admin.php" class="mobile-dropdown-item" style="color:#ff3b47;">
              <ion-icon name="shield-checkmark-outline"></ion-icon> Panel Admin
            </a>
          <?php endif; ?>
          <div class="mobile-dropdown-divider"></div>
          <a href="logout.php" class="mobile-dropdown-item mobile-logout">
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
      <h1>Jelajahi Dokumentasi HS15</h1>
      <p>Arsip koleksi momen kebersamaan, perjalanan keliling Banjar dalam foto & video</p>
    </div>

    <!-- Statistik Galeri HS15 -->
    <section class="stats-container" aria-label="Statistik Galeri HS15">
      <div class="stat-card">
        <div class="stat-icon">
          <ion-icon name="images-outline"></ion-icon>
        </div>
        <div class="stat-info">
          <span class="stat-label">Total Foto</span>
          <span class="stat-number" id="statPhotos"><?= $stats['photos'] ?></span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">
          <ion-icon name="videocam-outline"></ion-icon>
        </div>
        <div class="stat-info">
          <span class="stat-label">Total Video</span>
          <span class="stat-number" id="statVideos"><?= $stats['videos'] ?></span>
        </div>
      </div>
    </section>

    <div class="menu">
      <a href="gallery.php" class="card">
        <div class="content">
          <div class="icon">
            <ion-icon name="images-outline"></ion-icon>
          </div>
          <h2>Galeri Foto</h2>
          <p>Lihat koleksi foto momen dokumentasi perjalanan kami</p>
          <span class="card-action">Buka Galeri <ion-icon name="arrow-forward-outline"></ion-icon></span>
        </div>
      </a>

      <a href="vidgallery.php" class="card">
        <div class="content">
          <div class="icon">
            <ion-icon name="videocam-outline"></ion-icon>
          </div>
          <h2>Galeri Video</h2>
          <p>Tonton rekaman dokumentasi video seru kami</p>
          <span class="card-action">Buka Galeri <ion-icon name="arrow-forward-outline"></ion-icon></span>
        </div>
      </a>

      <?php if (is_admin($conn)): ?>
        <a href="admin.php" class="card" style="border-color: rgba(229, 9, 20, 0.45);">
          <div class="content">
            <div class="icon" style="color: #ff3b47;">
              <ion-icon name="shield-checkmark-outline"></ion-icon>
            </div>
            <h2>Panel Admin</h2>
            <p>Kelola upload media, edit judul, ganti/hapus file, dan atur akun pengguna</p>
            <span class="card-action" style="color: #ff3b47;">Buka Panel <ion-icon name="arrow-forward-outline"></ion-icon></span>
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
  <script src="../js/choose.js?v=20260924_v2"></script>
  <script src="../js/nav.js?v=20260924_v2"></script>
</body>
</html>

