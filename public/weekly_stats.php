<?php
// Menonaktifkan semua output error PHP agar tidak merusak JSON.
// Sangat disarankan untuk menonaktifkan display_errors di php.ini pada lingkungan produksi.
error_reporting(0); 
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/auth.php';
require_login(); // Memastikan pengguna sudah login
require_once __DIR__ . '/../config/db.php';

$user_id = get_user_id();
$labels = [];
$data = [];
$now = new DateTime();
$monday = (clone $now)->modify('monday this week');

for($i=0;$i<7;$i++) {
  $tgl = (clone $monday)->modify("+{$i} day")->format('Y-m-d');
  $labels[] = $tgl;

  // Menggunakan Prepared Statement untuk keamanan dan penanganan error yang lebih baik
  $stmt = $conn->prepare("SELECT COUNT(*) as jml FROM tugas WHERE user_id=? AND status='selesai' AND DATE(deadline)=?");

  if ($stmt === false) {
      error_log("Weekly stats query preparation failed: " . $conn->error);
      $data[] = 0;
      continue;
  }

  $stmt->bind_param("is", $user_id, $tgl);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result && $row = $result->fetch_assoc()) {
      $data[] = (int)$row['jml'];
  } else {
      $data[] = 0;
  }
  $stmt->close();
}

// Pastikan header Content-Type diatur sebagai application/json
header('Content-Type: application/json');
echo json_encode(['labels'=>$labels,'data'=>$data]);
?>