<?php
/**
 * stats_helper.php - Helper untuk kalkulasi statistik galeri HS15
 * (Jumlah Foto dan Jumlah Video)
 */

require_once __DIR__ . '/security_helper.php';

function getGalleryStats($mediaDir = null) {
    global $conn;

    // Jika koneksi database tersedia, hitung langsung dari tabel media yang tersinkronisasi
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $pRes = $conn->query("SELECT COUNT(*) as c FROM media WHERE media_type = 'photo'");
        $vRes = $conn->query("SELECT COUNT(*) as c FROM media WHERE media_type = 'video'");
        if ($pRes && $vRes) {
            return [
                'photos' => (int) ($pRes->fetch_assoc()['c'] ?? 0),
                'videos' => (int) ($vRes->fetch_assoc()['c'] ?? 0)
            ];
        }
    }

    // Fallback: hitung fisik file secara rekursif (termasuk subfolder)
    $mediaDir = $mediaDir ?: get_media_base_dir();
    $mediaDir = rtrim($mediaDir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($mediaDir)) {
        return [
            'photos' => 0,
            'videos' => 0
        ];
    }

    $photoExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $videoExts = ['mp4', 'webm', 'ogg', 'm4v'];

    $photoCount = 0;
    $videoCount = 0;

    $scanned = scan_media_files_recursive($mediaDir);
    foreach ($scanned as $item) {
        $ext = strtolower(pathinfo($item['filename'], PATHINFO_EXTENSION));
        if (in_array($ext, $photoExts, true)) {
            $photoCount++;
        } elseif (in_array($ext, $videoExts, true)) {
            $videoCount++;
        }
    }

    return [
        'photos' => $photoCount,
        'videos' => $videoCount
    ];
}

// Jika diakses langsung via HTTP request (misalnya fetch dari JavaScript), return JSON
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'stats_helper.php') {
    @require_once __DIR__ . '/connect.php';
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode(getGalleryStats());
    exit;
}
