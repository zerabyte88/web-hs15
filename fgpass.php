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
        $email = trim($_POST['email']);

        if (empty($email)) {
            $error_message = "Email tidak boleh kosong.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // AMAN: Simpan email di session server, BUKAN di form HTML
                $_SESSION['reset_email'] = $email;
                $step = 2; 
            } else {
                $error_message = "Email belum terdaftar.";
            }
        }
    }
    // STEP 2: Update Password Baru
    elseif (isset($_POST['step']) && $_POST['step'] == 2) {
        // Cek apakah sesi email valid
        if (!isset($_SESSION['reset_email'])) {
            $error_message = "Sesi tidak valid. Silakan ulangi proses.";
            $step = 1;
        } else {
            $email = $_SESSION['reset_email'];
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($new_password) || empty($confirm_password)) {
                $error_message = "Password tidak boleh kosong.";
                $step = 2;
            } elseif ($new_password !== $confirm_password) {
                $error_message = "Password tidak cocok. Silakan coba lagi.";
                $step = 2;
            } elseif (strlen($new_password) < 6) {
                $error_message = "Password minimal 6 karakter.";
                $step = 2;
            } else {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);

                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $hash, $email);
                
                if ($update_stmt->execute()) {
                    $success_message = "Password berhasil diubah! Silakan login dengan password baru Anda.";
                    $step = 3; 
                    unset($_SESSION['reset_email']); // Hapus sesi setelah sukses
                } else {
                    $error_message = "Terjadi kesalahan database. Silakan coba lagi.";
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
  <title>HS15</title>
  <link rel="stylesheet" href="background.css">
  <link rel="stylesheet" href="register.css">
  <link rel="icon" href="img/logo.jpg" type="image/svg+xml">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>
  <div id="bkgd1"></div>
  <div id="bkgd2"></div>

<section>
  <div class="form-box">
    <div class="form-value">
      <form method="post">
        
        <?php if ($step == 3): ?>
          <h2 style="color: #4CAF50; margin-bottom: 10px;">Berhasil!</h2>
          <div class="alert-success">
            <?= htmlspecialchars($success_message) ?>
          </div>
          <p style="margin-top: 30px;">
            <a href="index.html" class="btn-link">Kembali ke Login</a>
          </p>

        <?php elseif ($step == 2): ?>
          <h2>Masukkan Password Baru</h2>
          <p class="form-subtitle">Email: <strong><?= htmlspecialchars($_SESSION['reset_email']) ?></strong></p>

          <?php if ($error_message): ?>
            <div class="alert-error">
              <?= htmlspecialchars($error_message) ?>
            </div>
          <?php endif; ?>

          <input type="hidden" name="step" value="2">

          <div class="inputbox">
            <input type="password" name="new_password" required>
            <label>Password Baru</label>
            <ion-icon name="lock-closed"></ion-icon>
          </div>

          <div class="inputbox">
            <input type="password" name="confirm_password" required>
            <label>Konfirmasi Password</label>
            <ion-icon name="lock-closed"></ion-icon>
          </div>

          <button type="submit">Update Password</button>
          <p><a href="">Batal & Ulangi</a></p>

        <?php else: ?>
          <h2>Reset Password</h2>
          <p class="form-subtitle">Masukkan email untuk reset password Anda</p>

          <?php if ($error_message): ?>
            <div class="alert-error">
              <?= htmlspecialchars($error_message) ?>
            </div>
          <?php endif; ?>

          <input type="hidden" name="step" value="1">

          <div class="inputbox">
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required autocomplete="off">
            <label>Email</label>
            <ion-icon name="mail"></ion-icon>
          </div>

          <button type="submit">Lanjutkan</button>
          <p>Ingat password Anda? <a href="index.html">Kembali ke Login</a></p>
        <?php endif; ?>

      </form>
    </div>
  </div>
</section>
  <script src="choose.js"></script>
</body>
</html>