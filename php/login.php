<?php
// login.php - Proses autentikasi login dengan proteksi CSRF, rate limiting, dan penyamaran error
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/security_helper.php';

// 1. Validasi CSRF Token
if (!validate_csrf()) {
    header("Location: index.php?error=csrf_error");
    exit;
}

// 2. Pastikan form mengirimkan data
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

// 3. Periksa Pembatasan Percobaan Login (Rate Limiting)
$rateLimit = check_login_attempts($conn, $email);
if ($rateLimit['blocked']) {
    $waitMinutes = (int) $rateLimit['wait_minutes'];
    header("Location: index.php?error=too_many_attempts&wait=" . $waitMinutes);
    exit;
}

// 4. Ambil user berdasarkan email
$stmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verifikasi password dengan hash
    if (password_verify($password, $user['password'])) {
        // Hapus catatan percobaan gagal
        clear_login_attempts($conn, $email);

        // Regenerasi session ID untuk mencegah Session Fixation
        session_regenerate_id(true);

        // Login sukses -> simpan session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'] ?? 'member';

        // Arahkan ke halaman beranda
        header("Location: choose.php");
        exit;
    } else {
        // Password salah -> catat kegagalan & samarkan pesan error
        record_login_failure($conn, $email);
        header("Location: index.php?error=invalid_credentials");
        exit;
    }
} else {
    // User tidak ditemukan -> lakukan dummy password_verify untuk mitigasi timing attack
    password_verify($password, '$2y$10$abcdefghijklmnopqrstuuabcdefghijklmnopqrstuuabcdefghijk');

    // Catat kegagalan & gunakan pesan error yang sama persis (Anti-User Enumeration)
    record_login_failure($conn, $email);
    header("Location: index.php?error=invalid_credentials");
    exit;
}
