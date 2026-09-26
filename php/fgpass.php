<?php
// fgpass.php - Permintaan reset password aman berbasis token dengan penyamaran user enumeration
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/security_helper.php';

// Tutup akses: alihkan siapapun yang mencoba membuka halaman ini kembali ke halaman login
header("Location: index.php");
exit;

$email = '';
$success_message = '';
$error_message = '';
$dev_reset_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error_message = "Sesi keamanan telah berakhir. Silakan muat ulang halaman dan coba lagi.";
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error_message = "Email tidak boleh kosong.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid.";
        } else {
            // Cek apakah email terdaftar
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Email terdaftar: buat token acak aman (berlaku 30 menit)
                $token = generate_password_reset_token($conn, $email);

                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['REQUEST_URI'])), '/');
                $dev_reset_link = $protocol . '://' . $host . $dir . '/reset_password.php?token=' . $token;
            }

            // Pesan selalu sama persis untuk mencegah penyerang menebak email terdaftar (Anti-User Enumeration)
            $success_message = "Jika email Anda terdaftar di sistem kami, instruksi dan tautan untuk mengatur ulang password telah dikirimkan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lupa Password - HS15</title>
  <link rel="stylesheet" href="../css/background.css">
  <link rel="stylesheet" href="../css/auth.css">
  <link rel="icon" type="image/png" sizes="32x32" href="../img/favicon-32x32.png?v=2">
  <link rel="icon" type="image/png" sizes="16x16" href="../img/favicon-16x16.png?v=2">
  <link rel="icon" type="image/png" href="../img/logo.png?v=2">
  <link rel="apple-touch-icon" href="../img/apple-touch-icon.png?v=2">
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
        <img src="../img/logo.png" alt="HS15 Logo" class="auth-logo">
        <h2>Lupa Password</h2>
        <p class="auth-subtitle">Masukkan email terdaftar untuk menerima tautan pemulihan kata sandi</p>
      </div>

      <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
          <ion-icon name="alert-circle-outline"></ion-icon>
          <span><?= htmlspecialchars($error_message) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
          <ion-icon name="mail-unread-outline"></ion-icon>
          <span><?= htmlspecialchars($success_message) ?></span>
        </div>

        <?php if (!empty($dev_reset_link)): ?>
          <div style="margin-top: 18px; padding: 14px; background: rgba(30, 58, 138, 0.4); border: 1px solid rgba(59, 130, 246, 0.4); border-radius: 10px; font-size: 0.85rem; text-align: left;">
            <div style="font-weight: 600; color: #93c5fd; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
              <ion-icon name="link-outline"></ion-icon>
              <span>Simulasi Email (Local Server)</span>
            </div>
            <p style="color: rgba(255, 255, 255, 0.8); font-size: 0.8rem; margin: 0 0 10px 0;">
              Klik tautan reset berikut untuk melanjutkan pembuatan password baru:
            </p>
            <a href="<?= htmlspecialchars($dev_reset_link) ?>" class="btn-link-action" style="font-size: 0.82rem; padding: 8px 12px; display: inline-flex; align-items: center; gap: 6px;">
              <ion-icon name="key-outline"></ion-icon>
              <span>Reset Password Saya Sekarang</span>
            </a>
            <div style="font-size: 0.74rem; color: rgba(255, 255, 255, 0.5); margin-top: 8px;">
              * Tautan unik ini aman dan kedaluwarsa otomatis dalam 30 menit.
            </div>
          </div>
        <?php endif; ?>

        <div class="auth-footer" style="margin-top: 24px;">
          <p><a href="index.php">Kembali ke Halaman Login</a></p>
        </div>

      <?php else: ?>
        <form method="post" autocomplete="off">
          <?= csrf_field() ?>

          <div class="input-group">
            <label for="email">Email Akun</label>
            <div class="input-wrapper">
              <ion-icon name="mail-outline" class="input-icon-left"></ion-icon>
              <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="nama@email.com" required autofocus>
            </div>
          </div>

          <button type="submit" class="btn-submit">
            <span>Kirim Tautan Reset</span>
            <ion-icon name="paper-plane-outline"></ion-icon>
          </button>

          <div class="auth-footer">
            <p>Ingat password Anda? <a href="index.php">Kembali ke Login</a></p>
          </div>
        </form>
      <?php endif; ?>

    </div>
  </section>

  <script src="../js/auth.js"></script>
  <script src="../js/choose.js"></script>
</body>
</html>
