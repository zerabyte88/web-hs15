<?php
// connect.php
$host = "localhost";
$user = "root";   // default Laragon
$pass = "";       // default kosong
$db   = "azyuca"; // nama database kamu

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// pastikan encoding benar
$conn->set_charset("utf8mb4");
?>
