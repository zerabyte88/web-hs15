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
        if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
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
    : '../img/logo.jpg';
$role = strtolower($user['role'] ?: 'member');
$roleLabel = ucfirst($role);
$createdDate = date('d F Y', strtotime($user['created_at']));

$currentUserEmail = $user['email'] ?? ($_SESSION['email'] ?? 'User');
$currentUserRole  = $user['role'] ?? ($_SESSION['role'] ?? 'member');
$isSuperAdmin     = ($currentUserRole === 'admin');
$roleBadgeText    = $isSuperAdmin ? 'Super Admin' : 'Member';

$globalVer = file_exists(__DIR__ . '/../css/global.css') ? filemtime(__DIR__ . '/../css/global.css') : time();
$accountVer = file_exists(__DIR__ . '/../css/account.css') ? filemtime(__DIR__ . '/../css/account.css') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setelan Akun - HS15</title>
  <link rel="stylesheet" href="../css/global.css?v=<?= $globalVer ?>">
  <link rel="stylesheet" href="../css/account.css?v=<?= $accountVer ?>">
  <link rel="icon" href="../img/logo.jpg" type="image/jpeg">
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
        <a href="vidgallery.php" class="admin-nav-link" title="Buka Galeri Video">
          <ion-icon name="videocam-outline"></ion-icon> <span>Video</span>
        </a>
        <a href="account.php" class="admin-nav-link nav-desktop-only active" title="Pengaturan Akun">
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
        <button type="button" class="mobile-menu-btn active" aria-label="Menu akun dan opsi" aria-expanded="false">
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
          <a href="account.php" class="mobile-dropdown-item active">
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
        <div class="profile-preview">
          <img src="<?= htmlspecialchars($profileImage) ?>" alt="Foto profil akun">
          <span class="status-dot"></span>
        </div>
        <h2><?= htmlspecialchars($user['email']) ?></h2>
        <span class="role-badge"><?= htmlspecialchars($roleLabel) ?></span>
        <p class="member-since">Bergabung sejak <?= htmlspecialchars($createdDate) ?></p>

        <?php if ($user['role'] === 'admin'): ?>
          <a href="admin.php" class="account-button" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; margin-bottom:14px; background:#e50914; color:#fff;">
            <ion-icon name="shield-checkmark-outline"></ion-icon> Buka Panel Admin
          </a>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="profile-upload">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="profile">
          <label for="profile_photo" class="file-label">Pilih foto baru</label>
          <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" required>
          <button type="submit" class="account-button">Simpan Foto</button>
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
            <label for="delete_confirmation">Ketik HAPUS AKUN</label>
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

  <footer class="main-footer">
    <p>&copy; 2026 HS15 - Komunitas Keliling Banjar. All rights reserved.</p>
  </footer>
  <script src="../js/nav.js?v=20260924_v2"></script>
</body>
</html>
