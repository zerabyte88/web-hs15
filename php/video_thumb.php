<?php
/**
 * video_thumb.php - Endpoint untuk menyimpan thumbnail video yang di-capture client-side
 * Tanpa ffmpeg — thumbnail di-generate oleh browser dan dikirim sebagai base64
 */
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/security_helper.php';

// Hanya terima POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Wajib login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$filename = $_POST['filename'] ?? '';
$imageData = $_POST['image_data'] ?? '';

if (empty($filename) || empty($imageData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing filename or image_data']);
    exit;
}

// Validasi base64 data
if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $imageData, $matches)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image data format']);
    exit;
}

// Sanitasi filename — hanya izinkan karakter aman
$clean = ltrim(str_replace(['\\'], '/', $filename), '/');
$relDir = dirname($clean);
$baseName = pathinfo($clean, PATHINFO_FILENAME);

$thumbsDir = get_thumbs_base_dir();
$subDirPart = ($relDir !== '.' && $relDir !== '') ? str_replace('/', DIRECTORY_SEPARATOR, $relDir) . DIRECTORY_SEPARATOR : '';
$posterPath = $thumbsDir . $subDirPart . $baseName . '.jpg';

// Jika poster sudah ada, langsung kembalikan URL-nya
if (file_exists($posterPath) && filesize($posterPath) > 0) {
    $subDirUrl = ($relDir !== '.' && $relDir !== '') ? $relDir . '/' : '';
    echo json_encode([
        'success' => true,
        'poster_url' => media_url('thumbs/' . $subDirUrl . $baseName . '.jpg'),
        'cached' => true
    ]);
    exit;
}

// Decode base64 data
$base64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
$decoded = base64_decode($base64, true);

if ($decoded === false || strlen($decoded) < 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Failed to decode image data']);
    exit;
}

// Pastikan direktori tujuan ada
$targetDir = dirname($posterPath);
if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0755, true);
}

// Buat image dari data, resize ke 480px width, simpan sebagai JPEG
$srcImage = @imagecreatefromstring($decoded);
if (!$srcImage) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image content']);
    exit;
}

$srcWidth = imagesx($srcImage);
$srcHeight = imagesy($srcImage);
$thumbWidth = 480;
$thumbHeight = max(1, (int) floor($srcHeight * ($thumbWidth / max(1, $srcWidth))));

$thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
imagecopyresampled($thumb, $srcImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $srcWidth, $srcHeight);
$saved = imagejpeg($thumb, $posterPath, 80);

imagedestroy($srcImage);
imagedestroy($thumb);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save poster']);
    exit;
}

$subDirUrl = ($relDir !== '.' && $relDir !== '') ? $relDir . '/' : '';
echo json_encode([
    'success' => true,
    'poster_url' => media_url('thumbs/' . $subDirUrl . $baseName . '.jpg'),
    'cached' => false
]);
