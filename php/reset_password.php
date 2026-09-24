<?php
// reset_password.php - Form reset password baru berbasis token aman
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/security_helper.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error_message = '';
$is_token_valid = false;
$user_email = '';

if (empty($token)) {
    $error_message = "Tautan reset password tidak valid atau tidak memiliki token.";
} else {
    $resetData = verify_password_reset_token($conn, $token);
    if (!$resetData) {
        $error_message = "Tautan reset password tidak valid atau sudah kedaluwarsa. Silakan ajukan permohonan baru.";
    } else {
        $is_token_valid = true;
        $user_email = $resetData['email'];
    }
}

// Proses submit form password baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_token_valid) {
    if (!validate_csrf()) {
        $error_message = "Sesi keamanan telah berakhir. Silakan muat ulang halaman dan coba lagi.";
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $error_message = "Semua kolom password wajib diisi.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password baru minimal terdiri dari 6 karakter.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Konfirmasi password baru tidak cocok.";
        } else {
            // Update password user di database
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hash, $user_email);

            if ($stmt->execute()) {
                // Hapus token yang sudah dipakai
                consume_password_reset_token($conn, $token);

                // Arahkan ke halaman login dengan notifikasi sukses
                header("Location: index.php?reset=1");
                exit;
            } else {
                $error_message = "Terjadi kesalahan pada database saat memperbarui password. Silakan coba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buat Password Baru - HS15</title>
  <link rel="stylesheet" href="../css/background.css">
  <link rel="stylesheet" href="../css/auth.css">
  <link rel="icon" href="../img/logo.jpg" type="image/jpeg">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>
  <!-- Background Slideshow & Overlay -->
  <div id="bkgd1"></div>
  <div id="bkgd2"></div>
  <div class="bkgd-overlay"></div>

  <section class="auth-container">
    <div class="auth-card">
      <div class="auth-header">
        <img src="../img/logo.jpg" alt="HS15 Logo" class="auth-logo">
        <h2>Password Baru</h2>
        <p class="auth-subtitle">
          <?php if ($is_token_valid): ?>
            Atur password baru untuk akun <strong><?= htmlspecialchars($user_email) ?></strong>
          <?php else: ?>
            Pemulihan Password HS15
          <?php endif; ?>
        </p>
      </div>

      <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
          <ion-icon name="alert-circle-outline"></ion-icon>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($is_token_valid): ?>
        <form method="post" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

          <div class="input-group">
            <label for="new_password">Password Baru</label>
            <div class="input-wrapper">
              <ion-icon name="lock-closed-outline" class="input-icon-left"></ion-icon>
              <input type="password" id="new_password" name="new_password" autocomplete="new-password" placeholder="Minimal 6 karakter" required autofocus>
              <button type="button" class="toggle-password" title="Lihat password" aria-label="Toggle password visibility">
                <ion-icon name="eye-outline"></ion-icon>
              </button>
            </div>
          </div>

          <div class="input-group">
            <label for="confirm_password">Konfirmasi Password Baru</label>
            <div class="input-wrapper">
              <ion-icon name="shield-checkmark-outline" class="input-icon-left"></ion-icon>
              <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Ulangi password baru" required>
              <button type="button" class="toggle-password" title="Lihat password" aria-label="Toggle confirm password visibility">
                <ion-icon name="eye-outline"></ion-icon>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-submit">
            <span>Simpan Password Baru</span>
            <ion-icon name="checkmark-outline"></ion-icon>
          </button>

          <div class="auth-footer">
            <p><a href="index.php">Batal & Kembali ke Login</a></p>
          </div>
        </form>
      <?php else: ?>
        <div style="margin-top: 20px; text-align: center;">
          <a href="fgpass.php" class="btn-link-action" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
            <ion-icon name="refresh-outline"></ion-icon>
            <span>Minta Tautan Reset Baru</span>
          </a>
          <div class="auth-footer" style="margin-top: 20px;">
            <p><a href="index.php">Kembali ke Halaman Login</a></p>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <script src="../js/auth.js"></script>
  <script src="../js/choose.js"></script>
</body>
</html>

