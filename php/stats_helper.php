<?php
/**
 * stats_helper.php - Helper untuk kalkulasi statistik galeri HS15
 * (Jumlah Foto dan Jumlah Video)
 */

function getGalleryStats($galleryDir = null) {
    if ($galleryDir === null) {
        $galleryDir = __DIR__ . '/../gallery/';
    }
    $galleryDir = rtrim($galleryDir, '/\\') . DIRECTORY_SEPARATOR;

    if (!is_dir($galleryDir)) {
        return [
            'photos' => 0,
            'videos' => 0
        ];
    }

    $photoExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $videoExts = ['mp4', 'webm', 'ogg', 'm4v'];

    $photoCount = 0;
    $videoCount = 0;

    $files = scandir($galleryDir);
    if ($files !== false) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === 'thumbs') continue;
            $fullPath = $galleryDir . $file;
            if (is_file($fullPath)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, $photoExts, true)) {
                    $photoCount++;
                } elseif (in_array($ext, $videoExts, true)) {
                    $videoCount++;
                }
            }
        }
    }

    return [
        'photos' => $photoCount,
        'videos' => $videoCount
    ];
}

// Jika diakses langsung via HTTP request (misalnya fetch dari JavaScript), return JSON
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'stats_helper.php') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60');
    echo json_encode(getGalleryStats());
    exit;
}
