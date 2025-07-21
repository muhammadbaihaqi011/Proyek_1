<?php
// Menonaktifkan semua output error PHP agar tidak merusak JSON.
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_once __DIR__ . '/../config/db.php';

$user_id = get_user_id();
$notifications = ['tugas' => []];

// Contoh: Ambil tugas yang akan datang atau yang lewat deadline (status belum selesai)
$stmt = $conn->prepare("SELECT nama_tugas, deadline FROM tugas WHERE user_id = ? AND status != 'selesai' AND deadline <= NOW() + INTERVAL 1 DAY ORDER BY deadline ASC LIMIT 5");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications['tugas'][] = [
            'nama_tugas' => $row['nama_tugas'],
            'deadline' => date('d M H:i', strtotime($row['deadline']))
        ];
    }
    $stmt->close();
} else {
    error_log("Error preparing cek_notif query: " . $conn->error);
}

header('Content-Type: application/json');
echo json_encode($notifications);
?>