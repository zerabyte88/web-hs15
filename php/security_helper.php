<?php
// security_helper.php - Helper keamanan terpusat untuk proteksi CSRF, Rate Limiting, dan Token Reset Password

if (session_status() === PHP_SESSION_NONE) {
    // Pengaturan cookie sesi yang lebih aman jika belum dimulai
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        // SameSite = Lax untuk kompatibilitas form navigasi
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        session_start();
    } else {
        @session_start();
    }
}

/**
 * Inisialisasi otomatis tabel database yang dibutuhkan jika belum ada
 */
function init_security_tables(mysqli $conn): void {
    static $initialized = false;
    if ($initialized) return;

    $loginAttemptsSql = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        email VARCHAR(255) NOT NULL,
        attempted_at DATETIME NOT NULL,
        INDEX idx_ip_time (ip_address, attempted_at),
        INDEX idx_email_time (email, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $passwordResetsSql = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $mediaSql = "CREATE TABLE IF NOT EXISTS media (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(500) NOT NULL UNIQUE,
        original_name VARCHAR(255) NULL,
        media_type ENUM('photo', 'video') NOT NULL,
        title VARCHAR(255) NULL,
        description TEXT NULL,
        media_date DATE NULL,
        media_time TIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type (media_type),
        INDEX idx_date (media_date, media_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    @$conn->query($loginAttemptsSql);
    @$conn->query($passwordResetsSql);
    @$conn->query($mediaSql);
    @$conn->query("ALTER TABLE media MODIFY filename VARCHAR(500) NOT NULL");

    // Pastikan ada setidaknya satu akun admin
    $adminCheck = @$conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($adminCheck && $adminCheck->num_rows === 0) {
        $admSet = @$conn->query("UPDATE users SET role = 'admin' WHERE email = 'adm_azyuca@gmail.com'");
        if ($conn->affected_rows === 0) {
            @$conn->query("UPDATE users SET role = 'admin' ORDER BY id ASC LIMIT 1");
        }
    }

    $initialized = true;
}

/* ==========================================================================
   1. CSRF (Cross-Site Request Forgery) Protection
   ========================================================================== */

/**
 * Mengambil atau membuat CSRF token yang aman
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Menghasilkan elemen input hidden untuk disisipkan ke form HTML
 */
function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Memvalidasi token CSRF dari request POST menggunakan hash_equals
 */
function validate_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken = $_POST['csrf_token'] ?? '';

    if (empty($sessionToken) || empty($postToken)) {
        return false;
    }

    return hash_equals($sessionToken, $postToken);
}

/* ==========================================================================
   2. Pembatasan Percobaan Login (Rate Limiting / Brute-Force Protection)
   ========================================================================== */

/**
 * Mengambil alamat IP klien dengan aman
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Validasi format IPv4 / IPv6
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '0.0.0.0';
}

/**
 * Memeriksa apakah IP atau email sedang terkena batas percobaan login
 * Aturan: Maksimal 5 percobaan gagal dalam rentang waktu 15 menit
 */
function check_login_attempts(mysqli $conn, string $email): array {
    init_security_tables($conn);
    $ip = get_client_ip();
    $maxAttempts = 5;
    $lockoutMinutes = 15;

    // Bersihkan data lama di atas 24 jam secara acak (1% chance per call)
    if (mt_rand(1, 100) === 1) {
        $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) as failed_count, MAX(attempted_at) as last_attempt
        FROM login_attempts 
        WHERE (ip_address = ? OR email = ?) 
          AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->bind_param("ssi", $ip, $email, $lockoutMinutes);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $failedCount = (int) ($res['failed_count'] ?? 0);
    $lastAttempt = $res['last_attempt'] ?? null;

    if ($failedCount >= $maxAttempts && $lastAttempt) {
        // Hitung sisa waktu tunggu
        $lastAttemptTime = strtotime($lastAttempt);
        $unlockTime = $lastAttemptTime + ($lockoutMinutes * 60);
        $secondsRemaining = max(0, $unlockTime - time());
        $minutesRemaining = max(1, (int) ceil($secondsRemaining / 60));

        if ($secondsRemaining > 0) {
            return [
                'blocked' => true,
                'wait_minutes' => $minutesRemaining,
                'seconds_remaining' => $secondsRemaining
            ];
        }
    }

    return [
        'blocked' => false,
        'remaining_attempts' => max(0, $maxAttempts - $failedCount)
    ];
}

/**
 * Mencatat percobaan login yang gagal
 */
function record_login_failure(mysqli $conn, string $email): void {
    init_security_tables($conn);
    $ip = get_client_ip();
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $ip, $email);
    $stmt->execute();
}

/**
 * Menghapus catatan percobaan gagal setelah login berhasil
 */
function clear_login_attempts(mysqli $conn, string $email): void {
    init_security_tables($conn);
    $ip = get_client_ip();
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR email = ?");
    $stmt->bind_param("ss", $ip, $email);
    $stmt->execute();
}

/* ==========================================================================
   3. Token Reset Password (Time-limited & Cryptographically Secure)
   ========================================================================== */

/**
 * Menghasilkan token reset password baru (berlaku 30 menit)
 */
function generate_password_reset_token(mysqli $conn, string $email): string {
    init_security_tables($conn);

    // Hapus token lama untuk email ini
    $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
    $del->bind_param("s", $email);
    $del->execute();

    // Buat token acak 64 karakter hex
    $token = bin2hex(random_bytes(32));

    // Token berlaku selama 30 menit
    $stmt = $conn->prepare("
        INSERT INTO password_resets (email, token, expires_at, created_at) 
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())
    ");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();

    return $token;
}

/**
 * Memverifikasi validitas token reset password
 */
function verify_password_reset_token(mysqli $conn, string $token): ?array {
    init_security_tables($conn);

    // Bersihkan token yang sudah kedaluwarsa secara berkala
    if (mt_rand(1, 50) === 1) {
        $conn->query("DELETE FROM password_resets WHERE expires_at < NOW()");
    }

    $stmt = $conn->prepare("
        SELECT id, email, expires_at 
        FROM password_resets 
        WHERE token = ? AND expires_at > NOW() 
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        return $res->fetch_assoc();
    }

    return null;
}

/**
 * Menghapus token setelah password berhasil diperbarui
 */
function consume_password_reset_token(mysqli $conn, string $token): void {
    init_security_tables($conn);
    $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
}

/* ==========================================================================
   4. Otorisasi & Manajemen Peran (Admin & Member)
   ========================================================================== */

/**
 * Memeriksa apakah user yang sedang aktif memiliki role admin
 */
function is_admin(?mysqli $conn = null): bool {
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    // Jika role tersimpan di session dan admin, return true
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }
    // Jika $conn disediakan atau role belum disinkronkan, cek database
    if ($conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $uid = (int) $_SESSION['user_id'];
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $_SESSION['role'] = $row['role'] ?? 'member';
                return $_SESSION['role'] === 'admin';
            }
        }
    }
    return false;
}

/**
 * Memastikan hanya akun dengan peran admin yang dapat mengakses halaman
 * Jika bukan admin, redirect ke beranda atau kirim 403 Forbidden
 */
function require_admin(mysqli $conn): void {
    if (empty($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
    if (!is_admin($conn)) {
        http_response_code(403);
        header("Location: choose.php?error=unauthorized");
        exit;
    }
}

/* ==========================================================================
   5. Helper Format Media & Pembuatan Thumbnail / Poster
   ========================================================================== */

/**
 * Memformat tanggal YYYY-MM-DD ke format bahasa Indonesia
 */
function formatIndonesianDate(?string $dateYmd): string {
    if (empty($dateYmd)) return '';
    $months = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $parts = explode('-', $dateYmd);
    if (count($parts) === 3) {
        $year = $parts[0];
        $month = $months[$parts[1]] ?? $parts[1];
        $day = ltrim($parts[2], '0');
        return "$day $month $year";
    }
    return $dateYmd;
}

/**
 * Ekstraksi tanggal, waktu, dan judul default dari pola nama file
 */
function parseMediaFilenameDate(string $filename): array {
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $date = null;
    $time = null;
    $defaultTitle = ucwords(str_replace(['_', '-'], ' ', $base));

    if (preg_match('/^(?:IMG_|VID_|video_)?(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})$/i', $base, $m)) {
        $date = "{$m[1]}-{$m[2]}-{$m[3]}";
        $time = "{$m[4]}:{$m[5]}:{$m[6]}";
        $defaultTitle = "Dokumentasi {$m[3]}/{$m[2]}/{$m[1]}";
    } elseif (preg_match('/^(?:VID|IMG)-(\d{4})(\d{2})(\d{2})-/i', $base, $m)) {
        $date = "{$m[1]}-{$m[2]}-{$m[3]}";
        $defaultTitle = "Dokumentasi {$m[3]}/{$m[2]}/{$m[1]}";
    }
    return [
        'date' => $date,
        'time' => $time,
        'title' => $defaultTitle
    ];
}

/**
 * Menghasilkan judul/keterangan tampilan untuk foto atau video
 */
if (!function_exists('formatMediaCaption')) {
    function formatMediaCaption(string $filename, ?string $title = null, ?string $date = null, ?string $time = null): string {
        if (!empty($title)) {
            return $title;
        }
        $parsed = parseMediaFilenameDate($filename);
        $useDate = !empty($date) ? $date : $parsed['date'];
        $useTime = !empty($time) ? $time : $parsed['time'];

        if ($useDate) {
            $dateStr = formatIndonesianDate($useDate);
            if ($useTime) {
                $timeShort = substr($useTime, 0, 5);
                return "$dateStr • $timeShort";
            }
            return $dateStr;
        }
        return $parsed['title'];
    }
}

/**
 * Mencari path executable ffmpeg.exe pada sistem
 */
function get_ffmpeg_path(): ?string {
    $candidates = [
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ffmpeg.exe',
        __DIR__ . DIRECTORY_SEPARATOR . 'ffmpeg.exe',
        'ffmpeg.exe',
        'ffmpeg'
    ];
    foreach ($candidates as $cand) {
        if (file_exists($cand)) {
            return realpath($cand);
        }
    }
    return null;
}

/**
 * Menghasilkan thumbnail poster JPEG untuk video menggunakan ffmpeg
 */
function generate_video_poster(string $videoPath, string $posterPath): bool {
    $ffmpeg = get_ffmpeg_path();
    if (!$ffmpeg || !file_exists($videoPath)) {
        return false;
    }
    $posterDir = dirname($posterPath);
    if (!is_dir($posterDir)) {
        @mkdir($posterDir, 0755, true);
    }
    $cmd = '"' . $ffmpeg . '" -i ' . escapeshellarg($videoPath) . ' -ss 00:00:02.000 -vframes 1 ' . escapeshellarg($posterPath) . ' -y 2>&1';
    @shell_exec($cmd);
    return file_exists($posterPath) && filesize($posterPath) > 0;
}

/**
 * Menghasilkan thumbnail WebP/JPEG terkompresi untuk foto
 */
function generate_photo_thumbnail(string $src, string $dest, int $thumbWidth = 480): bool {
    if (!file_exists($src)) return false;
    $thumbDir = dirname($dest);
    if (!is_dir($thumbDir)) {
        @mkdir($thumbDir, 0755, true);
    }

    $info = @getimagesize($src);
    if (!$info) return false;
    [$width, $height] = $info;
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg': $image = @imagecreatefromjpeg($src); break;
        case 'image/png':  $image = @imagecreatefrompng($src); break;
        case 'image/gif':  $image = @imagecreatefromgif($src); break;
        case 'image/webp': $image = @imagecreatefromwebp($src); break;
        default: return false;
    }
    if (!$image) return false;

    $newHeight = max(1, (int) floor($height * ($thumbWidth / $width)));
    $tmp = imagecreatetruecolor($thumbWidth, $newHeight);

    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
    }

    imagecopyresampled($tmp, $image, 0, 0, 0, 0, $thumbWidth, $newHeight, $width, $height);
    $ext = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
    $saved = false;

    if ($ext === 'webp' && function_exists('imagewebp')) {
        $saved = imagewebp($tmp, $dest, 78);
    } elseif ($ext === 'png') {
        $saved = imagepng($tmp, $dest, 7);
    } elseif ($ext === 'gif') {
        $saved = imagegif($tmp, $dest);
    } else {
        $saved = imagejpeg($tmp, $dest, 80);
    }

    if (PHP_VERSION_ID < 80500 && function_exists('imagedestroy')) {
        @imagedestroy($image);
        @imagedestroy($tmp);
    }
    return $saved;
}

/* ==========================================================================
   6. Sinkronisasi File Media ke Database (media table) & Media Helpers
   ========================================================================== */

/**
 * Mengambil path folder root media fisik
 */
function get_media_base_dir(): string {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) {
        $legacy = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'gallery' . DIRECTORY_SEPARATOR;
        if (is_dir($legacy)) {
            return $legacy;
        }
    }
    return $dir;
}

/**
 * Mengambil path folder root thumbs media
 */
function get_thumbs_base_dir(): string {
    $dir = get_media_base_dir() . 'thumbs' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Menghasilkan URL publik untuk file media (mendukung subfolder & encoding aman)
 */
function media_url(string $relPath): string {
    $clean = ltrim(str_replace(['\\'], '/', $relPath), '/');
    $segments = explode('/', $clean);
    $encoded = array_map('rawurlencode', $segments);
    return '../media/' . implode('/', $encoded);
}

/**
 * Mengambil atau menghasilkan URL thumbnail gambar WebP / poster video
 */
function get_media_thumb_url(string $filename, string $mediaType = 'photo', bool $generateIfMissing = true): string {
    $clean = ltrim(str_replace(['\\'], '/', $filename), '/');
    $relDir = dirname($clean);
    $base = pathinfo($clean, PATHINFO_FILENAME);

    $thumbsBaseDir = get_thumbs_base_dir();
    $mediaBaseDir  = get_media_base_dir();

    $subDirPart = ($relDir !== '.' && $relDir !== '') ? str_replace('/', DIRECTORY_SEPARATOR, $relDir) . DIRECTORY_SEPARATOR : '';
    $subDirUrl  = ($relDir !== '.' && $relDir !== '') ? $relDir . '/' : '';

    if ($mediaType === 'photo') {
        $targetDiskThumb = $thumbsBaseDir . $subDirPart . $base . '.webp';
        $flatDiskThumb   = $thumbsBaseDir . $base . '.webp';

        if (file_exists($targetDiskThumb)) {
            return media_url('thumbs/' . $subDirUrl . $base . '.webp');
        }
        if (file_exists($flatDiskThumb)) {
            return media_url('thumbs/' . $base . '.webp');
        }

        if ($generateIfMissing) {
            $srcDisk = $mediaBaseDir . str_replace('/', DIRECTORY_SEPARATOR, $clean);
            if (file_exists($srcDisk)) {
                if (!is_dir(dirname($targetDiskThumb))) {
                    @mkdir(dirname($targetDiskThumb), 0755, true);
                }
                if (generate_photo_thumbnail($srcDisk, $targetDiskThumb, 480)) {
                    return media_url('thumbs/' . $subDirUrl . $base . '.webp');
                }
            }
        }

        return media_url($clean);
    } else {
        $targetDiskPoster = $thumbsBaseDir . $subDirPart . $base . '.jpg';
        $flatDiskPoster   = $thumbsBaseDir . $base . '.jpg';

        if (file_exists($targetDiskPoster)) {
            return media_url('thumbs/' . $subDirUrl . $base . '.jpg');
        }
        if (file_exists($flatDiskPoster)) {
            return media_url('thumbs/' . $base . '.jpg');
        }

        if ($generateIfMissing) {
            $srcDisk = $mediaBaseDir . str_replace('/', DIRECTORY_SEPARATOR, $clean);
            if (file_exists($srcDisk)) {
                if (!is_dir(dirname($targetDiskPoster))) {
                    @mkdir(dirname($targetDiskPoster), 0755, true);
                }
                if (generate_video_poster($srcDisk, $targetDiskPoster)) {
                    return media_url('thumbs/' . $subDirUrl . $base . '.jpg');
                }
            }
        }

        return '../img/logo.png';
    }
}

/**
 * Menghapus file media fisik beserta thumbnail WebP / poster JPG
 */
function delete_media_file_and_thumbs(string $filename): void {
    $clean = ltrim(str_replace(['\\'], '/', $filename), '/');
    $mediaBaseDir  = get_media_base_dir();
    $thumbsBaseDir = get_thumbs_base_dir();

    $filePath = $mediaBaseDir . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    if (is_file($filePath)) {
        @unlink($filePath);
    }

    $relDir = dirname($clean);
    $base = pathinfo($clean, PATHINFO_FILENAME);
    $subDirPart = ($relDir !== '.' && $relDir !== '') ? str_replace('/', DIRECTORY_SEPARATOR, $relDir) . DIRECTORY_SEPARATOR : '';

    $thumbCandidates = [
        $thumbsBaseDir . $subDirPart . $base . '.webp',
        $thumbsBaseDir . $subDirPart . $base . '.jpg',
        $thumbsBaseDir . $base . '.webp',
        $thumbsBaseDir . $base . '.jpg'
    ];
    foreach ($thumbCandidates as $tc) {
        if (is_file($tc)) {
            @unlink($tc);
        }
    }
}

/**
 * Membersihkan seluruh isi direktori thumbs secara rekursif
 */
function clean_thumbs_directory(string $dir): void {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.htaccess') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            clean_thumbs_directory($path);
            @rmdir($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * Scan direktori media secara rekursif untuk membaca seluruh foto & video
 * termasuk yang berada di dalam subfolder
 */
function scan_media_files_recursive(string $baseDir): array {
    $results = [];
    $baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($baseDir)) return $results;

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) continue;

            $pathname = $item->getPathname();
            $relPath = substr($pathname, strlen($baseDir));
            $relPath = str_replace('\\', '/', $relPath);

            // Lewati folder thumbs, file tersembunyi, dan file .htaccess
            if (str_starts_with($relPath, 'thumbs/') || $relPath === 'thumbs') continue;
            if (basename($relPath) === '.htaccess' || str_starts_with(basename($relPath), '.')) continue;

            $results[] = [
                'rel_path'  => $relPath,
                'full_path' => $pathname,
                'filename'  => basename($relPath),
                'mtime'     => $item->getMTime()
            ];
        }
    } catch (\Throwable $e) {}

    return $results;
}

/**
 * Memastikan semua file fisik yang ada di folder media (termasuk subfolder) tersimpan di tabel media
 */
function sync_media_files_to_db(mysqli $conn, ?string $mediaDir = null): int {
    init_security_tables($conn);
    if ($mediaDir === null) {
        $mediaDir = get_media_base_dir();
    }
    $mediaDir = rtrim($mediaDir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($mediaDir)) return 0;

    $existing = [];
    $res = $conn->query("SELECT id, filename FROM media");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing[$row['filename']] = (int) $row['id'];
        }
    }

    $photoExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $videoExts = ['mp4', 'webm', 'ogg', 'm4v'];

    $scannedFiles = scan_media_files_recursive($mediaDir);
    if (empty($scannedFiles)) return 0;

    $stmt = $conn->prepare("
        INSERT INTO media (filename, original_name, media_type, title, description, media_date, media_time)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) return 0;

    $inserted = 0;
    foreach ($scannedFiles as $fileInfo) {
        $relPath   = $fileInfo['rel_path'];
        $baseName  = $fileInfo['filename'];
        $fullPath  = $fileInfo['full_path'];
        $mtime     = $fileInfo['mtime'];

        if (isset($existing[$relPath])) continue;

        $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
        $mediaType = null;
        if (in_array($ext, $photoExts, true)) {
            $mediaType = 'photo';
        } elseif (in_array($ext, $videoExts, true)) {
            $mediaType = 'video';
        } else {
            continue;
        }

        $parsed = parseMediaFilenameDate($baseName);
        $mediaDate = $parsed['date'] ?: date('Y-m-d', $mtime);
        $mediaTime = $parsed['time'] ?: date('H:i:s', $mtime);

        // Jika file ada di dalam subfolder, gunakan nama folder sebagai konteks judul
        $folderName = dirname($relPath);
        $title = $parsed['title'];
        if ($folderName !== '.' && !empty($folderName)) {
            if (empty($title) || str_starts_with($title, 'Dokumentasi ')) {
                $title = $folderName . ' (' . formatIndonesianDate($mediaDate) . ')';
            } else {
                $title = $folderName . ' - ' . $title;
            }
        }

        $desc = null;
        $origName = $baseName;

        $stmt->bind_param("sssssss", $relPath, $origName, $mediaType, $title, $desc, $mediaDate, $mediaTime);
        if ($stmt->execute()) {
            $inserted++;
            $existing[$relPath] = $stmt->insert_id;
        }
    }

    return $inserted;
}


/**
 * Mengambil URL avatar profil pengguna dengan verifikasi keberadaan file di disk.
 * Jika file tidak ditemukan, otomatis mencari file fallback user_{id}_* atau logo default.
 */
function get_user_avatar(?string $photoPath, int $userId = 0): string {
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;

    if (!empty($photoPath)) {
        $cleaned = ltrim(str_replace(['../', '..\\'], '', $photoPath), '/\\');
        $diskPath = $baseDir . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cleaned);
        if (is_file($diskPath)) {
            return '../' . str_replace('\\', '/', $cleaned);
        }
    }

    if ($userId > 0) {
        $profileDir = $baseDir . 'img' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR;
        $matches = glob($profileDir . 'user_' . $userId . '_*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
        if (!empty($matches)) {
            $latest = end($matches);
            return '../img/profiles/' . basename($latest);
        }
    }

    return '../img/logo.png';
}

/**
 * Mengambil avatar pengguna yang sedang login saat ini dari sesi & database.
 */
function get_current_user_avatar(mysqli $conn): string {
    if (empty($_SESSION['user_id'])) {
        return '../img/logo.png';
    }
    $uid = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            return get_user_avatar($row['profile_photo'] ?? null, $uid);
        }
    }
    return '../img/logo.png';
}
