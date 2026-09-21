<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['image' => '../img/logo.jpg']);
    exit;
}

require __DIR__ . '/connect.php';
$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT profile_photo FROM users WHERE id = ?');

if (!$stmt) {
    echo json_encode(['image' => '../img/logo.jpg']);
    exit;
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$image = $user['profile_photo'] ?? '';

if (!$image || !is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image))) {
    $image = '../img/logo.jpg';
} else {
    $image = '../' . $image;
}

echo json_encode(['image' => $image]);
