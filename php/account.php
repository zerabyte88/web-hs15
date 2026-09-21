<?php
session_start();
require __DIR__ . '/connect.php';

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

$profileImage = !empty($user['profile_photo']) && is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $user['profile_photo']))
    ? '../' . $user['profile_photo']
    : '../img/logo.jpg';
$role = strtolower($user['role'] ?: 'member');
$roleLabel = ucfirst($role);
$createdDate = date('d F Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setelan Akun - HS15</title>
  <link rel="stylesheet" href="../css/global.css?v=20260918">
  <link rel="stylesheet" href="../css/account.css?v=20260918">
  <link rel="icon" href="../img/logo.jpg" type="image/jpeg">
</head>
<body>
  <header>
    <nav>
      <a href="../html/choose.html" class="logo" id="logo-link">
        <img src="../img/logo.jpg" alt="HS15 Logo" class="logo-img">
        <span>HS15 - Komunitas Keliling Banjar</span>
      </a>
      <ul id="menu" class="nav-main">
        <li><a href="../html/choose.html">Beranda</a></li>
        <li><a href="../php/gallery.php">Foto</a></li>
        <li><a href="../php/vidgallery.php">Video</a></li>
      </ul>
      <div class="profile-menu">
        <button type="button" class="profile-button" aria-expanded="false" aria-controls="profile-dropdown" title="Menu akun">
          <img src="<?= htmlspecialchars($profileImage) ?>" alt="Foto profil" class="profile-avatar">
        </button>
        <div id="profile-dropdown" class="profile-dropdown">
          <a href="../php/account.php">Setelan Akun</a>
          <a href="../php/logout.php" class="dropdown-logout">Log Out</a>
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

        <form method="post" enctype="multipart/form-data" class="profile-upload">
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
  <script src="../js/nav.js?v=20260918"></script>
</body>
</html>
