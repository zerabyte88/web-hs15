<?php
// admin.php - Panel Administrasi HS15 (Upload, Kelola Media, dan Kelola Akun & Peran)
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/stats_helper.php';

// 1. Otorisasi Keamanan: Wajib Login & Peran Admin
require_admin($conn);

// 2. Sinkronisasi Otomatis Media Folder gallery/ dengan Database
sync_media_files_to_db($conn);

$galleryDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'gallery' . DIRECTORY_SEPARATOR;
$thumbsDir  = $galleryDir . 'thumbs' . DIRECTORY_SEPARATOR;
if (!is_dir($thumbsDir)) {
    @mkdir($thumbsDir, 0755, true);
}

$currentUserId = (int) $_SESSION['user_id'];
$currentUserEmail = $_SESSION['email'] ?? 'Admin';

$flashSuccess = '';
$flashError = '';

// ============================================================================
// 3. Pemrosesan Aksi Form (POST)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        header("Location: admin.php?error=csrf");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --- A. Upload Media Baru ---
    if ($action === 'upload_media') {
        if (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
            header("Location: admin.php?tab=upload&error=no_file");
            exit;
        }

        $file = $_FILES['media_file'];
        $origName = basename($file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        $photoExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $videoExts = ['mp4', 'webm', 'ogg', 'm4v'];

        $mediaType = null;
        if (in_array($ext, $photoExts, true)) {
            $mediaType = 'photo';
        } elseif (in_array($ext, $videoExts, true)) {
            $mediaType = 'video';
        } else {
            header("Location: admin.php?tab=upload&error=invalid_type");
            exit;
        }

        // Generate nama file yang unik & rapi
        $prefix = ($mediaType === 'photo') ? 'IMG_' : 'VID_';
        $timestamp = date('Ymd_His');
        $uniqueSuffix = bin2hex(random_bytes(3));
        $newFilename = "{$prefix}{$timestamp}_{$uniqueSuffix}.{$ext}";
        $destPath = $galleryDir . $newFilename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            header("Location: admin.php?tab=upload&error=upload_failed");
            exit;
        }

        // Generate thumbnail/poster
        $nameWithoutExt = pathinfo($newFilename, PATHINFO_FILENAME);
        if ($mediaType === 'photo') {
            $thumbDest = $thumbsDir . $nameWithoutExt . '.webp';
            generate_photo_thumbnail($destPath, $thumbDest, 480);
        } else {
            $posterDest = $thumbsDir . $nameWithoutExt . '.jpg';
            generate_video_poster($destPath, $posterDest);
        }

        // Ambil input form metadata
        $titleInput = trim($_POST['title'] ?? '');
        $dateInput  = trim($_POST['media_date'] ?? '');
        $timeInput  = trim($_POST['media_time'] ?? '');
        $descInput  = trim($_POST['description'] ?? '');

        $mediaDate = !empty($dateInput) ? $dateInput : date('Y-m-d');
        $mediaTime = !empty($timeInput) ? $timeInput : date('H:i:s');
        $title = !empty($titleInput) ? $titleInput : formatMediaCaption($newFilename, null, $mediaDate, $mediaTime);
        $description = !empty($descInput) ? $descInput : null;

        $stmt = $conn->prepare("
            INSERT INTO media (filename, original_name, media_type, title, description, media_date, media_time)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssss", $newFilename, $origName, $mediaType, $title, $description, $mediaDate, $mediaTime);
        $stmt->execute();

        header("Location: admin.php?tab=media&success=uploaded");
        exit;
    }

    // --- B. Edit Metadata Media ---
    if ($action === 'edit_media') {
        $mediaId   = (int) ($_POST['media_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $dateInput = trim($_POST['media_date'] ?? '');
        $timeInput = trim($_POST['media_time'] ?? '');
        $descInput = trim($_POST['description'] ?? '');

        if ($mediaId <= 0) {
            header("Location: admin.php?tab=media&error=invalid_id");
            exit;
        }

        $mediaDate = !empty($dateInput) ? $dateInput : null;
        $mediaTime = !empty($timeInput) ? $timeInput : null;
        $description = !empty($descInput) ? $descInput : null;

        $stmt = $conn->prepare("
            UPDATE media 
            SET title = ?, media_date = ?, media_time = ?, description = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssi", $title, $mediaDate, $mediaTime, $description, $mediaId);
        $stmt->execute();

        header("Location: admin.php?tab=media&success=edited");
        exit;
    }

    // --- C. Ganti File Media ---
    if ($action === 'replace_media') {
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        if ($mediaId <= 0 || !isset($_FILES['replace_file']) || $_FILES['replace_file']['error'] !== UPLOAD_ERR_OK) {
            header("Location: admin.php?tab=media&error=replace_failed");
            exit;
        }

        $stmt = $conn->prepare("SELECT filename, media_type FROM media WHERE id = ?");
        $stmt->bind_param("i", $mediaId);
        $stmt->execute();
        $oldMedia = $stmt->get_result()->fetch_assoc();

        if (!$oldMedia) {
            header("Location: admin.php?tab=media&error=not_found");
            exit;
        }

        $file = $_FILES['replace_file'];
        $origName = basename($file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        $photoExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $videoExts = ['mp4', 'webm', 'ogg', 'm4v'];

        $newType = null;
        if (in_array($ext, $photoExts, true)) {
            $newType = 'photo';
        } elseif (in_array($ext, $videoExts, true)) {
            $newType = 'video';
        } else {
            header("Location: admin.php?tab=media&error=invalid_type");
            exit;
        }

        // Hapus file lama fisik & thumbnail
        $oldFile = $galleryDir . $oldMedia['filename'];
        if (is_file($oldFile)) @unlink($oldFile);

        $oldBase = pathinfo($oldMedia['filename'], PATHINFO_FILENAME);
        $oldThumbWebp = $thumbsDir . $oldBase . '.webp';
        $oldThumbJpg  = $thumbsDir . $oldBase . '.jpg';
        if (is_file($oldThumbWebp)) @unlink($oldThumbWebp);
        if (is_file($oldThumbJpg))  @unlink($oldThumbJpg);

        // Simpan file pengganti
        $prefix = ($newType === 'photo') ? 'IMG_' : 'VID_';
        $timestamp = date('Ymd_His');
        $uniqueSuffix = bin2hex(random_bytes(3));
        $newFilename = "{$prefix}{$timestamp}_{$uniqueSuffix}.{$ext}";
        $destPath = $galleryDir . $newFilename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            header("Location: admin.php?tab=media&error=upload_failed");
            exit;
        }

        // Generate thumbnail/poster baru
        $nameWithoutExt = pathinfo($newFilename, PATHINFO_FILENAME);
        if ($newType === 'photo') {
            generate_photo_thumbnail($destPath, $thumbsDir . $nameWithoutExt . '.webp', 480);
        } else {
            generate_video_poster($destPath, $thumbsDir . $nameWithoutExt . '.jpg');
        }

        $updateStmt = $conn->prepare("UPDATE media SET filename = ?, original_name = ?, media_type = ? WHERE id = ?");
        $updateStmt->bind_param("sssi", $newFilename, $origName, $newType, $mediaId);
        $updateStmt->execute();

        header("Location: admin.php?tab=media&success=replaced");
        exit;
    }

    // --- D. Hapus Media ---
    if ($action === 'delete_media') {
        $mediaId = (int) ($_POST['media_id'] ?? 0);
        if ($mediaId <= 0) {
            header("Location: admin.php?tab=media&error=invalid_id");
            exit;
        }

        $stmt = $conn->prepare("SELECT filename FROM media WHERE id = ?");
        $stmt->bind_param("i", $mediaId);
        $stmt->execute();
        $media = $stmt->get_result()->fetch_assoc();

        if ($media) {
            $filename = $media['filename'];
            $filePath = $galleryDir . $filename;
            if (is_file($filePath)) @unlink($filePath);

            $base = pathinfo($filename, PATHINFO_FILENAME);
            $thumbWebp = $thumbsDir . $base . '.webp';
            $thumbJpg  = $thumbsDir . $base . '.jpg';
            if (is_file($thumbWebp)) @unlink($thumbWebp);
            if (is_file($thumbJpg))  @unlink($thumbJpg);

            $delStmt = $conn->prepare("DELETE FROM media WHERE id = ?");
            $delStmt->bind_param("i", $mediaId);
            $delStmt->execute();
        }

        header("Location: admin.php?tab=media&success=deleted");
        exit;
    }

    // --- E. Tambah Pengguna Baru ---
    if ($action === 'add_user') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = ($_POST['role'] ?? 'member') === 'admin' ? 'admin' : 'member';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: admin.php?tab=users&error=invalid_email");
            exit;
        }
        if (strlen($password) < 6) {
            header("Location: admin.php?tab=users&error=short_password");
            exit;
        }

        // Cek email kembar
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            header("Location: admin.php?tab=users&error=email_exists");
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $hash, $role);
        $stmt->execute();

        header("Location: admin.php?tab=users&success=user_added");
        exit;
    }

    // --- F. Ubah Peran Pengguna (Admin vs Member) ---
    if ($action === 'update_role') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newRole = ($_POST['new_role'] ?? 'member') === 'admin' ? 'admin' : 'member';

        if ($targetUserId <= 0) {
            header("Location: admin.php?tab=users&error=invalid_user");
            exit;
        }

        // Cegah admin mendemosi akunnya sendiri
        if ($targetUserId === $currentUserId) {
            header("Location: admin.php?tab=users&error=self_role");
            exit;
        }

        // Pastikan tidak menghapus admin terakhir
        if ($newRole === 'member') {
            $adminCountRes = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'admin'");
            $adminCount = (int) ($adminCountRes->fetch_assoc()['c'] ?? 0);
            if ($adminCount <= 1) {
                header("Location: admin.php?tab=users&error=last_admin");
                exit;
            }
        }

        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $newRole, $targetUserId);
        $stmt->execute();

        header("Location: admin.php?tab=users&success=role_updated");
        exit;
    }

    // --- G. Reset Password Pengguna ---
    if ($action === 'reset_password') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newPassword  = $_POST['new_password'] ?? '';

        if ($targetUserId <= 0 || strlen($newPassword) < 6) {
            header("Location: admin.php?tab=users&error=short_password");
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $targetUserId);
        $stmt->execute();

        header("Location: admin.php?tab=users&success=password_reset");
        exit;
    }

    // --- H. Hapus Pengguna ---
    if ($action === 'delete_user') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);

        if ($targetUserId <= 0) {
            header("Location: admin.php?tab=users&error=invalid_user");
            exit;
        }

        // Cegah menghapus diri sendiri
        if ($targetUserId === $currentUserId) {
            header("Location: admin.php?tab=users&error=self_delete");
            exit;
        }

        // Cek peran target & lindungi admin terakhir
        $checkStmt = $conn->prepare("SELECT role, profile_photo FROM users WHERE id = ?");
        $checkStmt->bind_param("i", $targetUserId);
        $checkStmt->execute();
        $targetUser = $checkStmt->get_result()->fetch_assoc();

        if ($targetUser && $targetUser['role'] === 'admin') {
            $adminCountRes = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'admin'");
            $adminCount = (int) ($adminCountRes->fetch_assoc()['c'] ?? 0);
            if ($adminCount <= 1) {
                header("Location: admin.php?tab=users&error=last_admin");
                exit;
            }
        }

        if ($targetUser && !empty($targetUser['profile_photo'])) {
            $photoPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetUser['profile_photo']);
            if (is_file($photoPath)) @unlink($photoPath);
        }

        $delStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delStmt->bind_param("i", $targetUserId);
        $delStmt->execute();

        header("Location: admin.php?tab=users&success=user_deleted");
        exit;
    }
}

// ============================================================================
// 4. Pembacaan Pesan Notifikasi (Flash Messages)
// ============================================================================
$successMessages = [
    'uploaded'       => 'Media baru berhasil diunggah dan disimpan ke galeri.',
    'edited'         => 'Metadata media (judul, tanggal, jam, deskripsi) berhasil diperbarui.',
    'replaced'       => 'File media berhasil diganti dengan file yang baru.',
    'deleted'        => 'Media dan file thumbnail terkait berhasil dihapus permanen.',
    'user_added'     => 'Akun pengguna baru berhasil ditambahkan.',
    'role_updated'   => 'Peran pengguna (Role) berhasil diperbarui.',
    'password_reset' => 'Password pengguna berhasil direset.',
    'user_deleted'   => 'Akun pengguna berhasil dihapus permanen.'
];

$errorMessages = [
    'csrf'           => 'Token keamanan kedaluwarsa. Silakan coba kembali.',
    'no_file'        => 'Pilih file media terlebih dahulu.',
    'invalid_type'   => 'Format file tidak didukung. Format yang didukung: JPG, PNG, GIF, WEBP, MP4, WEBM.',
    'upload_failed'  => 'Gagal memproses file upload. Periksa ukuran file dan perizinan folder.',
    'invalid_id'     => 'ID media atau pengguna tidak valid.',
    'not_found'      => 'Data tidak ditemukan.',
    'invalid_email'  => 'Format email tidak valid.',
    'short_password' => 'Password minimal terdiri dari 6 karakter.',
    'email_exists'   => 'Email tersebut sudah terdaftar untuk akun lain.',
    'self_role'      => 'Anda tidak dapat mengubah peran akun Anda sendiri demi keamanan.',
    'self_delete'    => 'Anda tidak dapat menghapus akun Anda sendiri.',
    'last_admin'     => 'Operasi dibatalkan: Sistem harus memiliki setidaknya satu Admin aktif.',
    'replace_failed' => 'Gagal mengganti file media. Silakan coba lagi.'
];

if (isset($_GET['success'], $successMessages[$_GET['success']])) {
    $flashSuccess = $successMessages[$_GET['success']];
}
if (isset($_GET['error'], $errorMessages[$_GET['error']])) {
    $flashError = $errorMessages[$_GET['error']];
}

// Tab aktif ('media', 'upload', 'users')
$activeTab = $_GET['tab'] ?? 'media';
if (!in_array($activeTab, ['media', 'upload', 'users'], true)) {
    $activeTab = 'media';
}

// ============================================================================
// 5. Pengambilan Statistik Keseluruhan
// ============================================================================
$stats = [
    'photos' => 0,
    'videos' => 0,
    'users'  => 0,
    'admins' => 0
];
$stPhoto = $conn->query("SELECT COUNT(*) as c FROM media WHERE media_type = 'photo'")->fetch_assoc();
$stVideo = $conn->query("SELECT COUNT(*) as c FROM media WHERE media_type = 'video'")->fetch_assoc();
$stUsers = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc();
$stAdmin = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'admin'")->fetch_assoc();

$stats['photos'] = (int) ($stPhoto['c'] ?? 0);
$stats['videos'] = (int) ($stVideo['c'] ?? 0);
$stats['users']  = (int) ($stUsers['c'] ?? 0);
$stats['admins'] = (int) ($stAdmin['c'] ?? 0);

// ============================================================================
// 6. Pengambilan Data Media untuk Tab Kelola Media (Filter & Pagination)
// ============================================================================
$searchQuery  = trim($_GET['q'] ?? '');
$filterType   = $_GET['type'] ?? 'all';
$sortOption   = $_GET['sort'] ?? 'newest';

$whereClauses = [];
$params = [];
$paramTypes = "";

if ($filterType === 'photo' || $filterType === 'video') {
    $whereClauses[] = "media_type = ?";
    $params[] = $filterType;
    $paramTypes .= "s";
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(title LIKE ? OR filename LIKE ? OR description LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $paramTypes .= "sss";
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Order by
$orderSql = "ORDER BY media_date DESC, media_time DESC, id DESC";
if ($sortOption === 'oldest') {
    $orderSql = "ORDER BY media_date ASC, media_time ASC, id ASC";
} elseif ($sortOption === 'title') {
    $orderSql = "ORDER BY title ASC";
}

// Hitung total data hasil filter
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM media $whereSql");
if (!empty($paramTypes)) {
    $countStmt->bind_param($paramTypes, ...$params);
}
$countStmt->execute();
$totalMediaCount = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);

// Pagination
$perPage = 15;
$totalPages = max(1, (int) ceil($totalMediaCount / $perPage));
$page = isset($_GET['page']) ? max(1, min($totalPages, (int) $_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

$mediaListSql = "SELECT * FROM media $whereSql $orderSql LIMIT ? OFFSET ?";
$mediaStmt = $conn->prepare($mediaListSql);

$limitParamTypes = $paramTypes . "ii";
$limitParams = array_merge($params, [$perPage, $offset]);
$mediaStmt->bind_param($limitParamTypes, ...$limitParams);
$mediaStmt->execute();
$mediaList = $mediaStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// 7. Pengambilan Data Pengguna untuk Tab Kelola Pengguna
// ============================================================================
$usersList = $conn->query("
    SELECT id, email, role, profile_photo, created_at 
    FROM users 
    ORDER BY FIELD(role, 'admin', 'member'), id ASC
")->fetch_all(MYSQLI_ASSOC);

$cssVer = file_exists(__DIR__ . '/../css/admin.css') ? filemtime(__DIR__ . '/../css/admin.css') : time();
$globalVer = file_exists(__DIR__ . '/../css/global.css') ? filemtime(__DIR__ . '/../css/global.css') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel - HS15</title>
  <link rel="stylesheet" href="../css/global.css?v=<?= $globalVer ?>">
  <link rel="stylesheet" href="../css/admin.css?v=<?= $cssVer ?>">
  <link rel="icon" href="../img/logo.jpg" type="image/jpeg">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body class="admin-body">

  <!-- Header Navigasi Admin -->
  <header class="admin-header">
    <div class="admin-nav">
      <a href="choose.php" class="admin-logo" id="logo-link">
        <img src="../img/logo.jpg" alt="HS15 Logo" class="admin-logo-img">
        <span class="logo-title">HS15<span class="logo-sub"> - Komunitas Keliling Banjar</span></span>
      </a>

      <div class="admin-nav-links">
        <a href="choose.php" class="admin-nav-link" title="Ke Beranda Utama">
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
        <a href="admin.php" class="admin-nav-link nav-desktop-only active" style="color:#ff3b47;" title="Panel Administrasi">
          <ion-icon name="shield-checkmark-outline"></ion-icon> <span>Admin</span>
        </a>
      </div>

      <!-- Desktop User Profile -->
      <div class="admin-nav-user nav-desktop-only">
        <img src="<?= htmlspecialchars(get_current_user_avatar($conn)) ?>" alt="Foto profil" class="admin-header-avatar" onerror="this.onerror=null; this.src='../img/logo.jpg';">
        <div class="user-pill">
          <span class="user-email"><?= htmlspecialchars($currentUserEmail) ?></span>
          <span class="user-role-badge">Super Admin</span>
        </div>
        <a href="logout.php" class="btn-logout" title="Log Out Sesi">
          <ion-icon name="log-out-outline"></ion-icon>
        </a>
      </div>

      <!-- Mobile 3-Dots Menu -->
      <div class="mobile-menu-wrap">
        <button type="button" class="mobile-menu-btn active" aria-label="Menu akun dan opsi" aria-expanded="false">
          <ion-icon name="ellipsis-vertical"></ion-icon>
        </button>
        <div class="mobile-dropdown">
          <div class="mobile-dropdown-user">
            <img src="<?= htmlspecialchars(get_current_user_avatar($conn)) ?>" alt="Foto profil" class="mobile-dropdown-avatar" onerror="this.onerror=null; this.src='../img/logo.jpg';">
            <div class="mobile-dropdown-info">
              <span class="mobile-dropdown-email"><?= htmlspecialchars($currentUserEmail) ?></span>
              <span class="mobile-dropdown-role">Super Admin</span>
            </div>
          </div>
          <div class="mobile-dropdown-divider"></div>
          <a href="account.php" class="mobile-dropdown-item">
            <ion-icon name="person-circle-outline"></ion-icon> Setelan Akun
          </a>
          <a href="admin.php" class="mobile-dropdown-item active" style="color:#ff3b47;">
            <ion-icon name="shield-checkmark-outline"></ion-icon> Panel Admin
          </a>
          <div class="mobile-dropdown-divider"></div>
          <a href="logout.php" class="mobile-dropdown-item mobile-logout">
            <ion-icon name="log-out-outline"></ion-icon> Log Out
          </a>
        </div>
      </div>
    </div>
  </header>

  <main class="admin-container">
    <!-- Judul Halaman -->
    <div class="admin-hero">
      <h1>Panel Administrasi HS15</h1>
      <p>Kelola koleksi foto, video, metadata, serta hak akses pengguna komunitas dengan mudah.</p>
    </div>

    <!-- Alert Notifikasi Flash -->
    <?php if ($flashSuccess): ?>
      <div class="admin-alert admin-alert-success" role="alert">
        <ion-icon name="checkmark-circle-outline"></ion-icon>
        <div><?= htmlspecialchars($flashSuccess) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
      <div class="admin-alert admin-alert-error" role="alert">
        <ion-icon name="alert-circle-outline"></ion-icon>
        <div><?= htmlspecialchars($flashError) ?></div>
      </div>
    <?php endif; ?>

    <!-- Statistik Ringkasan -->
    <section class="stats-grid" aria-label="Statistik Komunitas">
      <div class="admin-stat-card">
        <div class="stat-icon-wrap icon-photos">
          <ion-icon name="images-outline"></ion-icon>
        </div>
        <div class="stat-content">
          <span class="stat-tit">Total Foto</span>
          <span class="stat-val"><?= $stats['photos'] ?></span>
        </div>
      </div>

      <div class="admin-stat-card">
        <div class="stat-icon-wrap icon-videos">
          <ion-icon name="videocam-outline"></ion-icon>
        </div>
        <div class="stat-content">
          <span class="stat-tit">Total Video</span>
          <span class="stat-val"><?= $stats['videos'] ?></span>
        </div>
      </div>

      <div class="admin-stat-card">
        <div class="stat-icon-wrap icon-users">
          <ion-icon name="people-outline"></ion-icon>
        </div>
        <div class="stat-content">
          <span class="stat-tit">Total Pengguna</span>
          <span class="stat-val"><?= $stats['users'] ?></span>
        </div>
      </div>

      <div class="admin-stat-card">
        <div class="stat-icon-wrap icon-admins">
          <ion-icon name="shield-checkmark-outline"></ion-icon>
        </div>
        <div class="stat-content">
          <span class="stat-tit">Administrator</span>
          <span class="stat-val"><?= $stats['admins'] ?></span>
        </div>
      </div>
    </section>

    <!-- Navigasi Tab -->
    <nav class="admin-tabs" aria-label="Navigasi Panel Admin">
      <button type="button" class="tab-btn <?= $activeTab === 'media' ? 'active' : '' ?>" data-tab="media">
        <ion-icon name="folder-open-outline"></ion-icon> Kelola Media (<?= $totalMediaCount ?>)
      </button>
      <button type="button" class="tab-btn <?= $activeTab === 'upload' ? 'active' : '' ?>" data-tab="upload">
        <ion-icon name="cloud-upload-outline"></ion-icon> Upload Media Baru
      </button>
      <button type="button" class="tab-btn <?= $activeTab === 'users' ? 'active' : '' ?>" data-tab="users">
        <ion-icon name="people-outline"></ion-icon> Kelola Pengguna & Peran (<?= count($usersList) ?>)
      </button>
    </nav>

    <!-- ======================================================================
         TAB 1: KELOLA MEDIA (FOTO & VIDEO)
         ====================================================================== -->
    <section id="tab-media" class="tab-panel <?= $activeTab === 'media' ? 'active' : '' ?>">
      <div class="admin-card-box">
        <div class="box-header">
          <h2><ion-icon name="images-outline"></ion-icon> Koleksi Media HS15</h2>
          
          <form method="get" class="toolbar-wrap">
            <input type="hidden" name="tab" value="media">
            
            <div class="search-box">
              <ion-icon name="search-outline"></ion-icon>
              <input type="text" name="q" placeholder="Cari judul, file, keterangan..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>

            <!-- Filter Tipe Media -->
            <select name="type" id="filterTypeSelect" class="filter-select-hidden" onchange="this.form.submit()">
              <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Semua Tipe</option>
              <option value="photo" <?= $filterType === 'photo' ? 'selected' : '' ?>>Hanya Foto</option>
              <option value="video" <?= $filterType === 'video' ? 'selected' : '' ?>>Hanya Video</option>
            </select>
            <div class="custom-select-box" data-select="filterTypeSelect">
              <button type="button" class="custom-select-trigger" aria-haspopup="listbox" aria-expanded="false" title="Pilih tipe media">
                <span class="custom-select-label"><?= $filterType === 'photo' ? 'Hanya Foto' : ($filterType === 'video' ? 'Hanya Video' : 'Semua Tipe') ?></span>
                <ion-icon name="chevron-down-outline" class="custom-select-arrow"></ion-icon>
              </button>
              <div class="custom-select-dropdown" role="listbox">
                <div class="custom-select-option <?= $filterType === 'all' ? 'active' : '' ?>" data-value="all">
                  <span>Semua Tipe</span>
                  <ion-icon name="checkmark-outline" class="option-check"></ion-icon>
                </div>
                <div class="custom-select-option <?= $filterType === 'photo' ? 'active' : '' ?>" data-value="photo">
                  <span>Hanya Foto</span>
                  <ion-icon name="checkmark-outline" class="option-check"></ion-icon>
                </div>
                <div class="custom-select-option <?= $filterType === 'video' ? 'active' : '' ?>" data-value="video">
                  <span>Hanya Video</span>
                  <ion-icon name="checkmark-outline" class="option-check"></ion-icon>
                </div>
              </div>
            </div>

            <!-- Filter Urutan Media -->
            <select name="sort" id="filterSortSelect" class="filter-select-hidden" onchange="this.form.submit()">
              <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Terbaru</option>
              <option value="oldest" <?= $sortOption === 'oldest' ? 'selected' : '' ?>>Terlama</option>
              <option value="title" <?= $sortOption === 'title' ? 'selected' : '' ?>>Judul (A-Z)</option>
            </select>
            <div class="custom-select-box" data-select="filterSortSelect">
              <button type="button" class="custom-select-trigger" aria-haspopup="listbox" aria-expanded="false" title="Urutkan media">
                <span class="custom-select-label"><?= $sortOption === 'oldest' ? 'Terlama' : ($sortOption === 'title' ? 'Judul (A-Z)' : 'Terbaru') ?></span>
                <ion-icon name="chevron-down-outline" class="custom-select-arrow"></ion-icon>
              </button>
              <div class="custom-select-dropdown" role="listbox">
                <div class="custom-select-option <?= $sortOption === 'newest' ? 'active' : '' ?>" data-value="newest">
                  <span>Terbaru</span>
                  <ion-icon name="checkmark-outline" class="option-check"></ion-icon>
                </div>
                <div class="custom-select-option <?= $sortOption === 'oldest' ? 'active' : '' ?>" data-value="oldest">
                  <span>Terlama</span>
                  <ion-icon name="checkmark-outline" class="option-check"></ion-icon>
                </div>
                <div class="custom-select-option <?= $sortOption === 'title' ? 'active' : '' ?>" data-value="title">
                  <span>Judul (A-Z)</span>
                  <ion-icon name="checkmark-outline" class="option-check"></ion-icon>
                </div>
              </div>
            </div>

            <?php if (!empty($searchQuery) || $filterType !== 'all' || $sortOption !== 'newest'): ?>
              <a href="admin.php?tab=media" class="btn btn-secondary btn-sm" title="Reset Filter">
                <ion-icon name="close-circle-outline"></ion-icon> Reset
              </a>
            <?php endif; ?>
          </form>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 50px;">#</th>
                <th>Media Preview</th>
                <th>Tipe</th>
                <th>Tanggal & Jam</th>
                <th>Keterangan / Deskripsi</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($mediaList)): ?>
                <?php foreach ($mediaList as $idx => $m): 
                  $base = pathinfo($m['filename'], PATHINFO_FILENAME);
                  $isPhoto = $m['media_type'] === 'photo';
                  $thumbSrc = '';
                  if ($isPhoto) {
                      $thumbFile = $thumbsDir . $base . '.webp';
                      $thumbSrc = file_exists($thumbFile) ? '../gallery/thumbs/' . rawurlencode($base . '.webp') : '../gallery/' . rawurlencode($m['filename']);
                  } else {
                      $thumbFile = $thumbsDir . $base . '.jpg';
                      $thumbSrc = file_exists($thumbFile) ? '../gallery/thumbs/' . rawurlencode($base . '.jpg') : '../img/logo.jpg';
                  }

                  $mediaFullSrc = '../gallery/' . rawurlencode($m['filename']);
                  $formattedDate = !empty($m['media_date']) ? formatIndonesianDate($m['media_date']) : '-';
                  $formattedTime = !empty($m['media_time']) ? substr($m['media_time'], 0, 5) : '';
                ?>
                  <tr>
                    <td><?= $offset + $idx + 1 ?></td>
                    <td>
                      <div class="media-preview-cell">
                        <a href="<?= $mediaFullSrc ?>" target="_blank" title="Lihat Media Lengkap">
                          <img src="<?= $thumbSrc ?>" alt="Preview" class="thumb-preview" loading="lazy">
                        </a>
                        <div class="media-meta-wrap">
                          <span class="media-title-text" title="<?= htmlspecialchars($m['title'] ?? '') ?>">
                            <?= htmlspecialchars($m['title'] ?: $m['filename']) ?>
                          </span>
                          <span class="media-filename-sub" title="<?= htmlspecialchars($m['filename']) ?>">
                            <?= htmlspecialchars($m['filename']) ?>
                          </span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <?php if ($isPhoto): ?>
                        <span class="badge-pill badge-photo"><ion-icon name="image-outline"></ion-icon> Foto</span>
                      <?php else: ?>
                        <span class="badge-pill badge-video"><ion-icon name="videocam-outline"></ion-icon> Video</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div style="font-weight: 500;"><?= htmlspecialchars($formattedDate) ?></div>
                      <?php if ($formattedTime): ?>
                        <div style="font-size: 0.76rem; color: var(--admin-text-subtle); display: inline-flex; align-items: center; justify-content: center; gap: 4px;"><ion-icon name="time-outline"></ion-icon> <?= htmlspecialchars($formattedTime) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div style="max-width: 240px; margin: 0 auto; font-size: 0.82rem; color: var(--admin-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= !empty($m['description']) ? htmlspecialchars($m['description']) : '<span style="color:rgba(255,255,255,0.25); font-style:italic;">Tidak ada deskripsi</span>' ?>
                      </div>
                    </td>
                    <td>
                      <div class="actions-cell">
                        <!-- Tombol Edit Metadata -->
                        <button type="button" class="btn btn-secondary btn-sm btn-edit-media" 
                          data-id="<?= $m['id'] ?>"
                          data-title="<?= htmlspecialchars($m['title'] ?? '') ?>"
                          data-date="<?= htmlspecialchars($m['media_date'] ?? '') ?>"
                          data-time="<?= htmlspecialchars($m['media_time'] ?? '') ?>"
                          data-desc="<?= htmlspecialchars($m['description'] ?? '') ?>"
                          title="Edit Judul, Tanggal, Jam & Deskripsi">
                          <ion-icon name="create-outline"></ion-icon> Edit
                        </button>

                        <!-- Tombol Ganti File -->
                        <button type="button" class="btn btn-secondary btn-sm btn-replace-media"
                          data-id="<?= $m['id'] ?>"
                          data-title="<?= htmlspecialchars($m['title'] ?? '') ?>"
                          data-filename="<?= htmlspecialchars($m['filename']) ?>"
                          title="Ganti File Media">
                          <ion-icon name="swap-horizontal-outline"></ion-icon> Ganti
                        </button>

                        <!-- Form Hapus Media -->
                        <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus media ini secara permanen? File asli dan thumbnail akan dihapus.');" style="display:inline;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete_media">
                          <input type="hidden" name="media_id" value="<?= $m['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm" title="Hapus Media">
                            <ion-icon name="trash-outline"></ion-icon>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 40px; color: var(--admin-text-subtle);">
                    <ion-icon name="folder-open-outline" style="font-size: 2.5rem; display: block; margin: 0 auto 10px auto; opacity: 0.5;"></ion-icon>
                    Tidak ada media yang ditemukan sesuai filter atau pencarian Anda.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination Media -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination-wrap">
            <div class="pagination-links">
              <!-- Tombol Halaman Pertama (<<) & Sebelumnya (<) -->
              <?php if ($page > 1): ?>
                <a href="admin.php?tab=media&q=<?= urlencode($searchQuery) ?>&type=<?= urlencode($filterType) ?>&sort=<?= urlencode($sortOption) ?>&page=1" class="page-num" title="Halaman Pertama">&laquo;</a>
                <a href="admin.php?tab=media&q=<?= urlencode($searchQuery) ?>&type=<?= urlencode($filterType) ?>&sort=<?= urlencode($sortOption) ?>&page=<?= $page - 1 ?>" class="page-num" title="Halaman Sebelumnya">&lsaquo;</a>
              <?php else: ?>
                <span class="page-num is-disabled" title="Halaman Pertama">&laquo;</span>
                <span class="page-num is-disabled" title="Halaman Sebelumnya">&lsaquo;</span>
              <?php endif; ?>

              <!-- Nomor Halaman -->
              <?php 
                $startP = max(1, $page - 2);
                $endP   = min($totalPages, $page + 2);
                for ($p = $startP; $p <= $endP; $p++): 
              ?>
                <a href="admin.php?tab=media&q=<?= urlencode($searchQuery) ?>&type=<?= urlencode($filterType) ?>&sort=<?= urlencode($sortOption) ?>&page=<?= $p ?>" class="page-num <?= $p === $page ? 'active' : '' ?>">
                  <?= $p ?>
                </a>
              <?php endfor; ?>

              <!-- Tombol Halaman Berikutnya (>) & Terakhir (>>) -->
              <?php if ($page < $totalPages): ?>
                <a href="admin.php?tab=media&q=<?= urlencode($searchQuery) ?>&type=<?= urlencode($filterType) ?>&sort=<?= urlencode($sortOption) ?>&page=<?= $page + 1 ?>" class="page-num" title="Halaman Selanjutnya">&rsaquo;</a>
                <a href="admin.php?tab=media&q=<?= urlencode($searchQuery) ?>&type=<?= urlencode($filterType) ?>&sort=<?= urlencode($sortOption) ?>&page=<?= $totalPages ?>" class="page-num" title="Halaman Terakhir">&raquo;</a>
              <?php else: ?>
                <span class="page-num is-disabled" title="Halaman Selanjutnya">&rsaquo;</span>
                <span class="page-num is-disabled" title="Halaman Terakhir">&raquo;</span>
              <?php endif; ?>
            </div>
            <span class="pagination-info">Menampilkan halaman <?= $page ?> dari <?= $totalPages ?> (Total <?= $totalMediaCount ?> media)</span>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- ======================================================================
         TAB 2: UPLOAD MEDIA BARU (FOTO ATAU VIDEO)
         ====================================================================== -->
    <section id="tab-upload" class="tab-panel <?= $activeTab === 'upload' ? 'active' : '' ?>">
      <div class="admin-card-box">
        <div class="box-header">
          <h2><ion-icon name="cloud-upload-outline"></ion-icon> Unggah Foto / Video Baru</h2>
        </div>

        <form method="post" enctype="multipart/form-data" id="uploadForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="upload_media">

          <div class="dropzone-container" id="dropzone">
            <ion-icon name="cloud-upload" class="dropzone-icon"></ion-icon>
            <h3 class="dropzone-title">Seret & Lepas file foto atau video di sini</h3>
            <p class="dropzone-hint">atau klik untuk memilih file dari komputer Anda (Format: JPG, PNG, WEBP, GIF, MP4, WEBM)</p>
            <input type="file" id="media_file_input" name="media_file" class="dropzone-input" accept="image/*,video/*" required>
          </div>

          <div class="upload-preview-card" id="uploadPreviewCard">
            <div id="previewMediaWrap"></div>
            <div class="upload-preview-info">
              <div class="upload-preview-name" id="previewFileName">filename.ext</div>
              <div class="upload-preview-size" id="previewFileSize">0 KB</div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="btnRemovePreview">
              <ion-icon name="close-circle-outline"></ion-icon> Batalkan
            </button>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label for="upload_title">Judul Media</label>
              <input type="text" id="upload_title" name="title" class="form-control" placeholder="Contoh: Dokumentasi Rolling Tour Day 1">
              <small style="color:var(--admin-text-subtle); font-size:0.75rem;">Kosongkan bila ingin menggunakan format nama default.</small>
            </div>

            <div class="form-group">
              <label for="upload_date">Tanggal Dokumentasi</label>
              <input type="date" id="upload_date" name="media_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
              <label for="upload_time">Waktu / Jam (Opsional)</label>
              <input type="time" id="upload_time" name="media_time" class="form-control" value="<?= date('H:i') ?>" step="1">
            </div>

            <div class="form-group form-full">
              <label for="upload_desc">Deskripsi / Keterangan (Opsional)</label>
              <textarea id="upload_desc" name="description" class="form-control" placeholder="Tuliskan keterangan detail mengenai momen, lokasi, atau anggota komunitas yang ada di dokumentasi ini..."></textarea>
            </div>
          </div>

          <div style="margin-top: 24px; text-align: right;">
            <button type="submit" class="btn btn-primary" id="btnSubmitUpload">
              <ion-icon name="arrow-up-circle-outline"></ion-icon> Unggah Media Sekarang
            </button>
          </div>
        </form>
      </div>
    </section>

    <!-- ======================================================================
         TAB 3: KELOLA PENGGUNA & PERAN (ADMIN & MEMBER)
         ====================================================================== -->
    <section id="tab-users" class="tab-panel <?= $activeTab === 'users' ? 'active' : '' ?>">
      <div class="admin-card-box">
        <div class="box-header">
          <h2><ion-icon name="people-outline"></ion-icon> Daftar Akun & Hak Akses Pengguna</h2>
          <button type="button" class="btn btn-primary btn-sm" id="btnOpenAddUser">
            <ion-icon name="person-add-outline"></ion-icon> Tambah Akun Pengguna
          </button>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 50px;">ID</th>
                <th class="text-left" style="padding-left: 20px;">Pengguna</th>
                <th>Peran / Role</th>
                <th>Bergabung Sejak</th>
                <th>Kelola Peran & Akun</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($usersList as $u): 
                $isSelf = (int) $u['id'] === $currentUserId;
                $isAdmin = ($u['role'] ?? '') === 'admin';
                $joinedDate = !empty($u['created_at']) ? date('d M Y, H:i', strtotime($u['created_at'])) : '-';
                $avatar = get_user_avatar($u['profile_photo'] ?? null, (int) $u['id']);
              ?>
                <tr>
                  <td><?= $u['id'] ?></td>
                  <td class="text-left" style="padding-left: 20px;">
                    <div class="user-cell">
                      <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" onerror="this.onerror=null; this.src='../img/logo.jpg';" class="user-cell-avatar">
                      <div class="user-cell-info">
                        <span class="user-cell-email"><?= htmlspecialchars($u['email']) ?></span>
                        <?php if ($isSelf): ?>
                          <span class="user-cell-badge">Akun Anda</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td>
                    <?php if ($isAdmin): ?>
                      <span class="badge-pill badge-admin"><ion-icon name="shield-checkmark"></ion-icon> Admin</span>
                    <?php else: ?>
                      <span class="badge-pill badge-member"><ion-icon name="person"></ion-icon> Member</span>
                    <?php endif; ?>
                  </td>
                  <td style="color:var(--admin-text-muted); font-size:0.84rem;">
                    <?= htmlspecialchars($joinedDate) ?>
                  </td>
                  <td>
                    <div class="actions-cell">
                      <!-- Ganti Role Switcher -->
                      <?php if (!$isSelf): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Ubah peran pengguna <?= htmlspecialchars($u['email']) ?> menjadi <?= $isAdmin ? 'Member' : 'Admin' ?>?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="update_role">
                          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                          <input type="hidden" name="new_role" value="<?= $isAdmin ? 'member' : 'admin' ?>">
                          <button type="submit" class="btn btn-secondary btn-sm" title="Ubah Peran">
                            <?php if ($isAdmin): ?>
                              <ion-icon name="arrow-down-circle-outline"></ion-icon> Jadikan Member
                            <?php else: ?>
                              <ion-icon name="shield-checkmark-outline"></ion-icon> Jadikan Admin
                            <?php endif; ?>
                          </button>
                        </form>
                      <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity:0.4; cursor:not-allowed;" title="Tidak dapat mendemosi akun aktif">
                          <ion-icon name="lock-closed-outline"></ion-icon> Akun Anda
                        </button>
                      <?php endif; ?>

                      <!-- Reset Password Button -->
                      <button type="button" class="btn btn-secondary btn-sm btn-reset-pwd" 
                        data-id="<?= $u['id'] ?>"
                        data-email="<?= htmlspecialchars($u['email']) ?>"
                        title="Reset Password Akun">
                        <ion-icon name="key-outline"></ion-icon> Reset Sandi
                      </button>

                      <!-- Hapus Pengguna -->
                      <?php if (!$isSelf): ?>
                        <form method="post" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun <?= htmlspecialchars($u['email']) ?> secara permanen?');" style="display:inline;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                          <button type="submit" class="btn btn-danger btn-sm" title="Hapus Akun Pengguna">
                            <ion-icon name="trash-outline"></ion-icon>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <!-- ======================================================================
       MODAL 1: EDIT METADATA MEDIA (JUDUL, TANGGAL, JAM, DESKRIPSI)
       ====================================================================== -->
  <div class="modal-backdrop" id="modalEditMedia">
    <div class="modal-box">
      <div class="modal-header">
        <h3><ion-icon name="create-outline"></ion-icon> Edit Metadata Media</h3>
        <button type="button" class="btn-close-modal" data-close="modalEditMedia">&times;</button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_media">
        <input type="hidden" name="media_id" id="edit_media_id">

        <div class="modal-body">
          <div class="form-group" style="margin-bottom:14px;">
            <label for="edit_title">Judul Media</label>
            <input type="text" id="edit_title" name="title" class="form-control" required>
          </div>

          <div class="form-grid" style="margin-top:0; margin-bottom:14px;">
            <div class="form-group">
              <label for="edit_date">Tanggal</label>
              <input type="date" id="edit_date" name="media_date" class="form-control">
            </div>
            <div class="form-group">
              <label for="edit_time">Jam / Waktu</label>
              <input type="time" id="edit_time" name="media_time" class="form-control" step="1">
            </div>
          </div>

          <div class="form-group">
            <label for="edit_desc">Deskripsi / Keterangan</label>
            <textarea id="edit_desc" name="description" class="form-control" rows="3" placeholder="Keterangan singkat momen dokumentasi..."></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close="modalEditMedia">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ======================================================================
       MODAL 2: GANTI FILE MEDIA
       ====================================================================== -->
  <div class="modal-backdrop" id="modalReplaceMedia">
    <div class="modal-box">
      <div class="modal-header">
        <h3><ion-icon name="swap-horizontal-outline"></ion-icon> Ganti File Media</h3>
        <button type="button" class="btn-close-modal" data-close="modalReplaceMedia">&times;</button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="replace_media">
        <input type="hidden" name="media_id" id="replace_media_id">

        <div class="modal-body">
          <p style="font-size:0.88rem; color:var(--admin-text-muted); margin-top:0;">
            Ganti file untuk item: <strong id="replace_media_title" style="color:#ffffff;"></strong>
          </p>
          <p style="font-size:0.78rem; color:var(--admin-text-subtle);">
            File lama di disk dan thumbnail/poster akan dihapus dan diperbarui otomatis dengan file baru.
          </p>

          <div class="form-group" style="margin-top:16px;">
            <label for="replace_file_input">Pilih File Baru</label>
            <input type="file" id="replace_file_input" name="replace_file" class="form-control" accept="image/*,video/*" required>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close="modalReplaceMedia">Batal</button>
          <button type="submit" class="btn btn-primary">Ganti File Sekarang</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ======================================================================
       MODAL 3: TAMBAH PENGGUNA BARU
       ====================================================================== -->
  <div class="modal-backdrop" id="modalAddUser">
    <div class="modal-box">
      <div class="modal-header">
        <h3><ion-icon name="person-add-outline"></ion-icon> Tambah Akun Pengguna</h3>
        <button type="button" class="btn-close-modal" data-close="modalAddUser">&times;</button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_user">

        <div class="modal-body">
          <div class="form-group" style="margin-bottom:14px;">
            <label for="add_user_email">Alamat Email</label>
            <input type="email" id="add_user_email" name="email" class="form-control" placeholder="nama@email.com" required>
          </div>

          <div class="form-group" style="margin-bottom:14px;">
            <label for="add_user_pwd">Password (Min. 6 Karakter)</label>
            <input type="password" id="add_user_pwd" name="password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
          </div>

          <div class="form-group">
            <label for="add_user_role">Peran (Role)</label>
            <select id="add_user_role" name="role" class="form-control">
              <option value="member">Member (Akses Galeri & Unduh)</option>
              <option value="admin">Admin (Akses Penuh Kelola Media & Pengguna)</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close="modalAddUser">Batal</button>
          <button type="submit" class="btn btn-primary">Buat Akun</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ======================================================================
       MODAL 4: RESET PASSWORD PENGGUNA
       ====================================================================== -->
  <div class="modal-backdrop" id="modalResetPassword">
    <div class="modal-box">
      <div class="modal-header">
        <h3><ion-icon name="key-outline"></ion-icon> Reset Password Pengguna</h3>
        <button type="button" class="btn-close-modal" data-close="modalResetPassword">&times;</button>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="reset_user_id">

        <div class="modal-body">
          <p style="font-size:0.88rem; color:var(--admin-text-muted); margin-top:0;">
            Atur ulang password untuk akun: <strong id="reset_user_email" style="color:#ffffff;"></strong>
          </p>

          <div class="form-group" style="margin-top:14px;">
            <label for="reset_new_password">Password Baru (Min. 6 Karakter)</label>
            <input type="password" id="reset_new_password" name="new_password" class="form-control" placeholder="Ketik kata sandi baru..." required minlength="6">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close="modalResetPassword">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Password Baru</button>
        </div>
      </form>
    </div>
  </div>

  <!-- JavaScript Admin Panel Interaktif -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // 1. Tab Switching
      const tabButtons = document.querySelectorAll('.tab-btn');
      const tabPanels  = document.querySelectorAll('.tab-panel');

      function switchTab(targetTab) {
        tabButtons.forEach(btn => {
          if (btn.getAttribute('data-tab') === targetTab) {
            btn.classList.add('active');
          } else {
            btn.classList.remove('active');
          }
        });
        tabPanels.forEach(panel => {
          if (panel.id === 'tab-' + targetTab) {
            panel.classList.add('active');
          } else {
            panel.classList.remove('active');
          }
        });
        // Update URL query tanpa refresh
        const url = new URL(window.location);
        url.searchParams.set('tab', targetTab);
        window.history.replaceState({}, '', url);
      }

      tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
          switchTab(btn.getAttribute('data-tab'));
        });
      });

      // 2. Drag & Drop File Upload + Preview
      const dropzone = document.getElementById('dropzone');
      const mediaInput = document.getElementById('media_file_input');
      const previewCard = document.getElementById('uploadPreviewCard');
      const previewMediaWrap = document.getElementById('previewMediaWrap');
      const previewName = document.getElementById('previewFileName');
      const previewSize = document.getElementById('previewFileSize');
      const btnRemovePreview = document.getElementById('btnRemovePreview');

      if (dropzone && mediaInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
          dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
          });
        });

        ['dragleave', 'drop'].forEach(eventName => {
          dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
          });
        });

        dropzone.addEventListener('drop', (e) => {
          if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            mediaInput.files = e.dataTransfer.files;
            handleFileSelected(mediaInput.files[0]);
          }
        });

        mediaInput.addEventListener('change', () => {
          if (mediaInput.files && mediaInput.files.length > 0) {
            handleFileSelected(mediaInput.files[0]);
          }
        });

        btnRemovePreview.addEventListener('click', () => {
          mediaInput.value = '';
          previewCard.style.display = 'none';
          previewMediaWrap.innerHTML = '';
        });
      }

      function handleFileSelected(file) {
        if (!file) return;
        previewName.textContent = file.name;
        previewSize.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
        previewCard.style.display = 'flex';

        previewMediaWrap.innerHTML = '';
        if (file.type.startsWith('image/')) {
          const img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          img.className = 'upload-preview-media';
          previewMediaWrap.appendChild(img);
        } else if (file.type.startsWith('video/')) {
          const vid = document.createElement('video');
          vid.src = URL.createObjectURL(file);
          vid.className = 'upload-preview-media';
          vid.muted = true;
          vid.playsInline = true;
          previewMediaWrap.appendChild(vid);
        }

        // Auto-fill title if empty
        const titleField = document.getElementById('upload_title');
        if (titleField && !titleField.value) {
          const baseName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
          titleField.value = baseName.replace(/[_-]/g, ' ');
        }
      }

      // 3. Modal Helpers
      function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('open');
      }

      function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('open');
      }

      document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => {
          closeModal(btn.getAttribute('data-close'));
        });
      });

      document.querySelectorAll('.modal-backdrop').forEach(modal => {
        modal.addEventListener('click', (e) => {
          if (e.target === modal) {
            modal.classList.remove('open');
          }
        });
      });

      // 4. Modal Edit Media
      document.querySelectorAll('.btn-edit-media').forEach(btn => {
        btn.addEventListener('click', () => {
          document.getElementById('edit_media_id').value = btn.dataset.id;
          document.getElementById('edit_title').value = btn.dataset.title || '';
          document.getElementById('edit_date').value = btn.dataset.date || '';
          document.getElementById('edit_time').value = btn.dataset.time || '';
          document.getElementById('edit_desc').value = btn.dataset.desc || '';
          openModal('modalEditMedia');
        });
      });

      // 5. Modal Ganti File Media
      document.querySelectorAll('.btn-replace-media').forEach(btn => {
        btn.addEventListener('click', () => {
          document.getElementById('replace_media_id').value = btn.dataset.id;
          document.getElementById('replace_media_title').textContent = btn.dataset.title || btn.dataset.filename;
          openModal('modalReplaceMedia');
        });
      });

      // 6. Modal Tambah Pengguna
      const btnOpenAddUser = document.getElementById('btnOpenAddUser');
      if (btnOpenAddUser) {
        btnOpenAddUser.addEventListener('click', () => {
          openModal('modalAddUser');
        });
      }

      // 7. Modal Reset Password Pengguna
      document.querySelectorAll('.btn-reset-pwd').forEach(btn => {
        btn.addEventListener('click', () => {
          document.getElementById('reset_user_id').value = btn.dataset.id;
          document.getElementById('reset_user_email').textContent = btn.dataset.email;
          document.getElementById('reset_new_password').value = '';
          openModal('modalResetPassword');
        });
      });

      // 8. Custom Animated Select Dropdowns (Efek Membuka & Menutup Halus)
      document.querySelectorAll('.custom-select-box').forEach(box => {
        const trigger = box.querySelector('.custom-select-trigger');
        const targetId = box.dataset.select;
        const nativeSelect = targetId ? document.getElementById(targetId) : null;
        const options = box.querySelectorAll('.custom-select-option');

        trigger.addEventListener('click', (e) => {
          e.stopPropagation();
          const isOpen = box.classList.contains('open');

          // Tutup dropdown lain yang sedang terbuka
          document.querySelectorAll('.custom-select-box.open').forEach(b => {
            if (b !== box) {
              b.classList.remove('open');
              b.querySelector('.custom-select-trigger')?.setAttribute('aria-expanded', 'false');
            }
          });

          if (isOpen) {
            box.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
          } else {
            box.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
          }
        });

        options.forEach(opt => {
          opt.addEventListener('click', (e) => {
            e.stopPropagation();
            const val = opt.dataset.value;
            box.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');

            if (nativeSelect && nativeSelect.value !== val) {
              nativeSelect.value = val;
              if (nativeSelect.form) {
                nativeSelect.form.submit();
              }
            }
          });
        });
      });

      // Tutup dropdown saat klik di luar
      document.addEventListener('click', () => {
        document.querySelectorAll('.custom-select-box.open').forEach(b => {
          b.classList.remove('open');
          b.querySelector('.custom-select-trigger')?.setAttribute('aria-expanded', 'false');
        });
      });

      // Tutup dropdown saat tekan tombol Escape
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          document.querySelectorAll('.custom-select-box.open').forEach(b => {
            b.classList.remove('open');
            b.querySelector('.custom-select-trigger')?.setAttribute('aria-expanded', 'false');
          });
        }
      });
    });
  </script>
  <script src="../js/nav.js?v=<?= file_exists(__DIR__ . '/../js/nav.js') ? filemtime(__DIR__ . '/../js/nav.js') : time() ?>"></script>
</body>
</html>

