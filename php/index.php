<?php
session_start();

// Jika sudah login, bisa langsung redirect ke choose.html
if (isset($_SESSION['user_id'])) {
    header("Location: ../html/choose.html");
    exit;
}

$error_message = '';
$success_message = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_password':
            $error_message = 'Password yang Anda masukkan salah.';
            break;
        case 'user_not_found':
            $error_message = 'Email tidak terdaftar dalam sistem.';
            break;
        case 'empty_fields':
            $error_message = 'Silakan isi email dan password Anda.';
            break;
        default:
            $error_message = 'Terjadi kesalahan. Silakan coba lagi.';
    }
}

if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success_message = 'Pendaftaran berhasil! Silakan login dengan akun Anda.';
}

if (isset($_GET['reset']) && $_GET['reset'] == 1) {
    $success_message = 'Password berhasil diperbarui! Silakan login dengan password baru.';
}

if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
  $success_message = 'Akun berhasil dihapus secara permanen.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login - HS15</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <h2>Selamat Datang</h2>
        <p class="auth-subtitle">Masukkan email dan password untuk melanjutkan ke HS15</p>
      </div>

      <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
          <ion-icon name="alert-circle-outline"></ion-icon>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
          <ion-icon name="checkmark-circle-outline"></ion-icon>
          <span><?= htmlspecialchars($success_message) ?></span>
        </div>
      <?php endif; ?>

      <form action="../php/login.php" method="post" autocomplete="off">
        <div class="input-group">
          <label for="email">Email</label>
          <div class="input-wrapper">
            <ion-icon name="mail-outline" class="input-icon-left"></ion-icon>
            <input type="email" id="email" name="email" autocomplete="off" placeholder="nama@email.com" required autofocus>
          </div>
        </div>

        <div class="input-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <ion-icon name="lock-closed-outline" class="input-icon-left"></ion-icon>
            <input type="password" id="password" name="password" autocomplete="new-password" placeholder="••••••••" required>
            <button type="button" class="toggle-password" title="Lihat password" aria-label="Toggle password visibility">
              <ion-icon name="eye-outline"></ion-icon>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <span>Masuk</span>
          <ion-icon name="arrow-forward-outline"></ion-icon>
        </button>

        <div class="auth-footer">
          <p>Belum punya akun? <a href="../php/register.php">Daftar sekarang</a></p>
          <div class="auth-divider"></div>
          <p><a href="../php/fgpass.php">Lupa password Anda?</a></p>
        </div>
      </form>
    </div>
  </section>

  <script src="../js/auth.js"></script>
  <script src="../js/choose.js"></script>
</body>
</html>
