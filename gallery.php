<?php
function formatMediaCaption($filename) {
    $base = pathinfo($filename, PATHINFO_FILENAME);
    // Format timestamp: IMG_YYYYMMDD_HHMMSS atau VID_YYYYMMDD_HHMMSS
    if (preg_match('/^(?:IMG_|VID_|video_)?(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/i', $base, $m)) {
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

// Fungsi pembuatan thumbnail
function makeThumbnail($src, $dest, $thumbWidth = 400) {
    $info = getimagesize($src);
    if (!$info) return false;
    [$width, $height] = $info;
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $image = @imagecreatefromjpeg($src); break;
        case 'image/png':  $image = @imagecreatefrompng($src); break;
        case 'image/gif':  $image = @imagecreatefromgif($src); break;
        case 'image/webp': $image = @imagecreatefromwebp($src); break;
        default: return false;
    }

    if (!$image) return false;

    $newHeight = floor($height * ($thumbWidth / $width));
    $tmp = imagecreatetruecolor($thumbWidth, $newHeight);
    
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
    }

    imagecopyresampled($tmp, $image, 0, 0, 0, 0, $thumbWidth, $newHeight, $width, $height);
    imagejpeg($tmp, $dest, 85);
    imagedestroy($image);
    imagedestroy($tmp);
    return true;
}

$dir = __DIR__ . "/gallery/";
$thumbDir = $dir . "thumbs/";
if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

$allImages = glob("$dir*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE);
if ($allImages === false) $allImages = [];
usort($allImages, fn($a, $b) => filemtime($b) <=> filemtime($a));

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 16; // 4 kolom x 4 baris
$total = count($allImages);
$pages = ceil($total / $perPage);
$start = ($page - 1) * $perPage;
$images = array_slice($allImages, $start, $perPage);

$cssVer = file_exists('gallery.css') ? filemtime('gallery.css') : time();
$jsVer  = file_exists('gallery.js') ? filemtime('gallery.js') : time();
$globalVer = file_exists('global.css') ? filemtime('global.css') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Galeri Foto - HS15</title>
  <link rel="stylesheet" href="global.css?v=<?= $globalVer ?>">
  <link rel="stylesheet" href="gallery.css?v=<?= $cssVer ?>">
  <link rel="icon" href="img/logo.jpg" type="image/jpeg">
  <script src="gallery.js?v=<?= $jsVer ?>" defer></script>
</head>
<body>

  <!-- Header Navigasi -->
  <header>
    <nav>
      <a href="choose.html" class="logo" id="logo-link">
        <img src="img/logo.jpg" alt="HS15 Logo" class="logo-img">
        <span>HS15 - Komunitas Keliling Banjar</span>
      </a>
      <ul id="menu">
        <li><a href="choose.html">Beranda</a></li>
        <li><a href="gallery.php" class="nav-active">Foto</a></li>
        <li><a href="vidgallery.php">Video</a></li>
        <li><a href="logout.php" class="nav-logout">Log Out</a></li>
      </ul>
    </nav>
  </header>

  <main class="page-container">
    <div class="gallery-hero">
      <h1>Galeri Foto</h1>
      <p>Koleksi arsip dokumentasi momen kebersamaan dan perjalanan HS15</p>
    </div>

    <div class="gallery" id="photoGallery">
      <?php if (!empty($images)): ?>
        <?php foreach ($images as $index => $i): 
          $img = basename($i);
          $formattedCaption = formatMediaCaption($img);
          $thumbPath = $thumbDir . $img;

          if (!file_exists($thumbPath)) {
            makeThumbnail($i, $thumbPath, 600);
          }
          $displayThumb = file_exists($thumbPath) 
            ? 'gallery/thumbs/' . rawurlencode($img) 
            : 'gallery/' . rawurlencode($img);
          $fullSrc = 'gallery/' . rawurlencode($img);
        ?>
          <figure class="gallery-item" data-index="<?= $index ?>" data-full="<?= $fullSrc ?>" data-caption="<?= htmlspecialchars($formattedCaption) ?>">
            <img src="<?= $displayThumb ?>" 
                 data-full="<?= $fullSrc ?>"
                 alt="<?= htmlspecialchars($formattedCaption) ?>" 
                 loading="lazy">
            <figcaption><?= htmlspecialchars($formattedCaption) ?></figcaption>
          </figure>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align:center; grid-column:1/-1; color:rgba(255,255,255,0.6); padding:40px;">Belum ada foto dalam galeri saat ini.</p>
      <?php endif; ?>
    </div>

    <!-- Pagination Modern -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=1" class="page-btn page-edge" title="Halaman Pertama">« Pertama</a>
        <a href="?page=<?= $page - 1 ?>" class="page-btn" title="Halaman Sebelumnya">‹ Sebelumnya</a>
      <?php endif; ?>
      
      <span class="page-info">Halaman <?= $page ?> dari <?= $pages ?></span>
      
      <?php if ($page < $pages): ?>
        <a href="?page=<?= $page + 1 ?>" class="page-btn" title="Halaman Selanjutnya">Selanjutnya ›</a>
        <a href="?page=<?= $pages ?>" class="page-btn page-edge" title="Halaman Terakhir">Akhir »</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </main>

  <!-- Footer Copyright -->
  <footer class="main-footer">
    <p>&copy; 2026 HS15 - Komunitas Keliling Banjar. All rights reserved.</p>
  </footer>

  <!-- Lightbox Modal Fullscreen -->
  <div id="lightbox" class="lightbox" role="dialog" aria-modal="true">
    <div class="lightbox__backdrop"></div>
    
    <button id="closeBtn" class="lightbox__close" title="Tutup (Esc)">&times;</button>
    <button id="prevBtn" class="lightbox__prev" title="Sebelumnya (Panah Kiri)">‹</button>
    <button id="nextBtn" class="lightbox__next" title="Selanjutnya (Panah Kanan)">›</button>

    <figure class="lightbox__figure">
      <img id="lightboxImg" class="lightbox__img" src="" alt="">
      <figcaption id="caption" class="lightbox__caption"></figcaption>
      <div id="lightboxCounter" class="lightbox__counter"></div>
    </figure>
  </div>

</body>
</html>
