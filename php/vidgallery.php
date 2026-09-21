<?php
function formatMediaCaption($filename) {
    $base = pathinfo($filename, PATHINFO_FILENAME);
    // Format timestamp: VID_YYYYMMDD_HHMMSS atau video_YYYYMMDD_HHMMSS
    if (preg_match('/^(?:VID_|video_)?(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/i', $base, $m)) {
        $months = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];
        $year = $m[1];
        $month = $months[$m[2]] ?? $m[2];
        $day = ltrim($m[3], '0');
        $time = $m[4] . ':' . $m[5];
        return "$day $month $year • $time";
    }
    // Format WhatsApp: VID-YYYYMMDD-WAxxxx
    if (preg_match('/^(?:VID|IMG)-(\d{4})(\d{2})(\d{2})-/i', $base, $m)) {
        $months = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];
        $year = $m[1];
        $month = $months[$m[2]] ?? $m[2];
        $day = ltrim($m[3], '0');
        return "$day $month $year";
    }
    // Format biasa
    return ucwords(str_replace(['_', '-'], ' ', $base));
}

$dir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "gallery" . DIRECTORY_SEPARATOR;
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

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12; // 12 video per halaman
$total = count($vid);
$pages = ceil($total / $perPage);
$start = ($page - 1) * $perPage;
$currentVids = array_slice($vid, $start, $perPage);

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
</head>
<body>

  <!-- Header Navigasi -->
  <header>
    <nav>
      <a href="../html/choose.html" class="logo" id="logo-link">
        <img src="../img/logo.jpg" alt="HS15 Logo" class="logo-img">
        <span>HS15 - Komunitas Keliling Banjar</span>
      </a>
      <ul id="menu" class="nav-main">
        <li><a href="../html/choose.html">Beranda</a></li>
        <li><a href="../php/gallery.php">Foto</a></li>
        <li><a href="../php/vidgallery.php" class="nav-active">Video</a></li>
      </ul>
      <div class="profile-menu">
        <button type="button" class="profile-button" aria-expanded="false" aria-controls="profile-dropdown" title="Menu akun">
          <img src="../img/logo.jpg" alt="Foto profil" class="profile-avatar">
        </button>
        <div id="profile-dropdown" class="profile-dropdown">
          <a href="../php/account.php">Setelan Akun</a>
          <a href="../php/logout.php" class="dropdown-logout">Log Out</a>
        </div>
      </div>
    </nav>
  </header>

  <main class="page-container">
    <div class="gallery-hero">
      <h1>Galeri Video</h1>
      <p>Koleksi rekaman dan dokumentasi video kegiatan HS15</p>
    </div>

    <div class="gallery" id="gallery">
      <?php if (!empty($currentVids)): ?>
        <?php foreach ($currentVids as $v):
          $file = basename($v);
          $nameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
          $formattedCaption = formatMediaCaption($file);
          $targetThumb = $thumbDir . $nameWithoutExt . '.jpg';
          $thumbPath = '';

          // Cek ketersediaan thumbnail
          if (file_exists($targetThumb)) {
              $thumbPath = '../gallery/thumbs/' . rawurlencode($nameWithoutExt . '.jpg');
          } else {
              $ffmpegPath = __DIR__ . DIRECTORY_SEPARATOR . 'ffmpeg.exe';
              if (file_exists($ffmpegPath)) {
                  $videoPath = $dir . $file;
                  $cmd = "\"$ffmpegPath\" -i \"$videoPath\" -ss 00:00:02.000 -vframes 1 \"$targetThumb\" -y 2>&1";
                  @shell_exec($cmd);
                  if (file_exists($targetThumb)) {
                      $thumbPath = '../gallery/thumbs/' . rawurlencode($nameWithoutExt . '.jpg');
                  }
              }
          }

          $videoSrc = '../gallery/' . rawurlencode($file);
        ?>
          <div class="vWrap">
            <div class="video-box">
              <video controls preload="none" <?php if ($thumbPath): ?>poster="<?= $thumbPath ?>"<?php endif; ?> playsinline>
                <source src="<?= $videoSrc ?>">
                Browser Anda tidak mendukung pemutar video.
              </video>
            </div>
            <div class="vCaption" title="<?= htmlspecialchars($formattedCaption) ?>"><?= htmlspecialchars($formattedCaption) ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align:center; grid-column:1/-1; color:rgba(255,255,255,0.6); padding:40px;">Belum ada video dalam galeri saat ini.</p>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
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

</body>
</html>
