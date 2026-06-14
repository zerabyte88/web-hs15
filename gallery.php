<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>HS15</title>
  <link rel="stylesheet" href="gallery.css">
  <link rel="stylesheet" href="global.css">
  <link rel="icon" href="img/logo.jpg" type="image/svg+xml">
  <script src="gallery.js" defer></script>
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
                <li><a href="gallery.php" class="nav-active">Foto</a></li>
                <li><a href="vidgallery.php">Video</a></li>
                <li><a href="index.html">Log Out</a></li>
            </ul>
        </nav>
    </header>

  <div class="gallery">
    <?php
      // thumbnail
      function makeThumbnail($src, $dest, $thumbWidth = 400) {
        $info = getimagesize($src);
        if (!$info) return false;
        [$width, $height] = $info;
        $mime = $info['mime'];

        switch ($mime) {
          case 'image/jpeg': $image = imagecreatefromjpeg($src); break;
          case 'image/png':  $image = imagecreatefrompng($src); break;
          case 'image/gif':  $image = imagecreatefromgif($src); break;
          case 'image/webp': $image = imagecreatefromwebp($src); break;
          default: return false;
        }

        $newHeight = floor($height * ($thumbWidth / $width));
        $tmp = imagecreatetruecolor($thumbWidth, $newHeight);
        imagecopyresampled($tmp, $image, 0, 0, 0, 0, $thumbWidth, $newHeight, $width, $height);

        imagejpeg($tmp, $dest, 90);
        imagedestroy($image);
        imagedestroy($tmp);
        return true;
      }

      $dir = __DIR__ . "/gallery/";
      $thumbDir = $dir . "thumbs/";
      if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

      $images = glob("$dir*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE);
      usort($images, fn($a, $b) => filemtime($b) <=> filemtime($a));

      // Pagination
      $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
      $perPage = 16; // 4 kolom x 4 baris
      $total = count($images);
      $pages = ceil($total / $perPage);
      $start = ($page - 1) * $perPage;
      $images = array_slice($images, $start, $perPage);

      foreach ($images as $i) {
        $img = basename($i);
        $caption = htmlspecialchars(pathinfo($img, PATHINFO_FILENAME));
        $thumbPath = $thumbDir . $img;

        if (!file_exists($thumbPath)) {
          makeThumbnail($i, $thumbPath, 600);
        }

        echo "<figure>
                <img src='gallery/thumbs/".rawurlencode($img)."' 
                     data-full='gallery/".rawurlencode($img)."' 
                     alt='$caption' loading='lazy'>
                <figcaption>$caption</figcaption>
              </figure>";
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

  <!-- Overlay fullscreen -->
<div id="lightbox" class="lightbox">
  <div class="lightbox__backdrop"></div>
  
  <figure class="lightbox__figure">
    <img id="lightboxImg" class="lightbox__img" src="" alt="">
    <figcaption id="caption" class="lightbox__caption"></figcaption>
  </figure>

  <button id="closeBtn" class="lightbox__close">&times;</button>
</div>

</body>
</html>
