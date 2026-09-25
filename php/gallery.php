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
$roleBadgeText    = $isSuperAdmin ? 'Super Admin' : 'Member';

sync_media_files_to_db($conn);
$dir = get_media_base_dir();
$thumbDir = get_thumbs_base_dir();

$stats = getGalleryStats();

// Pagination & Query dari tabel media
$perPage = 16; // 4 kolom x 4 baris
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$totalStmt = $conn->query("SELECT COUNT(*) as c FROM media WHERE media_type = 'photo'");
$total = (int) ($totalStmt ? ($totalStmt->fetch_assoc()['c'] ?? 0) : 0);

$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, $page);
$start = ($page - 1) * $perPage;

$stmt = $conn->prepare("
    SELECT id, filename, original_name, title, description, media_date, media_time 
    FROM media 
    WHERE media_type = 'photo' 
    ORDER BY media_date DESC, media_time DESC, id DESC 
    LIMIT ? OFFSET ?
");
$images = [];
if ($stmt) {
    $stmt->bind_param("ii", $perPage, $start);
    $stmt->execute();
    $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fallback jika database belum terindeks
if (empty($images) && $total === 0) {
    $allImages = glob("$dir*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) ?: [];
    usort($allImages, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $total = count($allImages);
    $pages = max(1, (int) ceil($total / $perPage));
    $slice = array_slice($allImages, $start, $perPage);
    foreach ($slice as $imgFile) {
        $images[] = [
            'id' => 0,
            'filename' => basename($imgFile),
            'title' => null,
            'description' => null,
            'media_date' => null,
            'media_time' => null
        ];
    }
}

$cssVer = file_exists(__DIR__ . '/../css/gallery.css') ? filemtime(__DIR__ . '/../css/gallery.css') : time();
$jsVer  = file_exists(__DIR__ . '/../js/gallery.js') ? filemtime(__DIR__ . '/../js/gallery.js') : time();
$globalVer = file_exists(__DIR__ . '/../css/global.css') ? filemtime(__DIR__ . '/../css/global.css') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Galeri Foto - HS15</title>
  <link rel="stylesheet" href="../css/global.css?v=<?= $globalVer ?>">
  <link rel="stylesheet" href="../css/gallery.css?v=<?= $cssVer ?>">
  <link rel="icon" href="../img/logo.jpg" type="image/jpeg">
  <script src="../js/gallery.js?v=<?= $jsVer ?>" defer></script>
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
        <a href="gallery.php" class="admin-nav-link active" title="Buka Galeri Foto">
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

  <main class="page-container">
    <div class="gallery-hero">
      <h1>Galeri Foto</h1>
      <p>Koleksi arsip dokumentasi momen kebersamaan dan perjalanan HS15</p>
      <div class="gallery-stats-badge">
        <span class="badge-item">
          <svg class="badge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
          Total <?= $stats['photos'] ?> Foto
        </span>
      </div>
    </div>

    <div class="gallery" id="photoGallery">
      <?php if (!empty($images)): ?>
        <?php foreach ($images as $index => $i): 
          $img = $i['filename'];
          $formattedCaption = formatMediaCaption($img, $i['title'] ?? null, $i['media_date'] ?? null, $i['media_time'] ?? null);
          $desc = !empty($i['description']) ? $i['description'] : '';
          $lightboxCaption = $formattedCaption . ($desc ? " — $desc" : "");

          $displayThumb = get_media_thumb_url($img, 'photo');
          $fullSrc = media_url($img);
        ?>
          <figure class="gallery-item" data-index="<?= $index ?>" data-full="<?= $fullSrc ?>" data-caption="<?= htmlspecialchars($lightboxCaption) ?>">
            <div class="gallery-item__media">
              <img src="<?= $displayThumb ?>" 
                   data-full="<?= $fullSrc ?>"
                   alt="<?= htmlspecialchars($formattedCaption) ?>" 
                   loading="lazy"
                   decoding="async"
                   onload="this.classList.add('loaded')">
            </div>
            <figcaption>
              <span class="gallery-caption" title="<?= htmlspecialchars($formattedCaption) ?>"><?= htmlspecialchars($formattedCaption) ?></span>
              <a href="<?= $fullSrc ?>" download="<?= htmlspecialchars(basename($img)) ?>" class="gallery-download-btn" title="Download Foto Asli" onclick="event.stopPropagation();">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                  <polyline points="7 10 12 15 17 10"></polyline>
                  <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
              </a>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align:center; grid-column:1/-1; color:rgba(255,255,255,0.6); padding:40px;">Belum ada foto dalam galeri saat ini.</p>
      <?php endif; ?>
    </div>

    <!-- Pagination Modern -->
    <?php if ($pages > 0): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=1" class="page-btn page-edge" title="Halaman Pertama">&laquo; Pertama</a>
        <a href="?page=<?= $page - 1 ?>" class="page-btn" title="Halaman Sebelumnya">&lsaquo; Sebelumnya</a>
      <?php else: ?>
        <span class="page-btn page-edge is-disabled" aria-disabled="true">&laquo; Pertama</span>
        <span class="page-btn is-disabled" aria-disabled="true">&lsaquo; Sebelumnya</span>
      <?php endif; ?>
      
      <span class="page-info">Halaman <?= $page ?> dari <?= $pages ?></span>
      
      <?php if ($page < $pages): ?>
        <a href="?page=<?= $page + 1 ?>" class="page-btn" title="Halaman Selanjutnya">Selanjutnya &rsaquo;</a>
        <a href="?page=<?= $pages ?>" class="page-btn page-edge" title="Halaman Terakhir">Akhir &raquo;</a>
      <?php else: ?>
        <span class="page-btn is-disabled" aria-disabled="true">Selanjutnya &rsaquo;</span>
        <span class="page-btn page-edge is-disabled" aria-disabled="true">Akhir &raquo;</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </main>

  <!-- Footer Copyright -->
  <footer class="main-footer">
    <p>&copy; 2026 HS15 - Komunitas Keliling Banjar. All rights reserved.</p>
  </footer>

  <script src="../js/nav.js?v=20260918"></script>

  <!-- Lightbox Modal Fullscreen -->
  <div id="lightbox" class="lightbox" role="dialog" aria-modal="true">
    <div class="lightbox__backdrop"></div>
    
    <a id="downloadBtn" class="lightbox__download" href="" download title="Download Foto Asli">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
      </svg>
    </a>
    <button id="closeBtn" class="lightbox__close" title="Tutup (Esc)">&times;</button>
    <button id="prevBtn" class="lightbox__prev" title="Sebelumnya (Panah Kiri)">&lsaquo;</button>
    <button id="nextBtn" class="lightbox__next" title="Selanjutnya (Panah Kanan)">&rsaquo;</button>

    <figure class="lightbox__figure">
      <img id="lightboxImg" class="lightbox__img" src="" alt="">
      <figcaption id="caption" class="lightbox__caption"></figcaption>
      <div id="lightboxCounter" class="lightbox__counter"></div>
    </figure>
  </div>

  <script src="../js/nav.js?v=20260924_v2"></script>
</body>
</html>
