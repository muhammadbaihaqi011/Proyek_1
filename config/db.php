<?php
// Konfigurasi Database
$db_host = 'localhost';
$db_user = 'root'; // Sesuaikan dengan username database Anda
$db_pass = '';     // Sesuaikan dengan password database Anda
$db_name = 'fokus_selesai'; // Sesuaikan dengan nama database Anda

// Buat koneksi
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Periksa koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Set charset ke utf8mb4 untuk dukungan emoji dan karakter khusus
$conn->set_charset("utf8mb4");

?>