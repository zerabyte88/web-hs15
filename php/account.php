<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/security_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$errorMessage = '';
$successMessage = '';

$columns = [
    'profile_photo' => "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL",
    'role' => "ALTER TABLE users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'member'",
    'created_at' => "ALTER TABLE users ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
];

foreach ($columns as $column => $statement) {
    $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE '$column'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        $conn->query($statement);
    }
}

$userStmt = $conn->prepare('SELECT id, email, password, profile_photo, role, created_at FROM users WHERE id = ?');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $errorMessage = 'Sesi keamanan telah berakhir. Silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $croppedData = $_POST['cropped_image'] ?? '';
        if (!empty($croppedData) && preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#i', $croppedData, $matches)) {
            $imageExt = strtolower($matches[1]);
            if ($imageExt === 'jpeg') $imageExt = 'jpg';
            $binaryData = base64_decode($matches[2]);

            if ($binaryData === false || strlen($binaryData) > 5 * 1024 * 1024) {
                $errorMessage = 'Ukuran foto maksimal 5 MB atau format tidak valid.';
            } else {
                $profileDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'profiles';
                if (!is_dir($profileDir)) {
                    mkdir($profileDir, 0755, true);
                }

                $fileName = 'user_' . $userId . '_' . time() . '.' . $imageExt;
                $targetPath = $profileDir . DIRECTORY_SEPARATOR . $fileName;
                $relativePath = 'img/profiles/' . $fileName;

                if (file_put_contents($targetPath, $binaryData)) {
                    $updateStmt = $conn->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
                    $updateStmt->bind_param('si', $relativePath, $userId);
                    $updateStmt->execute();

                    if (!empty($user['profile_photo']) && str_starts_with($user['profile_photo'], 'img/profiles/')) {
                        $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $user['profile_photo']);
                        if (is_file($oldPath)) {
                            unlink($oldPath);
                        }
                    }

                    $successMessage = 'Foto profil berhasil diperbarui.';
                    $user['profile_photo'] = $relativePath;
                } else {
                    $errorMessage = 'Foto profil gagal disimpan.';
                }
            }
        } elseif (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = 'Pilih foto profil terlebih dahulu.';
        } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
            $errorMessage = 'Ukuran foto maksimal 5 MB.';
        } else {
            $imageInfo = @getimagesize($_FILES['profile_photo']['tmp_name']);
            $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

            if (!$imageInfo || !isset($allowedTypes[$imageInfo[2]])) {
                $errorMessage = 'Format foto harus JPG, PNG, atau WEBP.';
            } else {
                $profileDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'profiles';
                if (!is_dir($profileDir)) {
                    mkdir($profileDir, 0755, true);
                }

                $fileName = 'user_' . $userId . '_' . time() . '.' . $allowedTypes[$imageInfo[2]];
                $targetPath = $profileDir . DIRECTORY_SEPARATOR . $fileName;
                $relativePath = 'img/profiles/' . $fileName;

                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                    $updateStmt = $conn->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
                    $updateStmt->bind_param('si', $relativePath, $userId);
                    $updateStmt->execute();

                    if (!empty($user['profile_photo']) && str_starts_with($user['profile_photo'], 'img/profiles/')) {
                        $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $user['profile_photo']);
                        if (is_file($oldPath)) {
                            unlink($oldPath);
                        }
                    }

                    $successMessage = 'Foto profil berhasil diperbarui.';
                    $user['profile_photo'] = $relativePath;
                } else {
                    $errorMessage = 'Foto profil gagal disimpan.';
                }
            }
        }
    } elseif ($action === 'email') {
        $newEmail = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Masukkan email yang valid.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errorMessage = 'Password saat ini tidak sesuai.';
        } else {
            $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $checkStmt->bind_param('si', $newEmail, $userId);
            $checkStmt->execute();

            if ($checkStmt->get_result()->num_rows > 0) {
                $errorMessage = 'Email tersebut sudah digunakan akun lain.';
            } else {
                $updateStmt = $conn->prepare('UPDATE users SET email = ? WHERE id = ?');
                $updateStmt->bind_param('si', $newEmail, $userId);
                $updateStmt->execute();
                $_SESSION['email'] = $newEmail;
                $user['email'] = $newEmail;
                $successMessage = 'Email berhasil diperbarui.';
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            $errorMessage = 'Password saat ini tidak sesuai.';
        } elseif (strlen($newPassword) < 6) {
            $errorMessage = 'Password baru minimal 6 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'Konfirmasi password tidak cocok.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $updateStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $updateStmt->bind_param('si', $hash, $userId);
            $updateStmt->execute();
            $user['password'] = $hash;
            $successMessage = 'Password berhasil diperbarui.';
        }
        } elseif ($action === 'delete_account') {
          $currentPassword = $_POST['current_password'] ?? '';
          $confirmation = trim($_POST['confirmation'] ?? '');

          if (!password_verify($currentPassword, $user['password'])) {
            $errorMessage = 'Password saat ini tidak sesuai.';
          } elseif ($confirmation !== 'HAPUS AKUN') {
            $errorMessage = 'Ketik HAPUS AKUN untuk mengonfirmasi penghapusan.';
          } else {
            if (!empty($user['profile_photo']) && str_starts_with($user['profile_photo'], 'img/profiles/')) {
              $profilePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $user['profile_photo']);
              if (is_file($profilePath)) {
                unlink($profilePath);
              }
            }

            $deleteStmt = $conn->prepare('DELETE FROM users WHERE id = ?');
            $deleteStmt->bind_param('i', $userId);

            if ($deleteStmt->execute()) {
              $_SESSION = [];
              if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
              }
              session_destroy();
              header('Location: index.php?deleted=1');
              exit;
            }

            $errorMessage = 'Akun gagal dihapus. Silakan coba lagi.';
          }
    }
}
}

$profileImage = !empty($user['profile_photo']) && is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $user['profile_photo']))
    ? '../' . $user['profile_photo']
    : '../img/logo.png';
$role = strtolower($user['role'] ?: 'member');
$roleLabel = ucfirst($role);
$createdDate = date('d F Y', strtotime($user['created_at']));

$currentUserEmail = $user['email'] ?? ($_SESSION['email'] ?? 'User');
$currentUserRole  = $user['role'] ?? ($_SESSION['role'] ?? 'member');
$isSuperAdmin     = ($currentUserRole === 'admin');
$roleBadgeText    = $isSuperAdmin ? 'Administrator' : 'Member';

$globalVer = file_exists(__DIR__ . '/../css/global.css') ? filemtime(__DIR__ . '/../css/global.css') : time();
$accountVer = file_exists(__DIR__ . '/../css/account.css') ? filemtime(__DIR__ . '/../css/account.css') : time();
$bkgdVer = file_exists(__DIR__ . '/../css/background.css') ? filemtime(__DIR__ . '/../css/background.css') : time();
$jsBkgdVer = file_exists(__DIR__ . '/../js/choose.js') ? filemtime(__DIR__ . '/../js/choose.js') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setelan Akun - HS15</title>
  <link rel="stylesheet" href="../css/background.css?v=<?= $bkgdVer ?>">
  <link rel="stylesheet" href="../css/global.css?v=<?= $globalVer ?>">
  <link rel="stylesheet" href="../css/account.css?v=<?= $accountVer ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="../img/favicon-32x32.png?v=2">
  <link rel="icon" type="image/png" sizes="16x16" href="../img/favicon-16x16.png?v=2">
  <link rel="icon" type="image/png" href="../img/logo.png?v=2">
  <link rel="apple-touch-icon" href="../img/apple-touch-icon.png?v=2">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body class="account-body">

  <!-- Latar Belakang Slideshow & Overlay -->
  <div id="bkgd1"></div>
  <div id="bkgd2"></div>
  <div class="bkgd-overlay"></div>

  <!-- Header Navigasi -->
  <header>
    <nav>
      <a href="choose.php" class="logo" id="logo-link">
        <img src="../img/logo.png" alt="HS15 Logo" class="logo-img">
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
        <a href="account.php" class="admin-nav-link nav-desktop-only active" title="Pengaturan Akun">
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
        <img src="<?= htmlspecialchars(get_current_user_avatar($conn)) ?>" alt="Foto profil" class="admin-header-avatar <?= $isSuperAdmin ? 'avatar-role-admin' : 'avatar-role-member' ?>" onerror="this.onerror=null; this.src='../img/logo.png';">
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
        <button type="button" class="mobile-menu-btn active" aria-label="Menu akun dan opsi" aria-expanded="false">
          <ion-icon name="ellipsis-vertical"></ion-icon>
        </button>
        <div class="mobile-dropdown">
          <div class="mobile-dropdown-user">
            <img src="<?= htmlspecialchars(get_current_user_avatar($conn)) ?>" alt="Foto profil" class="mobile-dropdown-avatar <?= $isSuperAdmin ? 'avatar-role-admin' : 'avatar-role-member' ?>" onerror="this.onerror=null; this.src='../img/logo.png';">
            <div class="mobile-dropdown-info">
              <span class="mobile-dropdown-email"><?= htmlspecialchars($currentUserEmail) ?></span>
              <span class="mobile-dropdown-role <?= $isSuperAdmin ? 'badge-role-admin' : 'badge-role-member' ?>">
                <ion-icon name="<?= $isSuperAdmin ? 'shield-checkmark' : 'person' ?>"></ion-icon>
                <?= $roleBadgeText ?>
              </span>
            </div>
          </div>
          <div class="mobile-dropdown-divider"></div>
          <a href="account.php" class="mobile-dropdown-item active">
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

  <main class="account-page">
    <section class="account-hero">
      <p class="eyebrow">Pusat akun</p>
      <h1>Setelan Akun</h1>
      <p>Kelola informasi pribadi dan keamanan akun HS15 Anda.</p>
    </section>

    <?php if ($errorMessage): ?>
      <div class="account-alert account-alert-error"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
      <div class="account-alert account-alert-success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>

    <section class="account-grid">
      <article class="account-card account-summary">
        <div class="profile-preview <?= $isSuperAdmin ? 'preview-role-admin' : 'preview-role-member' ?>">
          <img src="<?= htmlspecialchars($profileImage) ?>" alt="Foto profil akun" class="<?= $isSuperAdmin ? 'avatar-role-admin' : 'avatar-role-member' ?>">
          <span class="status-dot"></span>
        </div>
        <h2><?= htmlspecialchars($user['email']) ?></h2>
        <span class="role-badge <?= $user['role'] === 'admin' ? 'role-admin' : 'role-member' ?>">
          <ion-icon name="<?= $user['role'] === 'admin' ? 'shield-checkmark' : 'person' ?>"></ion-icon>
          <?= htmlspecialchars($roleLabel) ?>
        </span>
        <p class="member-since">Bergabung sejak <?= htmlspecialchars($createdDate) ?></p>

        <?php if ($user['role'] === 'admin'): ?>
          <a href="admin.php" class="account-button admin-shortcut-btn" title="Buka Panel Administrasi HS15">
            <ion-icon name="shield-checkmark-outline"></ion-icon> Buka Panel Admin
          </a>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="profile-upload profile-photo-form" id="profileUploadForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="profile">
          <input type="hidden" name="cropped_image" id="croppedImageData" value="">
          
          <label for="profile_photo" class="file-label">Pilih foto baru</label>
          <div class="file-input-wrapper">
            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp">
          </div>

          <div class="crop-preview-controls" id="cropPreviewControls" style="display: none;">
            <span class="crop-status-badge"><ion-icon name="checkmark-circle"></ion-icon> Foto disesuaikan</span>
            <button type="button" class="btn-adjust-again" id="btnAdjustAgain">
              <ion-icon name="crop-outline"></ion-icon> Atur Ulang Ukuran
            </button>
          </div>

          <button type="submit" class="account-button" id="btnSubmitProfile">Simpan Foto</button>
        </form>

        <div class="danger-zone">
          <div class="danger-heading">
            <div>
              <h2>Hapus Akun Permanen</h2>
              <p>Data akun dan foto profil akan dihapus.</p>
            </div>
          </div>
          <form method="post" class="profile-upload delete-account-form" onsubmit="return confirm('Akun akan dihapus permanen. Lanjutkan?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_account">
            <label for="delete_current_password">Password saat ini</label>
            <input type="password" id="delete_current_password" name="current_password" autocomplete="current-password" required>
            <label for="delete_confirmation">Ketik "<strong>HAPUS AKUN</strong>"</label>
            <input type="text" id="delete_confirmation" name="confirmation" placeholder="HAPUS AKUN" autocomplete="off" required>
            <button type="submit" class="account-button danger-button">Hapus Akun Permanen</button>
          </form>
        </div>
      </article>

      <article class="account-card account-settings">
        <div class="setting-section">
          <div class="card-heading">
            <div><h2>Email Akun</h2><p>Perbarui alamat email untuk login.</p></div>
          </div>
          <form method="post" class="account-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="email">
            <label for="email">Email baru</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            <label for="email_current_password">Password saat ini</label>
            <input type="password" id="email_current_password" name="current_password" autocomplete="current-password" required>
            <button type="submit" class="account-button">Simpan Email</button>
          </form>
        </div>

        <div class="setting-section">
          <div class="card-heading">
            <div><h2>Password</h2><p>Gunakan password yang kuat dan unik.</p></div>
          </div>
          <form method="post" class="account-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <label for="password_current">Password saat ini</label>
            <input type="password" id="password_current" name="current_password" autocomplete="current-password" required>
            <label for="new_password">Password baru</label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
            <label for="confirm_password">Konfirmasi password baru</label>
            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
            <button type="submit" class="account-button">Perbarui Password</button>
          </form>
        </div>
      </article>
    </section>

  </main>

  <!-- Modal Sesuaikan Ukuran Foto Profil -->
  <div class="crop-modal-overlay" id="cropModalOverlay" aria-hidden="true" style="display: none;">
    <div class="crop-modal-dialog crop-modal-horizontal">
      <div class="crop-modal-header">
        <div class="crop-modal-title">
          <ion-icon name="scan-outline"></ion-icon>
          <h3>Sesuaikan Ukuran Foto</h3>
        </div>
        <button type="button" class="crop-modal-close" id="cropModalCloseBtn" aria-label="Tutup">&times;</button>
      </div>

      <div class="crop-modal-body-centered">
        <div class="crop-stage-container">
          <div class="crop-stage" id="cropStage">
            <canvas id="cropDisplayCanvas" width="500" height="500"></canvas>
            <div class="crop-box" id="cropBox">
              <div class="crop-box-grid"></div>
              <div class="crop-handle crop-handle-nw" data-handle="nw"></div>
              <div class="crop-handle crop-handle-ne" data-handle="ne"></div>
              <div class="crop-handle crop-handle-sw" data-handle="sw"></div>
              <div class="crop-handle crop-handle-se" data-handle="se"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="crop-modal-footer">
        <button type="button" class="crop-btn-cancel" id="cropModalCancelBtn">Batal</button>
        <button type="button" class="crop-btn-apply" id="cropModalApplyBtn">
          <ion-icon name="checkmark-sharp" class="crop-check-icon"></ion-icon>
          <span>Simpan</span>
        </button>
      </div>
    </div>
  </div>

  <footer class="main-footer">
    <p>&copy; 2026 HS15 - Komunitas Keliling Banjar. All rights reserved.</p>
  </footer>
  <script src="../js/choose.js?v=<?= $jsBkgdVer ?>"></script>
  <script src="../js/nav.js?v=20260924_v2"></script>
  <script src="../js/account.js?v=<?= file_exists(__DIR__ . '/../js/account.js') ? filemtime(__DIR__ . '/../js/account.js') : time() ?>"></script>
</body>
</html>
