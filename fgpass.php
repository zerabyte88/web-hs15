<?php
session_start();
require_once 'connect.php';

$step = 1; // Default step 1
$email = '';
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // STEP 1: Verifikasi Email
    if (isset($_POST['step']) && $_POST['step'] == 1) {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error_message = "Email tidak boleh kosong.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['reset_email'] = $email;
                $step = 2; 
            } else {
                $error_message = "Email belum terdaftar dalam sistem.";
            }
        }
    }
    // STEP 2: Update Password Baru
    elseif (isset($_POST['step']) && $_POST['step'] == 2) {
        if (!isset($_SESSION['reset_email'])) {
            $error_message = "Sesi reset telah kedaluwarsa. Silakan ulangi proses.";
            $step = 1;
        } else {
            $email = $_SESSION['reset_email'];
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($new_password) || empty($confirm_password)) {
                $error_message = "Password tidak boleh kosong.";
                $step = 2;
            } elseif (strlen($new_password) < 6) {
                $error_message = "Password baru minimal terdiri dari 6 karakter.";
                $step = 2;
            } elseif ($new_password !== $confirm_password) {
                $error_message = "Konfirmasi password tidak cocok.";
                $step = 2;
            } else {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $hash, $email);
                
                if ($update_stmt->execute()) {
                    $success_message = "Password berhasil diperbarui! Silakan login dengan password baru Anda.";
                    $step = 3; 
                    unset($_SESSION['reset_email']);
                } else {
                    $error_message = "Terjadi kesalahan pada database. Silakan coba lagi.";
                    $step = 2;
                }
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
  <title>Reset Password - HS15</title>
  <link rel="stylesheet" href="background.css">
  <link rel="stylesheet" href="auth.css">
  <link rel="icon" href="img/logo.jpg" type="image/jpeg">
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
        <img src="img/logo.jpg" alt="HS15 Logo" class="auth-logo">
        <?php if ($step == 3): ?>
          <h2>Selesai!</h2>
          <p class="auth-subtitle">Password akun Anda telah berhasil diganti</p>
        <?php elseif ($step == 2): ?>
          <h2>Password Baru</h2>
          <p class="auth-subtitle">Masukkan password baru untuk <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong></p>
        <?php else: ?>
          <h2>Reset Password</h2>
          <p class="auth-subtitle">Masukkan email terdaftar untuk memperbarui password Anda</p>
        <?php endif; ?>
      </div>

      <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
          <ion-icon name="alert-circle-outline"></ion-icon>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($step == 3): ?>
        <div class="alert alert-success">
          <ion-icon name="checkmark-circle-outline"></ion-icon>
          <span><?= htmlspecialchars($success_message) ?></span>
        </div>
        <div style="margin-top: 24px;">
          <a href="index.php" class="btn-link-action">
            <ion-icon name="log-in-outline" style="margin-right: 8px; font-size: 1.2rem;"></ion-icon>
            Masuk ke Akun Anda
          </a>
        </div>

      <?php elseif ($step == 2): ?>
        <form method="post">
          <input type="hidden" name="step" value="2">

          <div class="input-group">
            <label for="new_password">Password Baru</label>
            <div class="input-wrapper">
              <ion-icon name="lock-closed-outline" class="input-icon-left"></ion-icon>
              <input type="password" id="new_password" name="new_password" placeholder="Minimal 6 karakter" required autofocus>
              <button type="button" class="toggle-password" title="Lihat password" aria-label="Toggle new password visibility">
                <ion-icon name="eye-outline"></ion-icon>
              </button>
            </div>
          </div>

          <div class="input-group">
            <label for="confirm_password">Konfirmasi Password Baru</label>
            <div class="input-wrapper">
              <ion-icon name="shield-checkmark-outline" class="input-icon-left"></ion-icon>
              <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password baru" required>
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
            <p><a href="fgpass.php">Batal & Ulangi dari Awal</a></p>
          </div>
        </form>

      <?php else: ?>
        <form method="post">
          <input type="hidden" name="step" value="1">

          <div class="input-group">
            <label for="email">Email Terdaftar</label>
            <div class="input-wrapper">
              <ion-icon name="mail-outline" class="input-icon-left"></ion-icon>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="nama@email.com" required autofocus>
            </div>
          </div>

          <button type="submit" class="btn-submit">
            <span>Lanjutkan</span>
            <ion-icon name="arrow-forward-outline"></ion-icon>
          </button>

          <div class="auth-footer">
            <p>Ingat password Anda? <a href="index.php">Kembali ke Login</a></p>
          </div>
        </form>
      <?php endif; ?>

    </div>
  </section>

  <script src="auth.js"></script>
  <script src="choose.js"></script>
</body>
</html>