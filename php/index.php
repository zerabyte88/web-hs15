<?php
require_once __DIR__ . '/security_helper.php';

// Jika sudah login, bisa langsung redirect ke choose.php
if (isset($_SESSION['user_id'])) {
    header("Location: choose.php");
    exit;
}

$error_message = '';
$success_message = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_credentials':
        case 'invalid_password':
        case 'user_not_found':
            $error_message = 'Email atau password yang Anda masukkan salah.';
            break;
        case 'too_many_attempts':
            $wait = isset($_GET['wait']) ? max(1, (int)$_GET['wait']) : 15;
            $error_message = 'Terlalu banyak percobaan login gagal. Silakan coba lagi dalam ' . $wait . ' menit.';
            break;
        case 'csrf_error':
            $error_message = 'Sesi keamanan telah berakhir. Silakan coba lagi.';
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
        <p class="auth-subtitle">Masukkan email dan password untuk lanjut</p>
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
        <?= csrf_field() ?>
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
      </form>
    </div>
  </section>

  <script src="../js/auth.js"></script>
  <script src="../js/choose.js"></script>
</body>
</html>
