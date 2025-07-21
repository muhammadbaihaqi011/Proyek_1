<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

// Fungsi untuk memeriksa apakah pengguna adalah admin
// Untuk saat ini, kita asumsikan user_id = 1 adalah admin.
// Anda sangat disarankan untuk menambahkan kolom 'role' atau 'is_admin' di tabel users.
function require_admin() {
    if (!is_logged_in()) {
        redirect('login.php'); // Redirect ke login jika belum login
    }
    // Asumsi user_id 1 adalah admin. Ganti ini dengan sistem role yang lebih baik.
    if (get_user_id() != 1) {
        // Atau tampilkan pesan error, atau redirect ke dashboard biasa
        die('<div style="font-family: \'Segoe UI\', sans-serif; text-align: center; margin-top: 50px;">
                <h1 style="color: #dc3545;">Akses Ditolak!</h1>
                <p style="font-size: 1.2em;">Anda tidak memiliki izin untuk mengakses halaman ini.</p>
                <a href="dashboard.php" style="text-decoration: none; background-color: #007bff; color: white; padding: 10px 20px; border-radius: 5px;">Kembali ke Dashboard</a>
             </div>');
    }
}

require_admin(); // Panggil fungsi ini untuk melindungi halaman admin

$users = $conn->query("SELECT id, username, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Halaman Admin - Daftar Pengguna</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f0f2f5;
        }
        .navbar {
            background-color: #007bff !important; /* Primary blue, consistent with dashboard */
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
            color: #ffffff !important;
        }
        .container {
            margin-top: 50px;
            padding-top: 20px; /* Space from fixed navbar */
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            overflow: hidden; /* For header border-radius */
        }
        .card-header {
            background-color: #007bff; /* Primary blue for header */
            color: white;
            padding: 20px;
            border-bottom: none;
            font-weight: 600;
            font-size: 1.25rem;
        }
        .table {
            margin-bottom: 0; /* Remove default table margin */
        }
        .table th, .table td {
            vertical-align: middle;
            padding: 15px;
        }
        .table thead th {
            background-color: #0069d9; /* Slightly darker blue for table header */
            color: white;
            border-bottom: none;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.03); /* Light stripe */
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.1); /* Hover effect */
            cursor: pointer;
        }
        .no-users-message {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-style: italic;
        }
        /* Custom styling for logout button in admin */
        .admin-nav .btn-light {
            background-color: #f8f9fa;
            color: #007bff;
            border: 1px solid #007bff;
            transition: all 0.3s ease;
        }
        .admin-nav .btn-light:hover {
            background-color: #e2e6ea;
            color: #0056b3;
            border-color: #0056b3;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"><i class="fa-solid fa-calendar-check me-2"></i> Fokus & Selesai</a>
        <div class="d-flex align-items-center gap-3 admin-nav">
             <span class="fw-semibold text-white"><i class="fa-solid fa-user-shield me-1"></i> Admin</span>
             <a class="btn btn-light" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fa-solid fa-users me-2"></i> Daftar Pengguna Website</h4>
        </div>
        <div class="card-body p-0"> <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Tanggal Daftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= esc($user['id']) ?></td>
                                    <td><?= esc($user['username']) ?></td>
                                    <td><?= esc(date('d M Y H:i:s', strtotime($user['created_at']))) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="no-users-message">Tidak ada pengguna terdaftar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>