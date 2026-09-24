<?php
// media.php - Endpoint penyaji media galeri dengan proteksi autentikasi session dan HTTP Range streaming

require_once __DIR__ . '/security_helper.php';

// 1. Validasi Autentikasi Pengguna
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Akses ditolak: Anda harus login untuk mengakses konten media galeri HS15.');
}

// 2. Ambil parameter file dan bersihkan
$file = $_GET['file'] ?? $_GET['path'] ?? '';
if (empty($file)) {
    http_response_code(400);
    exit('Parameter file tidak valid.');
}

// Cegah null-byte injection
$file = str_replace(chr(0), '', $file);

// Normalisasi direktori dasar gallery
$galleryDir = realpath(__DIR__ . '/../gallery');
if (!$galleryDir) {
    http_response_code(500);
    exit('Direktori galeri tidak ditemukan di server.');
}

// Pastikan file path valid dan berada di dalam folder gallery (Mencegah Directory Traversal)
$normalizedRel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
$targetPath = realpath($galleryDir . DIRECTORY_SEPARATOR . $normalizedRel);

if (!$targetPath || !is_file($targetPath) || !str_starts_with($targetPath, $galleryDir)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('File media tidak ditemukan atau akses ditolak.');
}

// 3. Tentukan MIME Type yang tepat
$ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'webp' => 'image/webp',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogg'  => 'video/ogg',
    'm4v'  => 'video/mp4'
];

$mime = $mimeTypes[$ext] ?? 'application/octet-stream';
$fileSize = filesize($targetPath);
$lastModified = filemtime($targetPath);
$etag = '"' . md5($targetPath . $lastModified . $fileSize) . '"';

// 4. Cek HTTP Cache (ETag & Last-Modified)
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified) {
    http_response_code(304);
    exit;
}

// Matikan output buffering jika aktif agar streaming tidak memakan banyak memori
if (ob_get_level()) {
    ob_end_clean();
}

// Header dasar
header('Content-Type: ' . $mime);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('ETag: ' . $etag);
header('Cache-Control: private, max-age=86400');
header('Accept-Ranges: bytes');

// 5. Dukungan HTTP Byte Range Request (Khusus Video Seeking / Streaming)
$start = 0;
$end = $fileSize - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = (float) $matches[1];
        if (!empty($matches[2])) {
            $end = (float) $matches[2];
        }
    }

    if ($start > $end || $start >= $fileSize || $end >= $fileSize) {
        http_response_code(416); // Requested Range Not Satisfiable
        header("Content-Range: bytes */$fileSize");
        exit;
    }

    http_response_code(206); // Partial Content
    header("Content-Range: bytes $start-$end/$fileSize");
    $length = $end - $start + 1;
    header("Content-Length: $length");

    $fp = fopen($targetPath, 'rb');
    if ($fp) {
        fseek($fp, $start);
        $buffer = 1024 * 64; // 64KB per chunk
        while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
            if ($pos + $buffer > $end) {
                $buffer = $end - $pos + 1;
            }
            echo fread($fp, (int) $buffer);
            flush();
        }
        fclose($fp);
    }
    exit;
}

// 6. Pengiriman File Normal (Full Content)
header('Content-Length: ' . $fileSize);
readfile($targetPath);
exit;

