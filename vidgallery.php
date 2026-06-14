<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HS15</title>
  <link rel="stylesheet" type="text/css" href="global.css">
  <link rel="stylesheet" type="text/css" href="vidgallery.css">
  <link rel="icon" href="img/logo.jpg" type="image/svg+xml">
  <script src="vidgallery.js" defer></script>
</head>
<body>

  <header>
    <nav>
      <a href="choose.html" class="logo" id="logo-link">
        <img src="img/logo.jpg" alt="HS15 Logo" class="logo-img">
        <span>HS15 - Komunitas Keliling Banjar</span>
      </a>
      
      <ul id="menu">
        <li><a href="choose.html">Beranda</a></li>
        <li><a href="gallery.php">Foto</a></li>
        <li><a href="vidgallery.php" class="nav-active">Video</a></li>
        <li><a href="index.html">Log Out</a></li>
      </ul>
    </nav>
  </header>

  <div class="gallery" id="gallery">
    <?php
    $dir = __DIR__ . DIRECTORY_SEPARATOR . "gallery" . DIRECTORY_SEPARATOR;
    $cacheFile = sys_get_temp_dir() . '/vidgallery_cache_' . md5($dir) . '.json';
    $cacheTime = 3600; // 1 jam
    
    // Load dari cache jika ada dan masih valid
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
      $vid = json_decode(file_get_contents($cacheFile), true);
    } else {
      $vid = glob($dir . "*.{webm,mp4,ogg}", GLOB_BRACE);
      // Simpan ke cache
      @file_put_contents($cacheFile, json_encode($vid));
    }

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 12; // 12 video per halaman
    $total = count($vid);
    $pages = ceil($total / $perPage);
    $start = ($page - 1) * $perPage;
    $vid = array_slice($vid, $start, $perPage);

    if (!empty($vid)) {
      foreach ($vid as $v) {
        $file = basename($v);
        $caption = htmlspecialchars(pathinfo($file, PATHINFO_FILENAME));
        
// Cek thumbnail di folder thumbs/
$thumbDir = $dir . "thumbs" . DIRECTORY_SEPARATOR;
$nameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
$thumbPath = null;
$targetThumb = $thumbDir . $nameWithoutExt . '.jpg';

// Buat folder thumbs otomatis jika belum ada
if (!is_dir($thumbDir)) {
  mkdir($thumbDir, 0777, true);
}

  // Cek apakah thumbnail sudah ada
      if (file_exists($targetThumb)) {
        $thumbPath = 'gallery/thumbs/' . rawurlencode($nameWithoutExt . '.jpg');
      } else {

  // Jika tidak ada, eksekusi FFmpeg
      $ffmpegPath = __DIR__ . DIRECTORY_SEPARATOR . 'ffmpeg.exe';
      $videoPath = $dir . $file;
  
    if (file_exists($ffmpegPath)) {
  
  // Perintah mengambil frame di detik ke-00:00:02
    $cmd = "\"$ffmpegPath\" -i \"$videoPath\" -ss 00:00:02.000 -vframes 1 \"$targetThumb\" -y 2>&1";
    shell_exec($cmd);
    
  // Konfirmasi ulang jika file berhasil dibuat
    if (file_exists($targetThumb)) {
    $thumbPath = 'gallery/thumbs/' . rawurlencode($nameWithoutExt . '.jpg');
    }
  
  } else {
    echo "Error: FFmpeg tidak ditemukan di $ffmpegPath. Pastikan ffmpeg.exe ada di direktori yang sama dengan skrip ini.";
  }
      }
        ?>
        <div class="vWrap" data-video="gallery/<?= rawurlencode($file) ?>">
          <video controls preload="none" <?php if ($thumbPath): ?>poster="<?= $thumbPath ?>"<?php endif; ?>></video>
          <div class="vCaption"><?= $caption ?></div>
        </div>
        <?php
      }
    } else {
      echo "<p>Tidak ada video di gallery.</p>";
    }
    ?>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?page=1">« Pertama</a>
      <a href="?page=<?= $page - 1 ?>">‹ Sebelumnya</a>
    <?php endif; ?>
    
    <span class="page-info">Halaman <?= $page ?> dari <?= $pages ?></span>
    
    <?php if ($page < $pages): ?>
      <a href="?page=<?= $page + 1 ?>">Selanjutnya ›</a>
      <a href="?page=<?= $pages ?>">Akhir »</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</body>
</html>
