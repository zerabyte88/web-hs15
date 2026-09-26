<?php
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
$roleBadgeText    = $isSuperAdmin ? 'Administrator' : 'Member';

sync_media_files_to_db($conn);
$dir = get_media_base_dir();
$thumbDir = get_thumbs_base_dir();
$stats = getGalleryStats();

// Pagination & Query dari tabel media
$perPage = 12; // 12 video per halaman
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$totalStmt = $conn->query("SELECT COUNT(*) as c FROM media WHERE media_type = 'video'");
$total = (int) ($totalStmt ? ($totalStmt->fetch_assoc()['c'] ?? 0) : 0);

$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, $page);
$start = ($page - 1) * $perPage;

$stmt = $conn->prepare("
    SELECT id, filename, original_name, title, description, media_date, media_time 
    FROM media 
    WHERE media_type = 'video' 
    ORDER BY media_date DESC, media_time DESC, id DESC 
    LIMIT ? OFFSET ?
");
$currentVids = [];
if ($stmt) {
    $stmt->bind_param("ii", $perPage, $start);
    $stmt->execute();
    $currentVids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fallback jika database belum terindeks
if (empty($currentVids) && $total === 0) {
    $vid = [];
    foreach (['mp4', 'webm', 'ogg', 'm4v'] as $extension) {
      $matches = glob($dir . '*.' . $extension);
      if ($matches !== false) {
        $vid = array_merge($vid, $matches);
      }
    }
    $vid = array_values(array_unique($vid));
    if ($vid) {
        usort($vid, fn($a, $b) => filemtime($b) <=> filemtime($a));
    }
    $total = count($vid);
    $pages = max(1, (int) ceil($total / $perPage));
    $slice = array_slice($vid, $start, $perPage);
    foreach ($slice as $vPath) {
        $currentVids[] = [
            'id' => 0,
            'filename' => basename($vPath),
            'title' => null,
            'description' => null,
            'media_date' => null,
            'media_time' => null
        ];
    }
}

$thumbDir = $dir . "thumbs" . DIRECTORY_SEPARATOR;
if (!is_dir($thumbDir)) {
    @mkdir($thumbDir, 0755, true);
}

$cssVer = file_exists(__DIR__ . '/../css/vidgallery.css') ? filemtime(__DIR__ . '/../css/vidgallery.css') : time();
$jsVer  = file_exists(__DIR__ . '/../js/vidgallery.js') ? filemtime(__DIR__ . '/../js/vidgallery.js') : time();
$globalVer = file_exists(__DIR__ . '/../css/global.css') ? filemtime(__DIR__ . '/../css/global.css') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Galeri Video - HS15</title>
  <link rel="stylesheet" href="../css/global.css?v=<?= $globalVer ?>">
  <link rel="stylesheet" href="../css/vidgallery.css?v=<?= $cssVer ?>">
  <link rel="icon" href="../img/logo.jpg" type="image/jpeg">
  <script src="../js/vidgallery.js?v=<?= $jsVer ?>" defer></script>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>

  <!-- Header Navigasi -->
  <header>
    <nav>
      <a href="choose.php" class="logo" id="logo-link">
        <img src="../img/logo.jpg" alt="HS15 Logo" class="logo-img">
        <span class="logo-title">HS15<span class="logo-sub"> - Komunitas Keliling Banjar</span></span>
      </a>

      <div class="admin-nav-links">
        <a href="choose.php" class="admin-nav-link" title="Ke Beranda Utama">
          <ion-icon name="home-outline"></ion-icon> <span>Beranda</span>
        </a>
        <a href="gallery.php" class="admin-nav-link" title="Buka Galeri Foto">
          <ion-icon name="images-outline"></ion-icon> <span>Foto</span>
        </a>
        <a href="vidgallery.php" class="admin-nav-link active" title="Buka Galeri Video">
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

  <main class="page-container">
    <div class="gallery-hero">
      <h1>Galeri Video</h1>
      <p>Koleksi rekaman dan dokumentasi video kegiatan HS15</p>
      <div class="gallery-stats-badge">
        <span class="badge-item">
          <svg class="badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
          Total <?= $stats['videos'] ?> Video
        </span>
      </div>
    </div>

    <div class="gallery" id="gallery">
      <?php if (!empty($currentVids)): ?>
        <?php foreach ($currentVids as $v):
          $file = $v['filename'];
          $nameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
          $formattedCaption = formatMediaCaption($file, $v['title'] ?? null, $v['media_date'] ?? null, $v['media_time'] ?? null);
          $desc = !empty($v['description']) ? $v['description'] : '';
          $captionTooltip = $formattedCaption . ($desc ? " — $desc" : "");

          $thumbPath = get_media_thumb_url($file, 'video');
          $videoSrc = media_url($file);
        ?>
          <div class="vWrap">
            <div class="video-box">
          <video controls preload="none" data-poster="<?= $thumbPath ?>" data-filename="<?= htmlspecialchars($file) ?>" playsinline>
            <source src="<?= $videoSrc ?>">
            Browser Anda tidak mendukung pemutar video.
          </video>
            </div>
            <div class="vFooter">
              <div class="vCaption" title="<?= htmlspecialchars($captionTooltip) ?>"><?= htmlspecialchars($formattedCaption) ?></div>
              <a href="<?= $videoSrc ?>" download="<?= htmlspecialchars(basename($file)) ?>" class="v-download-btn" title="Download Video <?= htmlspecialchars($formattedCaption) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="7 10 12 15 17 10"></polyline>
                  <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align:center; grid-column:1/-1; color:rgba(255,255,255,0.6); padding:40px;">Belum ada video dalam galeri saat ini.</p>
      <?php endif; ?>
    </div>

    <!-- Pagination Modern (Sama seperti Panel Admin) -->
    <?php if ($pages > 1): ?>
    <div class="gallery-pagination-wrap">
      <div class="pagination-links">
        <!-- Tombol Halaman Pertama (<<) & Sebelumnya (<) -->
        <?php if ($page > 1): ?>
          <a href="?page=1" class="page-num" title="Halaman Pertama">&laquo;</a>
          <a href="?page=<?= $page - 1 ?>" class="page-num" title="Halaman Sebelumnya">&lsaquo;</a>
        <?php else: ?>
          <span class="page-num is-disabled" title="Halaman Pertama">&laquo;</span>
          <span class="page-num is-disabled" title="Halaman Sebelumnya">&lsaquo;</span>
        <?php endif; ?>

        <!-- Nomor Halaman -->
        <?php 
          $startP = max(1, $page - 2);
          $endP   = min($pages, $page + 2);
          for ($p = $startP; $p <= $endP; $p++): 
        ?>
          <a href="?page=<?= $p ?>" class="page-num <?= $p === $page ? 'active' : '' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>

        <!-- Tombol Halaman Berikutnya (>) & Terakhir (>>) -->
        <?php if ($page < $pages): ?>
          <a href="?page=<?= $page + 1 ?>" class="page-num" title="Halaman Selanjutnya">&rsaquo;</a>
          <a href="?page=<?= $pages ?>" class="page-num" title="Halaman Terakhir">&raquo;</a>
        <?php else: ?>
          <span class="page-num is-disabled" title="Halaman Selanjutnya">&rsaquo;</span>
          <span class="page-num is-disabled" title="Halaman Terakhir">&raquo;</span>
        <?php endif; ?>
      </div>

      <div class="pagination-info">Menampilkan halaman <?= $page ?> dari <?= $pages ?> (Total <?= $total ?> video)</div>
    </div>
    <?php elseif ($total > 0): ?>
      <div class="gallery-pagination-wrap">
        <div class="pagination-info">Menampilkan seluruh <?= $total ?> video</div>
      </div>
    <?php endif; ?>
  </main>

  <!-- Footer Copyright -->
  <footer class="main-footer">
    <p>&copy; 2026 HS15 - Komunitas Keliling Banjar. All rights reserved.</p>
  </footer>

  <script src="../js/nav.js?v=20260924_v2"></script>
</body>
</html>
