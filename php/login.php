<?php
session_start();
require __DIR__ . '/connect.php';

// Pastikan form mengirimkan data
if (!isset($_POST['email'], $_POST['password'])) {
    header("Location: index.php?error=empty_fields");
    exit;
}

$email = trim($_POST['email']);
$password = $_POST['password'];

if (empty($email) || empty($password)) {
    header("Location: index.php?error=empty_fields");
    exit;
}

// Ambil user berdasarkan email
$stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verifikasi password dengan hash
    if (password_verify($password, $user['password'])) {
        // Login sukses â†’ simpan session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email']   = $user['email'];

        // Arahkan ke halaman beranda
        header("Location: ../html/choose.html");
        exit;
    } else {
        header("Location: index.php?error=invalid_password");
        exit;
    }
} else {
    header("Location: index.php?error=user_not_found");
    exit;
}
?>
