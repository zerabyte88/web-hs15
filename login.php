<?php
session_start();
require 'connect.php';

// pastikan form mengirimkan data
if (!isset($_POST['email'], $_POST['password'])) {
    exit("Form tidak lengkap.");
}

$email = trim($_POST['email']);
$password = $_POST['password'];

// ambil user berdasarkan email
$stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // verifikasi password dengan hash
    if (password_verify($password, $user['password'])) {
        // login sukses → simpan session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email']   = $user['email'];

        // arahkan ke halaman berikut
        header("Location: choose.html");
        exit;
    } else {
        echo "Password salah!";
    }
} else {
    echo "Email tidak ditemukan!";
}
?>