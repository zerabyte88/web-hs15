<?php
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // hash password sebelum simpan
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $hash);

    if ($stmt->execute()) {
        // setelah register, langsung arahkan ke login
        header("Location: index.html");
        exit;
    } else {
        echo "Gagal register: " . $stmt->error;
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>HS15</title>
  <link rel="stylesheet" href="background.css">
  <link rel="stylesheet" href="register.css">
  <link rel="stylesheet" href="choose.css">
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
        <h2>Buat Akun</h2>
          <p class="form-subtitle">Masukkan email dan password Anda</p>

        <div class="inputbox">
          <input type="email" name="email" required autocomplete="off">
            <label>Email</label>
          <ion-icon name="mail"></ion-icon>
        </div>

        <div class="inputbox">
          <input type="password" name="password" required>
            <label>Password</label>
          <ion-icon name="lock-closed"></ion-icon>
        </div>

        <button type="submit">Register</button>
        <p>Sudah punya akun? <a href="index.html">Login di sini</a></p>
      </form>
    </div>
  </div>
</section>

  <script src="choose.js"></script>

</body>
</html>