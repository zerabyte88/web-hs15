<?php
session_start();
require 'connect.php';

$error_message = '';
$email_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email_val = $email;

    if (empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Semua kolom wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password minimal terdiri dari 6 karakter.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Konfirmasi password tidak cocok.";
    } else {
        // Cek apakah email sudah terdaftar
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Email ini sudah terdaftar. Silakan gunakan email lain atau login.";
        } else {
            // Hash password
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insert_stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
            $insert_stmt->bind_param("ss", $email, $hash);

            if ($insert_stmt->execute()) {
                header("Location: index.php?registered=1");
                exit;
            } else {
                $error_message = "Terjadi kesalahan saat pendaftaran. Silakan coba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Akun - HS15</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <h2>Buat Akun Baru</h2>
        <p class="auth-subtitle">Bergabung bersama komunitas dokumentasi HS15</p>
      </div>

      <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
          <ion-icon name="alert-circle-outline"></ion-icon>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
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
            <input type="password" id="password" name="password" autocomplete="new-password" placeholder="Minimal 6 karakter" required>
            <button type="button" class="toggle-password" title="Lihat password" aria-label="Toggle password visibility">
              <ion-icon name="eye-outline"></ion-icon>
            </button>
          </div>
        </div>

        <div class="input-group">
          <label for="confirm_password">Konfirmasi Password</label>
          <div class="input-wrapper">
            <ion-icon name="shield-checkmark-outline" class="input-icon-left"></ion-icon>
            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Ulangi password Anda" required>
            <button type="button" class="toggle-password" title="Lihat password" aria-label="Toggle confirm password visibility">
              <ion-icon name="eye-outline"></ion-icon>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <span>Daftar Sekarang</span>
          <ion-icon name="person-add-outline"></ion-icon>
        </button>

        <div class="auth-footer">
          <p>Sudah punya akun? <a href="index.php">Login di sini</a></p>
        </div>
      </form>
    </div>
  </section>

  <script src="auth.js"></script>
  <script src="choose.js"></script>
</body>
</html>