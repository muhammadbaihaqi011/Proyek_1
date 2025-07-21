<?php
// Aktifkan semua error reporting untuk debugging. Matikan ini di lingkungan produksi.
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login(); // Memastikan pengguna sudah login

// --- Fungsi format tanggal Indonesia (Jika belum ada di functions.php) ---
if (!function_exists('tgl_indo_edlink')) {
    function tgl_indo_edlink($date)
    {
        $hari = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $d = date('w', strtotime($date));
        $day = $hari[$d];
        $tgl = date('d', strtotime($date));
        $bln = $bulan[(int)date('m', strtotime($date)) - 1];
        $thn = date('Y', strtotime($date));
        return "$day, $tgl $bln $thn";
    }
}


$user_id = get_user_id();
$username = get_username();

// Ambil data user beserta avatar
$user_data_query = $conn->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
if ($user_data_query === false) {
    error_log("Error preparing user data query: " . $conn->error);
    $user_data = ['username' => 'Pengguna', 'avatar' => null]; // Fallback
} else {
    $user_data_query->bind_param("i", $user_id);
    $user_data_query->execute();
    $user_data_result = $user_data_query->get_result();
    $user_data = $user_data_result->fetch_assoc();
    $user_data_query->close();

    if (!$user_data) {
        $user_data = ['username' => 'Pengguna', 'avatar' => null]; // Fallback jika tidak ditemukan
    }
}


// --- Calendar and Date Handling ---
$now = new DateTime();
$today_str = $now->format('Y-m-d');

// Determine the month and year to display in the calendar
// Default to current month/year if not specified in GET parameters
$display_month = isset($_GET['display_month']) ? (int)$_GET['display_month'] : (int)$now->format('m');
$display_year = isset($_GET['display_year']) ? (int)$_GET['display_year'] : (int)$now->format('Y');

// Create a DateTime object for the first day of the display month
$display_date_obj = new DateTime("{$display_year}-{$display_month}-01");

// Determine the Monday of the week containing the first day of the display month
// This will be the start of our calendar display for the week rows
$calendar_start_date_obj = (clone $display_date_obj)->modify('monday this week');

// Calculate previous and next month for navigation links
$prev_month_date_obj = (clone $display_date_obj)->modify('-1 month');
$next_month_date_obj = (clone $display_date_obj)->modify('+1 month');

$prev_month_link = 'dashboard.php?display_month=' . $prev_month_date_obj->format('m') . '&display_year=' . $prev_month_date_obj->format('Y');
$next_month_link = 'dashboard.php?display_month=' . $next_month_date_obj->format('m') . '&display_year=' . $next_month_date_obj->format('Y');

// Ambil semua tanggal tugas/acara untuk BULAN YANG DITAMPILKAN di kalender untuk tanda (dot)
$month_start_full = $display_date_obj->format('Y-m-01 00:00:00');
$month_end_full = (clone $display_date_obj)->modify('last day of this month')->format('Y-m-d 23:59:59');

$dates_with_activity = [];

// Query untuk tanggal tugas di bulan ini
$stmt_task_dates_month = $conn->prepare("SELECT DISTINCT DATE(deadline) as tanggal FROM tugas WHERE user_id = ? AND deadline BETWEEN ? AND ?");
if ($stmt_task_dates_month) {
    $stmt_task_dates_month->bind_param("iss", $user_id, $month_start_full, $month_end_full);
    $stmt_task_dates_month->execute();
    $task_dates_result_month = $stmt_task_dates_month->get_result();
    while($row = $task_dates_result_month->fetch_assoc()) {
        $dates_with_activity[] = $row['tanggal'];
    }
    $stmt_task_dates_month->close();
} else {
    error_log("Error preparing monthly task dates query: " . $conn->error);
}

// Query untuk tanggal acara di bulan ini
$stmt_event_dates_month = $conn->prepare("SELECT DISTINCT tanggal FROM acara WHERE user_id = ? AND tanggal BETWEEN ? AND ?");
if ($stmt_event_dates_month) {
    $date_month_start_only = date('Y-m-d', strtotime($month_start_full));
    $date_month_end_only = date('Y-m-d', strtotime($month_end_full));
    $stmt_event_dates_month->bind_param("iss", $user_id, $date_month_start_only, $date_month_end_only);
    $stmt_event_dates_month->execute();
    $event_dates_result_month = $stmt_event_dates_month->get_result();
    while($row = $event_dates_result_month->fetch_assoc()) {
        $dates_with_activity[] = $row['tanggal'];
    }
    $stmt_event_dates_month->close();
} else {
    error_log("Error preparing monthly event dates query: " . $conn->error);
}

$dates_with_activity = array_unique($dates_with_activity);


// === DATA UNTUK DAFTAR TUGAS & ACARA LENGKAP DI SIDEBAR KANAN ===
$all_tasks = [];
$stmt_all_tasks = $conn->prepare("SELECT id, nama_tugas, deskripsi, deadline, status FROM tugas WHERE user_id = ? ORDER BY deadline ASC");
if ($stmt_all_tasks) {
    $stmt_all_tasks->bind_param("i", $user_id);
    $stmt_all_tasks->execute();
    $all_tasks_result = $stmt_all_tasks->get_result();
    while ($t = $all_tasks_result->fetch_assoc()) {
        $all_tasks[] = $t;
    }
    $stmt_all_tasks->close();
} else {
    error_log("Error preparing all tasks query: " . $conn->error);
}

$all_events = [];
$stmt_all_events = $conn->prepare("SELECT id, nama_acara, deskripsi, tanggal FROM acara WHERE user_id = ? ORDER BY tanggal ASC");
if ($stmt_all_events) {
    $stmt_all_events->bind_param("i", $user_id);
    $stmt_all_events->execute();
    $all_events_result = $stmt_all_events->get_result();
    while ($a = $all_events_result->fetch_assoc()) {
        $all_events[] = $a;
    }
    $stmt_all_events->close();
} else {
    error_log("Error preparing all events query: " . $conn->error);
}

// --- History Data ---
$completed_tasks = [];
$stmt_completed_tasks = $conn->prepare("SELECT id, nama_tugas, deskripsi, deadline, status FROM tugas WHERE user_id = ? AND status = 'selesai' ORDER BY deadline DESC");
if ($stmt_completed_tasks) {
    $stmt_completed_tasks->bind_param("i", $user_id);
    $stmt_completed_tasks->execute();
    $completed_tasks_result = $stmt_completed_tasks->get_result();
    while ($t = $completed_tasks_result->fetch_assoc()) {
        $completed_tasks[] = $t;
    }
    $stmt_completed_tasks->close();
} else {
    error_log("Error preparing completed tasks query: " . $conn->error);
}

$past_events = [];
$stmt_past_events = $conn->prepare("SELECT id, nama_acara, deskripsi, tanggal FROM acara WHERE user_id = ? AND tanggal < CURDATE() ORDER BY tanggal DESC");
if ($stmt_past_events) {
    $stmt_past_events->bind_param("i", $user_id);
    $stmt_past_events->execute();
    $past_events_result = $stmt_past_events->get_result();
    while ($a = $past_events_result->fetch_assoc()) {
        $past_events[] = $a;
    }
    $stmt_past_events->close();
} else {
    error_log("Error preparing past events query: " . $conn->error);
}


// Proses tambah tugas
$err_tugas = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tugas'])) {
    $nama = trim($_POST['nama_tugas'] ?? '');
    $desk = trim($_POST['deskripsi_tugas'] ?? '');
    $deadline = $_POST['deadline_tugas'] ?? '';

    if (empty($nama) || empty($deadline)) {
        $err_tugas = 'Nama tugas & deadline wajib diisi!';
    } else {
        $stmt = $conn->prepare('INSERT INTO tugas (user_id, nama_tugas, deskripsi, deadline, status, created_at) VALUES (?, ?, ?, ?, "belum", NOW())');
        if ($stmt) {
            $stmt->bind_param('isss', $user_id, $nama, $desk, $deadline);
            if ($stmt->execute()) {
                header('Location: dashboard.php?display_month=' . $display_month . '&display_year=' . $display_year); exit;
            } else {
                $err_tugas = 'Gagal menambah tugas: ' . $stmt->error;
                error_log("Error adding task: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $err_tugas = 'Gagal menyiapkan query tambah tugas: ' . $conn->error;
            error_log("Error preparing add task query: " . $conn->error);
        }
    }
}

// PROSES UPDATE STATUS TUGAS (MELALUI AJAX/FORM)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id_toggle_status'])) {
    $task_id = $_POST['task_id_toggle_status'];
    $current_status = $_POST['current_status'];
    $new_status = ($current_status === 'selesai') ? 'belum' : 'selesai';

    $stmt = $conn->prepare("UPDATE tugas SET status = ? WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("sii", $new_status, $task_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'new_status' => $new_status]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update status: ' . $stmt->error]);
            error_log("Error updating task status: " . $stmt->error);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query update status: ' . $conn->error]);
        error_log("Error preparing update status query: " . $conn->error);
    }
    exit; // Penting untuk menghentikan eksekusi setelah AJAX
}

// PROSES DELETE TUGAS (Termasuk dari history)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task_id'])) {
    $task_id = $_POST['delete_task_id'];
    
    $stmt = $conn->prepare("DELETE FROM tugas WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $task_id, $user_id);
        if ($stmt->execute()) {
            header('Location: dashboard.php?display_month=' . $display_month . '&display_year=' . $display_year);
            exit;
        } else {
            error_log("Error deleting task: " . $stmt->error);
            // Anda bisa menampilkan pesan error ke user jika diperlukan
        }
        $stmt->close();
    } else {
        error_log("Error preparing delete task query: " . $conn->error);
    }
}

// PROSES EDIT TUGAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task_id'])) {
    $task_id = $_POST['edit_task_id'];
    $nama = trim($_POST['edit_nama_tugas'] ?? '');
    $desk = trim($_POST['edit_deskripsi_tugas'] ?? '');
    $deadline = $_POST['edit_deadline_tugas'] ?? '';

    if (empty($nama) || empty($deadline)) {
        $_SESSION['error_message'] = 'Nama tugas & deadline wajib diisi!'; // Set session for one-time display
    } else {
        $stmt = $conn->prepare("UPDATE tugas SET nama_tugas = ?, deskripsi = ?, deadline = ? WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("sssii", $nama, $desk, $deadline, $task_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Tugas berhasil diperbarui!'; // Set session for one-time display
            } else {
                error_log("Error updating task: " . $stmt->error);
                $_SESSION['error_message'] = 'Gagal memperbarui tugas: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            error_log("Error preparing update task query: " . $conn->error);
            $_SESSION['error_message'] = 'Gagal menyiapkan query update tugas: ' . $conn->error;
        }
    }
    header('Location: dashboard.php?display_month=' . $display_month . '&display_year=' . $display_year);
    exit;
}


// Proses tambah acara
$err_acara = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_acara'])) {
    $nama = trim($_POST['nama_acara'] ?? '');
    $desk = trim($_POST['deskripsi_acara'] ?? '');
    $tanggal = $_POST['tanggal_acara'] ?? '';

    if (empty($nama) || empty($tanggal)) {
        $err_acara = 'Nama acara & tanggal wajib diisi!';
    } else {
        $stmt = $conn->prepare('INSERT INTO acara (user_id, nama_acara, deskripsi, tanggal, created_at) VALUES (?, ?, ?, ?, NOW())');
        if ($stmt) {
            $stmt->bind_param('isss', $user_id, $nama, $desk, $tanggal);
            if ($stmt->execute()) {
                header('Location: dashboard.php?display_month=' . $display_month . '&display_year=' . $display_year);
                exit;
            } else {
                $err_acara = 'Gagal menambah acara: ' . $stmt->error;
                error_log("Error adding event: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $err_acara = 'Gagal menyiapkan query tambah acara: ' . $conn->error;
            error_log("Error preparing add event query: " . $conn->error);
        }
    }
}

// PROSES DELETE ACARA (Termasuk dari history)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    $event_id = $_POST['delete_event_id'];
    
    $stmt = $conn->prepare("DELETE FROM acara WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $event_id, $user_id);
        if ($stmt->execute()) {
            header('Location: dashboard.php?display_month=' . $display_month . '&display_year=' . $display_year);
            exit;
        } else {
            error_log("Error deleting event: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Error preparing delete event query: " . $conn->error);
    }
}

// PROSES EDIT ACARA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event_id'])) {
    $event_id = $_POST['edit_event_id'];
    $nama = trim($_POST['edit_nama_acara'] ?? '');
    $desk = trim($_POST['edit_deskripsi_acara'] ?? '');
    $tanggal = $_POST['edit_tanggal_acara'] ?? '';

    if (empty($nama) || empty($tanggal)) {
        $_SESSION['error_message'] = 'Nama acara & tanggal wajib diisi!';
    } else {
        $stmt = $conn->prepare("UPDATE acara SET nama_acara = ?, deskripsi = ?, tanggal = ? WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("sssii", $nama, $desk, $tanggal, $event_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Acara berhasil diperbarui!';
            } else {
                error_log("Error updating event: " . $stmt->error);
                $_SESSION['error_message'] = 'Gagal memperbarui acara: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            error_log("Error preparing update event query: " . $conn->error);
            $_SESSION['error_message'] = 'Gagal menyiapkan query update acara: ' . $conn->error;
        }
    }
    header('Location: dashboard.php?display_month=' . $display_month . '&display_year=' . $display_year);
    exit;
}


// Proses upload avatar
$err_avatar = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
  $file = $_FILES['avatar'];
  if ($file['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($ext, $allowed)) {
      $newName = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
      $uploadPath = __DIR__ . '/../uploads/' . $newName;

      if (!is_dir(__DIR__ . '/../uploads')) {
          mkdir(__DIR__ . '/../uploads', 0777, true);
      }

      if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        if (!empty($user_data['avatar']) && file_exists(__DIR__ . '/../uploads/' . $user_data['avatar'])) {
            @unlink(__DIR__ . '/../uploads/' . $user_data['avatar']);
        }

        $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        if ($stmt === false) {
            error_log("Error preparing avatar update query: " . $conn->error);
            $err_avatar = 'Terjadi kesalahan sistem saat upload.';
        } else {
            $stmt->bind_param('si', $newName, $user_id);
            if (!$stmt->execute()) {
                $err_avatar = 'Gagal update avatar: ' . $stmt->error;
                error_log("Error updating avatar: " . $stmt->error);
            } else {
                $user_data['avatar'] = $newName; // Update array user_data
                header('Location: dashboard.php?display_month=' . $display_month . '&display_year=' . $display_year);
                exit;
            }
            $stmt->close();
        }
      } else {
        $err_avatar = 'Gagal upload file. Pastikan folder uploads/ writeable.';
      }
    } else {
      $err_avatar = 'Format file tidak didukung. Hanya jpg, jpeg, png, gif.';
    }
  } else {
    $err_avatar = 'Upload error: ' . $file['error'];
  }
}

// Proses hapus avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_avatar'])) {
  if (!empty($user_data['avatar'])) {
    $avatarFile = __DIR__ . '/../uploads/' . $user_data['avatar'];
    if (file_exists($avatarFile)) {
      @unlink($avatarFile);
    }
    $stmt = $conn->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
    if ($stmt === false) {
        error_log("Error preparing delete avatar query: " . $conn->error);
    } else {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }
    $user_data['avatar'] = null; // Update array user_data
  }
  header('Location: dashboard.php?display_month=' . $display_month . '&display_year=' . $display_year);
  exit;
}

// Tampilkan avatar user jika ada, jika tidak tampilkan inisial
$avatar_path = (!empty($user_data['avatar']) && file_exists(__DIR__ . '/../uploads/' . $user_data['avatar']))
  ? '../uploads/' . $user_data['avatar']
  : 'profile_image.php?name=' . urlencode($user_data['username']); // Menggunakan $user_data['username']


// Motivasi harian
$motivasi = [
  'Jangan tunda pekerjaan, sukses dimulai dari langkah kecil hari ini!',
  'Fokus pada proses, bukan hasil. Setiap tugas yang selesai adalah kemenangan.',
  'Kamu lebih kuat dari rasa malasmu. Yuk, selesaikan satu tugas lagi!',
  'Waktu terbaik untuk memulai adalah sekarang. Semangat!',
  'Setiap hari adalah kesempatan baru untuk jadi lebih baik.',
  'Disiplin adalah kunci untuk meraih impian. Mulai hari ini!',
  'Kecil tapi rutin, lebih baik daripada besar tapi tak pernah.'
];
$mot_today = $motivasi[date('z') % count(array_keys($motivasi))]; // Perbaikan: Gunakan array_keys untuk count agar lebih robust


// Statistik Ringkasan
$total_tasks = 0; $done_tasks = 0; $total_events = 0;
$total_tasks_query = $conn->prepare("SELECT COUNT(*) as jml FROM tugas WHERE user_id = ?");
if ($total_tasks_query === false) { error_log("Error preparing total tasks query: " . $conn->error); } else {
    $total_tasks_query->bind_param("i", $user_id);
    $total_tasks_query->execute();
    $total_tasks = $total_tasks_query->get_result()->fetch_assoc()['jml'];
    $total_tasks_query->close();
}

$done_tasks_query = $conn->prepare("SELECT COUNT(*) as jml FROM tugas WHERE user_id = ? AND status = 'selesai'");
if ($done_tasks_query === false) { error_log("Error preparing done tasks query: " . $conn->error); } else {
    $done_tasks_query->bind_param("i", $user_id);
    $done_tasks_query->execute();
    $done_tasks = $done_tasks_query->get_result()->fetch_assoc()['jml'];
    $done_tasks_query->close();
}

$total_events_query = $conn->prepare("SELECT COUNT(*) as jml FROM acara WHERE user_id = ?");
if ($total_events_query === false) { error_log("Error preparing total events query: " . $conn->error); } else {
    $total_events_query->bind_param("i", $user_id);
    $total_events_query->execute();
    $total_events = $total_events_query->get_result()->fetch_assoc()['jml'];
    $total_events_query->close();
}

$progress_percentage = $total_tasks ? round($done_tasks / $total_tasks * 100) : 0;

// Ambil dan hapus pesan session jika ada (untuk notifikasi setelah redirect)
$success_message = $_SESSION['success_message'] ?? '';
$error_message_session = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Fokus & Selesai</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --background-light: #f0f2f5;
            --card-background: #ffffff;
            --text-color-dark: #333;
            --text-color-light: #666;
            --border-color: #e9ecef;
            --shadow-light: 0 5px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 10px 30px rgba(0,0,0,0.15);
            --success-color: #28a745;
            --warning-color: #ffc107; /* Warna oranye/kuning untuk warning */
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --primary-color-rgb: 0, 123, 255;
            --danger-color-rgb: 220, 53, 69;
        }

        body {
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--background-light);
            padding-top: 70px; /* Space for fixed navbar */
            transition: background-color 0.3s ease;
        }

        /* Dark Theme */
        body.theme-dark {
            --primary-color: #4a90e2; /* Softer blue for dark theme */
            --secondary-color: #b0b8c0;
            --background-light: #2c3e50; /* Darker background */
            --card-background: #34495e; /* Darker card background */
            --text-color-dark: #ecf0f1;
            --text-color-light: #bdc3c7;
            --border-color: #444;
            --shadow-light: 0 5px 15px rgba(0,0,0,0.3);
            --shadow-medium: 0 10px 30px rgba(0,0,0,0.4);
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --primary-color-rgb: 74, 144, 226;
            --danger-color-rgb: 231, 76, 60;
        }
        body.theme-dark .navbar { background-color: #2c3e50 !important; }
        body.theme-dark .navbar-brand, body.theme-dark .nav-link, body.theme-dark .dropdown-toggle { color: #ecf0f1 !important; }
        body.theme-dark .welcome-section { background: linear-gradient(45deg, #34495e, #4a6a8a); }
        body.theme-dark .card, body.theme-dark .calendar-edlink { background-color: var(--card-background); box-shadow: var(--shadow-medium); }
        body.theme-dark .card-header { background-color: #3f5870; color: var(--text-color-dark); border-bottom-color: var(--border-color); }
        body.theme-dark .task-item, body.theme-dark .event-item { border-bottom-color: var(--border-color); }
        body.theme-dark .task-item h5, body.theme-dark .event-item h5 { color: var(--text-color-dark); }
        body.theme-dark .task-item p, body.theme-dark .event-item p { color: var(--text-color-light); }
        body.theme-dark .form-control { background-color: #3f5870; color: var(--text-color-dark); border-color: var(--border-color); }
        body.theme-dark .form-control::placeholder { color: var(--text-color-light); }
        body.theme-dark .form-control:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25); }
        body.theme-dark .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        body.theme-dark .btn-primary:hover { background-color: #3a80ce; border-color: #3a80ce; }
        body.theme-dark .btn-success { background-color: var(--success-color); border-color: var(--success-color); }
        body.theme-dark .btn-success:hover { background-color: #21a65f; border-color: #21a65f; }
        body.theme-dark .btn-info { background-color: var(--info-color); border-color: var(--info-color); }
        body.theme-dark .btn-info:hover { background-color: #258cd1; border-color: #258cd1; }
        body.theme-dark .modal-content { background-color: var(--card-background); color: var(--text-color-dark); }
        body.theme-dark .modal-header { background-color: var(--primary-color); color: #ecf0f1; }
        body.theme-dark .modal-header .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        body.theme-dark .dropdown-menu { background-color: var(--card-background); border-color: var(--border-color); }
        body.theme-dark .dropdown-item { color: var(--text-color-dark); }
        body.theme-dark .dropdown-item:hover { background-color: #4a6a8a; color: #ecf0f1; }
        body.theme-dark .dropdown-divider { border-top-color: var(--border-color); }
        body.theme-dark .list-group-item { background-color: var(--card-background); color: var(--text-color-dark); border-color: var(--border-color); }


        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: background-color 0.3s ease;
        }
        .navbar-brand {
            font-weight: 700;
            color: #ffffff !important;
        }
        .welcome-section {
            background: linear-gradient(45deg, var(--primary-color), #00c6ff);
            color: white;
            padding: 50px 0;
            margin-bottom: 30px;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            animation: fadeInDown 0.8s ease-out; /* Animasi section welcome */
        }
        .welcome-section h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        }
        .welcome-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease-out; /* Animasi motivasi */
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: var(--shadow-light);
            margin-bottom: 25px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeIn 0.8s ease-out; /* Animasi card */
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        .card-header {
            background-color: var(--border-color);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--text-color-dark);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header .btn {
            border-radius: 8px;
            transition: transform 0.2s ease;
        }
        .card-header .btn:hover {
            transform: scale(1.05);
        }

        .task-item, .event-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start; /* Align items to the start to allow text wrapping */
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            animation: fadeIn 0.5s ease-out; /* Fade-in for list items */
        }
        .task-item:last-child, .event-item:last-child {
            border-bottom: none;
        }
        .task-item-content, .event-item-content {
            flex-grow: 1;
            flex-shrink: 1; /* Allow content to shrink */
            margin-right: 15px; /* Space between content and actions */
            word-wrap: break-word; /* Ensure long words break */
            overflow-wrap: break-word; /* Modern equivalent */
        }
        .task-item-actions, .event-item-actions {
            flex-shrink: 0; /* Prevent actions from shrinking */
            margin-left: auto; /* Push actions to the right */
            white-space: nowrap; /* Keep buttons on one line (if space allows) */
            display: flex; /* Make buttons flex container */
            flex-direction: column; /* Stack buttons vertically */
            align-items: flex-end; /* Align buttons to the right within their column */
        }
        /* Style for history delete button alignment */
        .history-item-actions {
            flex-shrink: 0;
            margin-left: 10px; /* Adjust spacing */
            align-self: center; /* Vertically center the button in history item */
        }

        .task-item h5, .event-item h5 {
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-color-dark);
        }
        .task-item p, .event-item p {
            margin-bottom: 0;
            font-size: 0.9rem;
            color: var(--text-color-light);
        }
        .status-badge {
            font-size: 0.75em;
            padding: 0.4em 0.8em;
            border-radius: 0.375rem;
            font-weight: 600;
            display: inline-block; /* Ensure it wraps correctly */
            margin-top: 5px;
        }
        .status-selesai { background-color: var(--success-color); color: white; }
        .status-belum { background-color: var(--danger-color); color: white; }
        .status-sedang-dikerjakan { background-color: var(--warning-color); color: var(--text-color-dark); }

        .btn-action {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 5px;
            margin-left: 5px;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
        }
        .btn-warning { background-color: var(--warning-color); border-color: var(--warning-color); color: var(--text-color-dark); }
        .btn-danger { background-color: var(--danger-color); border-color: var(--danger-color); }
        .btn-success { background-color: var(--success-color); border-color: var(--success-color); }
        .btn-info { background-color: var(--info-color); border-color: var(--info-color); }
        .no-items-message {
            text-align: center;
            padding: 30px;
            color: var(--text-color-light);
            font-style: italic;
        }

        /* Modal styling */
        .modal-content {
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            background-color: var(--card-background);
            color: var(--text-color-dark);
        }
        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            border-bottom: none;
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        /* Calendar specific styles */
        /* Menggunakan kelas dari kode Anda sebelumnya: calendar-edlink */
        .calendar-edlink {
            background-color: var(--card-background);
            border-radius: 15px;
            box-shadow: var(--shadow-light);
            padding: 20px;
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeIn 0.8s ease-out; /* Animasi card */
        }
        .calendar-edlink .weekdays {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            color: var(--text-color-dark); /* FIX: Pastikan warna gelap di mode terang */
            margin-bottom: 10px;
        }
        /* FIX: Tambahan untuk memastikan teks hari ini dan judul kalender selalu terlihat */
        .calendar-edlink h4,
        .calendar-edlink .text-secondary {
            color: var(--text-color-dark); /* FIX: Memastikan teks ini gelap di mode terang */
        }
        body.theme-dark .calendar-edlink h4,
        body.theme-dark .calendar-edlink .weekdays span,
        body.theme-dark .calendar-edlink .text-secondary {
            color: var(--text-color-dark) !important; /* FIX: Memastikan teks ini terang di mode gelap */
        }

        .calendar-edlink .days {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            flex-wrap: wrap; /* Allow days to wrap to the next line */
        }
        .calendar-edlink .day {
            text-align: center;
            width: 36px;
            height: 36px;
            line-height: 36px;
            border-radius: 50%;
            position: relative;
            font-weight: 500;
            cursor: pointer;
            color: var(--text-color-dark); /* FIX: Default text color for day is dark */
            transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
            text-decoration: none; /* Remove underline from links */
            margin: 2px; /* Small margin for spacing between days */
        }
        .calendar-edlink .day:hover {
            background-color: rgba(var(--primary-color-rgb), 0.1);
            transform: translateY(-2px);
        }
        .calendar-edlink .day.today {
            background: var(--primary-color);
            color: white;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(var(--primary-color-rgb), 0.3);
        }
        .calendar-edlink .day.border-primary {
            border: 2px solid var(--primary-color) !important;
        }
        .calendar-edlink .day.other-month {
            opacity: 0.5; /* Fade days from other months */
        }
        .calendar-edlink .day .dot {
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--warning-color); /* Oranye seperti contoh Anda */
        }

        .daily-schedule-card {
            margin-top: 20px;
        }
        .schedule-item {
            background-color: var(--background-light); /* Lighter background for schedule items */
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .schedule-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .schedule-item h6 {
            font-weight: 600;
            color: var(--text-color-dark);
            margin-bottom: 5px;
        }
        .schedule-item p {
            font-size: 0.85rem;
            color: var(--text-color-light);
            margin-bottom: 0;
        }
        .schedule-item .badge {
            font-size: 0.7em;
            padding: 0.3em 0.6em;
            margin-right: 5px;
        }

        /* Profile image and dropdown */
        .profile-img {
            width: 36px; /* Disesuaikan agar pas di navbar */
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            transition: transform 0.3s ease;
        }
        .dropdown-toggle .profile-img {
            margin-right: 8px;
        }
        .dropdown-toggle:hover .profile-img {
            transform: scale(1.1);
        }
        .dropdown-item i {
            width: 20px; /* Align icons */
            text-align: center;
            margin-right: 5px;
        }

        /* Notifikasi */
        .notification-bell {
            position: relative;
            cursor: pointer;
            font-size: 1.2rem;
            color: white;
            transition: transform 0.3s ease;
        }
        .notification-bell:hover {
            transform: scale(1.1);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger-color);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            animation: pulse 1.5s infinite;
            z-index: 100; /* Pastikan di atas elemen lain */
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(var(--danger-color-rgb), 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(var(--danger-color-rgb), 0); }
            100% { box-shadow: 0 0 0 0 rgba(var(--danger-color-rgb), 0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animated-icon {
            transition: transform 0.3s ease;
        }
        .animated-icon:hover {
            transform: rotate(15deg);
        }

        /* Statistik progress circle */
        .progress-circle-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
        }
        .progress-circle-bg, .progress-circle-fg {
            fill: none;
            stroke-width: 10;
        }
        .progress-circle-bg {
            stroke: var(--border-color);
        }
        .progress-circle-fg {
            stroke: var(--primary-color);
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dashoffset 1s ease-out;
        }
        .progress-circle-text {
            font-size: 1.8rem;
            font-weight: 700;
            fill: var(--primary-color);
        }
        /* Ensure primary-color-rgb is defined for rgba usage */
        body:not(.theme-dark) { --primary-color-rgb: 0, 123, 255; --danger-color-rgb: 220, 53, 69; }
        body.theme-dark { --primary-color-rgb: 74, 144, 226; --danger-color-rgb: 231, 76, 60; }

        .list-group-item:hover {
            background-color: rgba(0, 123, 255, 0.05); /* Softer hover for checklist */
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
        }

        /* Styles from user's provided code, integrated and harmonized */
        .card-task, .card-event, .shadow-sm {
            box-shadow: var(--shadow-light) !important; /* Ensure consistency */
        }
        .card-task:hover, .card-event:hover, .list-group-item:hover, .btn:hover {
            box-shadow: 0 2px 12px rgba(var(--primary-color-rgb), 0.2) !important; /* Consistent hover shadow */
            transform: translateY(-2px) scale(1.03);
            transition: 0.2s;
        }
        .avatar-upload { display:flex; align-items:center; gap:10px; }
        .avatar-upload input[type=file] { display:none; }
        .avatar-upload label { cursor:pointer; color:var(--primary-color); text-decoration:underline; }
        .theme-dark .avatar-upload label { color: var(--primary-color); } /* Dark theme adjustment */

        /* Checkbox styling (kustom) */
        .task-checkbox {
            width: 20px;
            height: 20px;
            min-width: 20px; /* Ensures it doesn't shrink */
            margin-right: 15px;
            cursor: pointer;
            position: relative;
            appearance: none; /* Hide default checkbox */
            border: 2px solid var(--primary-color);
            border-radius: 5px;
            transition: all 0.2s ease;
        }
        .task-checkbox:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        .task-checkbox:checked::before {
            content: "\f00c"; /* FontAwesome check icon */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
        }
        .task-checkbox:focus {
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
        }


        @media (min-width: 992px) {
            .sidebar-col {
                position: sticky;
                top: 80px; /* Adjusted for fixed navbar height */
                height: calc(100vh - 100px); /* Fill remaining viewport height */
                overflow-y: auto; /* Allow scrolling within sidebar if content is long */
                padding-bottom: 20px; /* Add some padding at the bottom */
            }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fa-solid fa-calendar-check animated-icon me-2"></i> Fokus & Selesai</a>
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-link text-white" id="themeToggle" title="Ganti Tema">
        <i class="fa-solid fa-moon"></i>
      </button>
      <div class="dropdown">
        <button class="btn position-relative" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="color:white;">
          <i class="fa-solid fa-bell fa-lg"></i>
          <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">!</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown" style="min-width:260px;">
          <li><span class="dropdown-item-text fw-bold">Notifikasi</span></li>
          <li><hr class="dropdown-divider"></li>
          <li><div id="notif-area" class="small text-muted px-3">Tidak ada notifikasi baru.</div></li>
        </ul>
      </div>
      <div class="dropdown">
        <button class="btn d-flex align-items-center gap-2 text-white" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background:transparent;">
          <img id="avatar-img" src="<?= $avatar_path ?>" class="profile-img" alt="Foto Profil">
          <span class="fw-semibold"><i class="fa-solid fa-user me-1"></i> <?= esc($username) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
          <li><h6 class="dropdown-header">Profil Pengguna</h6></li>
          <li>
              <form class="px-3 py-2 avatar-upload" enctype="multipart/form-data" method="post" id="avatarForm">
                  <label for="avatarfile" class="d-block w-100"><i class="fa-solid fa-image me-2"></i> Ganti Foto Profil</label>
                  <input type="file" id="avatarfile" name="avatar" accept="image/*" class="d-none">
              </form>
          </li>
          <li>
              <form method="post" class="px-3 py-2">
                  <input type="hidden" name="delete_avatar" value="1">
                  <button type="submit" class="btn btn-link text-danger p-0" style="text-decoration:underline; font-size:0.9em;"><i class="fa-solid fa-trash me-2"></i> Hapus Foto Profil</button>
              </form>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li><button class="dropdown-item" id="showStatsBtn" type="button"><i class="fa-solid fa-chart-bar me-2"></i> Statistik Mingguan</button></li>
          <?php if (get_user_id() == 1): // Tampilkan hanya jika user adalah admin (user_id = 1) ?>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="admin_users.php"><i class="fa-solid fa-users-gear me-2"></i> Halaman Admin</a></li>
          <?php endif; ?>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="welcome-section text-center">
    <div class="container animate__animated animate__fadeInDown">
        <h1>Halo, <?= esc($username) ?>!</h1>
        <p class="animate__animated animate__fadeInUp"><?= esc($mot_today) ?></p>
    </div>
</div>

<div class="container-fluid py-4">
  <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
        <?= esc($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if ($error_message_session): ?>
    <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
        <?= esc($error_message_session) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-3 sidebar-col">
      <div class="calendar-edlink mb-4 animate__animated animate__fadeInLeft">
        <div class="mb-3 p-2 rounded bg-gradient" style="background:linear-gradient(90deg, var(--primary-color) 60%,#6dd5ed 100%);color:#fff;box-shadow:0 2px 8px rgba(var(--primary-color-rgb),0.2);">
          <i class="fa-solid fa-quote-left"></i> <span class="fw-semibold"><?= $mot_today ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <a href="<?= $prev_month_link ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-chevron-left"></i></a>
            <span class="fw-bold fs-5"><?= $display_date_obj->format('F Y') ?></span>
            <a href="<?= $next_month_link ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <div class="weekdays">
          <?php $hari = ['Sen','Sel','Rab','Kam','Jum','Sab','Min']; foreach($hari as $h) echo '<span>'.$h.'</span>'; ?>
        </div>
        <div class="days mt-2">
          <?php
          $current_day_in_loop = clone $calendar_start_date_obj;
          // Loop for 6 weeks (42 days) to cover the entire month generously
          for($i=0;$i<42;$i++) {
            $dateStr = $current_day_in_loop->format('Y-m-d');
            $dayNum = $current_day_in_loop->format('d');
            $isToday = $dateStr === $today_str;
            $isInDisplayMonth = $current_day_in_loop->format('m') == $display_month && $current_day_in_loop->format('Y') == $display_year;
            
            $dot_html = '';
            if (in_array($dateStr, $dates_with_activity)) {
                $dot_html = '<span class="dot"></span>';
            }

            echo '<a href="?display_month=' . $display_month . '&display_year=' . $display_year . '&tgl=' . $dateStr . '" style="text-decoration:none;">';
            echo '<div class="day'.($isToday?' today':'').($isInDisplayMonth ? '' : ' other-month') .'">';
            echo $dayNum . $dot_html;
            echo '</div>';
            echo '</a>';
            $current_day_in_loop->modify('+1 day');
          }
          ?>
        </div>
      </div>
      <div class="card animate__animated animate__fadeInLeft">
          <div class="card-header">
              <span><i class="fa-solid fa-chart-simple me-2"></i> Statistik Umum</span>
          </div>
          <div class="card-body text-center">
              <div class="progress-circle-container">
                  <svg class="progress-circle" viewBox="0 0 36 36">
                      <path class="progress-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                      <path class="progress-circle-fg" stroke-dasharray="<?= $progress_percentage ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                      <text x="18" y="20.35" class="progress-circle-text"><?= $progress_percentage ?>%</text>
                  </svg>
              </div>
              <p>Total Tugas: <span class="fw-bold text-primary"><?= $total_tasks ?></span></p>
              <p>Tugas Selesai: <span class="fw-bold text-success"><?= $done_tasks ?></span></p>
              <p>Total Acara: <span class="fw-bold text-info"><?= $total_events ?></span></p>
          </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card animate__animated animate__fadeInUp">
        <div class="card-header">
            <span><i class="fa-solid fa-plus-circle me-2"></i> Tambah Tugas Baru</span>
        </div>
        <div class="card-body">
            <?php if ($err_tugas): ?><div class="alert alert-danger mb-3 animate__animated animate__fadeInDown"><?= esc($err_tugas) ?></div><?php endif; ?>
            <form method="post" class="row g-3">
              <div class="col-md-6">
                <label for="nama_tugas_input" class="form-label">Nama Tugas</label>
                <input type="text" name="nama_tugas" id="nama_tugas_input" class="form-control" placeholder="Tulis nama tugas..." required>
              </div>
              <div class="col-md-6">
                <label for="deadline_tugas_input" class="form-label">Deadline</label>
                <input type="datetime-local" name="deadline_tugas" id="deadline_tugas_input" class="form-control" required>
              </div>
              <div class="col-12">
                <label for="deskripsi_tugas_input" class="form-label">Deskripsi</label>
                <textarea name="deskripsi_tugas" id="deskripsi_tugas_input" class="form-control" rows="2" placeholder="Deskripsi (opsional)"></textarea>
              </div>
              <div class="col-12">
                <button type="submit" name="add_tugas" class="btn btn-primary w-100"><i class="fa-solid fa-calendar-plus me-2"></i> Tambah Tugas</button>
              </div>
            </form>
        </div>
      </div>
      <div class="card animate__animated animate__fadeInUp">
        <div class="card-header">
            <span><i class="fa-solid fa-calendar-plus me-2"></i> Tambah Acara Baru</span>
        </div>
        <div class="card-body">
            <?php if ($err_acara): ?><div class="alert alert-danger mb-3 animate__animated animate__fadeInDown"><?= esc($err_acara) ?></div><?php endif; ?>
            <form method="post" class="row g-3">
              <div class="col-md-6">
                <label for="nama_acara_input" class="form-label">Nama Acara</label>
                <input type="text" name="nama_acara" id="nama_acara_input" class="form-control" placeholder="Tulis nama acara..." required>
              </div>
              <div class="col-md-6">
                <label for="tanggal_acara_input" class="form-label">Tanggal Acara</label>
                <input type="date" name="tanggal_acara" id="tanggal_acara_input" class="form-control" required>
              </div>
              <div class="col-12">
                <label for="deskripsi_acara_input" class="form-label">Deskripsi</label>
                <textarea name="deskripsi_acara" id="deskripsi_acara_input" class="form-control" rows="2" placeholder="Deskripsi (opsional)"></textarea>
              </div>
              <div class="col-12">
                <button type="submit" name="add_acara" class="btn btn-success w-100"><i class="fa-solid fa-calendar-day me-2"></i> Tambah Acara</button>
              </div>
            </form>
        </div>
      </div>
    </div>
    <div class="col-lg-3 sidebar-col">
      <div class="card animate__animated animate__fadeInRight mb-4">
        <div class="card-header">
            <span><i class="fa-solid fa-clipboard-list me-2"></i> Catatan & Checklist</span>
        </div>
        <div class="card-body">
            <form id="noteForm" class="mb-3">
                <div class="input-group">
                    <input type="text" class="form-control" id="noteInput" placeholder="Tambah catatan/checklist...">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i></button>
                </div>
            </form>
            <ul class="list-group" id="noteList"></ul>
        </div>
      </div>

      <div class="card animate__animated animate__fadeInRight mb-4">
        <div class="card-header">
            <span><i class="fa-solid fa-tasks me-2"></i> Daftar Semua Tugas Anda</span>
        </div>
        <div class="card-body">
            <?php if (empty($all_tasks)): ?>
                <div class="text-muted small no-items-message">Tidak ada tugas yang tersedia.</div>
            <?php else: ?>
                <ul class="list-group">
                <?php foreach($all_tasks as $t): ?>
                    <?php
                    $is_urgent = ($t['status']==='belum' && strtotime($t['deadline']) >= time() && strtotime($t['deadline']) <= strtotime('+1 day'));
                    $is_late = ($t['status']==='belum' && strtotime($t['deadline']) < time());
                    ?>
                    <li class="list-group-item d-flex align-items-center shadow-sm mb-1 animate__animated animate__fadeInUp" style="border-radius:12px;<?= $is_urgent?'background:#fffbe6;':'' ?>">
                        <input type="checkbox" class="task-checkbox" data-task-id="<?= $t['id'] ?>" <?= $t['status'] === 'selesai' ? 'checked' : '' ?>>
                        <div class="task-item-content">
                            <h5 class="mb-0 <?= $t['status'] === 'selesai' ? 'text-decoration-line-through text-muted' : '' ?>"><?= esc($t['nama_tugas']) ?></h5>
                            <p class="small text-muted mb-1">
                                Deadline: <?= esc(tgl_indo_edlink(date('Y-m-d', strtotime($t['deadline'])))) ?>, Pukul <?= date('H:i', strtotime($t['deadline'])) ?>
                            </p>
                            <?php if (!empty($t['deskripsi'])): ?>
                                <p class="small"><?= esc($t['deskripsi']) ?></p>
                            <?php endif; ?>
                            <span class="status-badge status-<?= $t['status'] ?>"><?= $t['status']==='selesai'?'Selesai':($is_late?'Terlambat':($is_urgent?'Mendesak':'Belum')) ?></span>
                        </div>
                        <div class="task-item-actions d-flex flex-column align-items-end">
                            <button class="btn btn-sm btn-info mb-1 edit-task-btn" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editTaskModal"
                                    data-id="<?= $t['id'] ?>"
                                    data-nama="<?= esc($t['nama_tugas']) ?>"
                                    data-deskripsi="<?= esc($t['deskripsi']) ?>"
                                    data-deadline="<?= date('Y-m-d\TH:i', strtotime($t['deadline'])) ?>">
                                <i class="fa-solid fa-edit"></i> Edit
                            </button>
                            <form method="POST" action="dashboard.php?display_month=<?= $display_month ?>&display_year=<?= $display_year ?>" class="d-inline">
                                <input type="hidden" name="delete_task_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger delete-btn" onclick="return confirm('Yakin ingin menghapus tugas ini?');">
                                    <i class="fa-solid fa-trash-alt"></i> Hapus
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
      </div>

      <div class="card animate__animated animate__fadeInRight mb-4">
        <div class="card-header">
            <span><i class="fa-solid fa-calendar-alt me-2"></i> Daftar Semua Acara Anda</span>
        </div>
        <div class="card-body">
            <?php if (empty($all_events)): ?>
                <div class="text-muted small no-items-message">Tidak ada acara yang tersedia.</div>
            <?php else: ?>
                <ul class="list-group">
                <?php foreach($all_events as $a): ?>
                    <li class="list-group-item d-flex align-items-center shadow-sm mb-1 animate__animated animate__fadeInUp" style="border-radius:12px;">
                        <div class="event-item-content">
                            <h5 class="mb-0"><?= esc($a['nama_acara']) ?></h5>
                            <p class="small text-muted mb-1">Tanggal: <?= esc(tgl_indo_edlink($a['tanggal'])) ?></p>
                            <?php if (!empty($a['deskripsi'])): ?>
                                <p class="small"><?= esc($a['deskripsi']) ?></p>
                            <?php endif; ?>
                            <span class="badge bg-info text-white">Acara</span>
                        </div>
                        <div class="event-item-actions d-flex flex-column align-items-end">
                            <button class="btn btn-sm btn-info mb-1 edit-event-btn" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editEventModal"
                                    data-id="<?= $a['id'] ?>"
                                    data-nama="<?= esc($a['nama_acara']) ?>"
                                    data-deskripsi="<?= esc($a['deskripsi']) ?>"
                                    data-tanggal="<?= esc($a['tanggal']) ?>">
                                <i class="fa-solid fa-edit"></i> Edit
                            </button>
                            <form method="POST" action="dashboard.php?display_month=<?= $display_month ?>&display_year=<?= $display_year ?>" class="d-inline">
                                <input type="hidden" name="delete_event_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger delete-btn" onclick="return confirm('Yakin ingin menghapus acara ini?');">
                                    <i class="fa-solid fa-trash-alt"></i> Hapus
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
      </div>

      <div class="card animate__animated animate__fadeInRight mb-4">
        <div class="card-header">
            <span><i class="fa-solid fa-history me-2"></i> Riwayat Tugas & Acara Selesai</span>
        </div>
        <div class="card-body">
            <?php if (empty($completed_tasks) && empty($past_events)): ?>
                <div class="text-muted small no-items-message">Tidak ada riwayat tugas atau acara.</div>
            <?php else: ?>
                <h6 class="mt-2 mb-2">Tugas Selesai:</h6>
                <?php if (empty($completed_tasks)): ?>
                    <div class="text-muted small mb-3">Tidak ada tugas yang selesai.</div>
                <?php else: ?>
                    <ul class="list-group mb-3">
                        <?php foreach($completed_tasks as $t): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center shadow-sm mb-1 animate__animated animate__fadeInUp" style="border-radius:12px;">
                                <div class="task-item-content">
                                    <h5 class="mb-0 text-decoration-line-through text-muted"><?= esc($t['nama_tugas']) ?></h5>
                                    <p class="small text-muted mb-1">
                                        Selesai pada: <?= esc(tgl_indo_edlink(date('Y-m-d', strtotime($t['deadline'])))) ?>, Pukul <?= date('H:i', strtotime($t['deadline'])) ?>
                                    </p>
                                    <?php if (!empty($t['deskripsi'])): ?>
                                        <p class="small"><?= esc($t['deskripsi']) ?></p>
                                    <?php endif; ?>
                                    <span class="status-badge status-selesai">Selesai</span>
                                </div>
                                <div class="history-item-actions">
                                    <form method="POST" action="dashboard.php?display_month=<?= $display_month ?>&display_year=<?= $display_year ?>" class="d-inline">
                                        <input type="hidden" name="delete_task_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus tugas ini dari riwayat?');">
                                            <i class="fa-solid fa-trash-alt"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h6 class="mt-4 mb-2">Acara yang Telah Berlalu:</h6>
                <?php if (empty($past_events)): ?>
                    <div class="text-muted small">Tidak ada acara yang telah berlalu.</div>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach($past_events as $a): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center shadow-sm mb-1 animate__animated animate__fadeInUp" style="border-radius:12px;">
                                <div class="event-item-content">
                                    <h5 class="mb-0"><?= esc($a['nama_acara']) ?></h5>
                                    <p class="small text-muted mb-1">Tanggal: <?= esc(tgl_indo_edlink($a['tanggal'])) ?></p>
                                    <?php if (!empty($a['deskripsi'])): ?>
                                        <p class="small"><?= esc($a['deskripsi']) ?></p>
                                    <?php endif; ?>
                                    <span class="badge bg-secondary text-white">Terlaksana</span>
                                </div>
                                <div class="history-item-actions">
                                    <form method="POST" action="dashboard.php?display_month=<?= $display_month ?>&display_year=<?= $display_year ?>" class="d-inline">
                                        <input type="hidden" name="delete_event_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus acara ini dari riwayat?');">
                                            <i class="fa-solid fa-trash-alt"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editTaskModalLabel">Edit Tugas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editTaskForm" method="POST" action="dashboard.php?display_month=<?= $display_month ?>&display_year=<?= $display_year ?>">
        <div class="modal-body">
          <input type="hidden" name="edit_task_id" id="editTaskId">
          <div class="mb-3">
            <label for="editNamaTugas" class="form-label">Nama Tugas</label>
            <input type="text" class="form-control" id="editNamaTugas" name="edit_nama_tugas" required>
          </div>
          <div class="mb-3">
            <label for="editDeskripsiTugas" class="form-label">Deskripsi</label>
            <textarea class="form-control" id="editDeskripsiTugas" name="edit_deskripsi_tugas" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label for="editDeadlineTugas" class="form-label">Deadline</label>
            <input type="datetime-local" class="form-control" id="editDeadlineTugas" name="edit_deadline_tugas" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editEventModalLabel">Edit Acara</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editEventForm" method="POST" action="dashboard.php?display_month=<?= $display_month ?>&display_year=<?= $display_year ?>">
        <div class="modal-body">
          <input type="hidden" name="edit_event_id" id="editEventId">
          <div class="mb-3">
            <label for="editNamaAcara" class="form-label">Nama Acara</label>
            <input type="text" class="form-control" id="editNamaAcara" name="edit_nama_acara" required>
          </div>
          <div class="mb-3">
            <label for="editDeskripsiAcara" class="form-label">Deskripsi</label>
            <textarea class="form-control" id="editDeskripsiAcara" name="edit_deskripsi_acara" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label for="editTanggalAcara" class="form-label">Tanggal Acara</label>
            <input type="date" class="form-control" id="editTanggalAcara" name="edit_tanggal_acara" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="weeklyStatsModal" tabindex="-1" aria-labelledby="weeklyStatsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="weeklyStatsModalLabel"><i class="fa-solid fa-chart-bar me-2"></i> Statistik Mingguan Tugas Selesai</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="statsContent">
            <canvas id="weeklyChartModalCanvas" height="180"></canvas>
        </div>
      </div>
       <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    $(document).ready(function() { // Seluruh kode JS dibungkus di sini

        // Theme Switcher
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.onclick = function() {
                document.body.classList.toggle('theme-dark');
                localStorage.setItem('theme', document.body.classList.contains('theme-dark') ? 'dark' : 'light');
                themeToggle.querySelector('i').className = document.body.classList.contains('theme-dark') ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            };
            // Set theme on initial load
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('theme-dark');
                themeToggle.querySelector('i').className = 'fa-solid fa-sun';
            }
        }

        // Avatar Upload
        const avatarfile = document.getElementById('avatarfile');
        if (avatarfile) {
            avatarfile.onchange = function(e) {
                if (e.target.files.length > 0) {
                    document.getElementById('avatarForm').submit(); // Submit form when file is selected
                }
            };
        }

        // Checklist/Notes (uses Local Storage)
        const noteForm = document.getElementById('noteForm');
        const noteInput = document.getElementById('noteInput');
        const noteList = document.getElementById('noteList');

        function renderNotes() {
            noteList.innerHTML = '';
            let notes = JSON.parse(localStorage.getItem('notes') || '[]');
            if (notes.length === 0) {
                noteList.innerHTML = '<li class="list-group-item text-muted text-center">Belum ada catatan.</li>';
                return;
            }
            notes.forEach((n, i) => {
                let li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center animate__animated animate__fadeInUp';
                li.innerHTML = '<span>' + esc(n) + '</span>' +
                               '<button class="btn btn-sm btn-danger" onclick="removeNote(' + i + ')"><i class="fa-solid fa-trash-alt"></i></button>';
                noteList.appendChild(li);
            });
        }

        // Make removeNote globally accessible
        window.removeNote = function(i) {
            let notes = JSON.parse(localStorage.getItem('notes') || '[]');
            notes.splice(i, 1);
            localStorage.setItem('notes', JSON.stringify(notes));
            renderNotes();
        }

        if (noteForm) {
            noteForm.onsubmit = function(e) {
                e.preventDefault();
                let val = noteInput.value.trim();
                if (val) {
                    let notes = JSON.parse(localStorage.getItem('notes') || '[]');
                    notes.push(val);
                    localStorage.setItem('notes', JSON.stringify(notes));
                    noteInput.value = '';
                    renderNotes();
                }
            };
            renderNotes(); // Initial render
        }

        // Statistik Mingguan di Modal (uses Chart.js)
        let weeklyChartModal = null;
        const weeklyStatsModalElement = document.getElementById('weeklyStatsModal');

        if (weeklyStatsModalElement) {
            document.getElementById('showStatsBtn').addEventListener('click', function() {
                // Saat tombol diklik, pastikan modal tampil, lalu muat data
                // Menggunakan Bootstrap Modal JS API untuk membuka modal
                var myModal = new bootstrap.Modal(weeklyStatsModalElement);
                myModal.show();
            });

            weeklyStatsModalElement.addEventListener('shown.bs.modal', function() {
                const statsContent = document.getElementById('weeklyChartModalCanvas'); // Target canvas directly
                if (!statsContent) {
                    console.error("Canvas element not found for weekly chart modal.");
                    // Jika canvas tidak ditemukan, tampilkan pesan error di body modal
                    const modalBody = weeklyStatsModalElement.querySelector('.modal-body');
                    modalBody.innerHTML = '<p class="text-danger text-center">Element grafik tidak ditemukan.</p>';
                    return;
                }

                // Tampilkan spinner loading sebelum fetch
                const modalBody = weeklyStatsModalElement.querySelector('.modal-body');
                modalBody.innerHTML = '<p class="text-center text-muted">Memuat statistik...</p><div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div><canvas id="weeklyChartModalCanvas" height="180" style="display:none;"></canvas>'; // Sembunyikan canvas saat loading

                fetch('weekly_stats.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response for cek_notif.php was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        modalBody.innerHTML = '<canvas id="weeklyChartModalCanvas" height="180"></canvas>'; // Ganti dengan canvas setelah data diterima
                        const updatedCanvas = document.getElementById('weeklyChartModalCanvas');

                        if (weeklyChartModal) weeklyChartModal.destroy(); // Destroy previous chart instance

                        weeklyChartModal = new Chart(updatedCanvas.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: data.labels,
                                datasets: [{
                                    label: 'Tugas Selesai',
                                    data: data.data,
                                    backgroundColor: 'var(--primary-color)'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0
                                        }
                                    }
                                },
                                animation: {
                                    duration: 1500,
                                    easing: 'easeOutQuart'
                                }
                            }
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching or parsing stats:', error);
                        modalBody.innerHTML = '<p class="text-danger text-center">Gagal memuat statistik. Error: ' + error.message + '</p>';
                    });
            });

            weeklyStatsModalElement.addEventListener('hidden.bs.modal', function() {
                if (weeklyChartModal) {
                    weeklyChartModal.destroy();
                    weeklyChartModal = null;
                }
                // Reset modal body to initial canvas state for next open
                const modalBody = weeklyStatsModalElement.querySelector('.modal-body');
                modalBody.innerHTML = '<canvas id="weeklyChartModalCanvas" height="180"></canvas>';
            });
        }

        // Notifikasi (dari cek_notif.php)
        // const notifSound = new Audio('https://cdn.pixabay.com/audio/2022/07/26/audio_124bfa4c3b.mp3'); // Dikomentari untuk menghindari error 403

        function cekNotifikasiNavbar() {
            fetch('cek_notif.php')
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Network response for cek_notif.php was not ok');
                    }
                    return res.json();
                })
                .then(data => {
                    let notifArea = document.getElementById('notif-area');
                    let notifBadge = document.getElementById('notif-badge');
                    if (data.tugas && data.tugas.length > 0) {
                        notifBadge.style.display = 'block';
                        let pesanHtml = '<div class="list-group list-group-flush">';
                        data.tugas.forEach(t => {
                            pesanHtml += '<div class="list-group-item list-group-item-action py-2 animate__animated animate__fadeInLeft">' +
                                '<i class="fa-solid fa-circle-exclamation text-danger me-2"></i>' +
                                '<strong>' + esc(t.nama_tugas) + '</strong><br>' +
                                '<small class="text-muted">Deadline: ' + esc(t.deadline) + '</small>' +
                                '</div>';
                        });
                        pesanHtml += '</div>';
                        notifArea.innerHTML = pesanHtml;
                        // notifSound.play().catch(e => console.error("Error playing sound:", e));
                    } else {
                        notifBadge.style.display = 'none';
                        notifArea.innerHTML = '<div class="text-muted text-center py-3">Tidak ada notifikasi baru.</div>';
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }

        // Jalankan cekNotifikasiNavbar setiap 60 detik
        setInterval(cekNotifikasiNavbar, 60000);
        // Jalankan saat halaman dimuat pertama kali (setelah DOM siap)
        cekNotifikasiNavbar();

        // Escape HTML for safety (client-side)
        function esc(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        // Optional: Add basic form animation on input focus
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', () => {
                input.style.borderColor = 'var(--primary-color)';
                input.style.boxShadow = '0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25)';
            });
            input.addEventListener('blur', () => {
                input.style.borderColor = '';
                input.style.boxShadow = '';
            });
        });

        // ===========================================
        // JAVASCRIPT FOR CHECKBOX, EDIT, DELETE
        // ===========================================

        // Handle Task Status Toggle (Checkbox)
        $(document).on('change', '.task-checkbox', function() {
            const taskId = $(this).data('task-id');
            const isChecked = $(this).is(':checked');
            const newStatus = isChecked ? 'selesai' : 'belum';
            const taskItem = $(this).closest('.list-group-item');
            const taskName = taskItem.find('h5');
            const statusBadge = taskItem.find('.status-badge');

            $.ajax({
                url: 'dashboard.php?display_month=<?= $display_month ?>&display_year=<?= $display_year ?>', // Kirim ke halaman yang sama
                type: 'POST',
                data: {
                    task_id_toggle_status: taskId,
                    current_status: isChecked ? 'belum' : 'selesai' // Kirim status saat ini (sebelum diubah)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        taskName.toggleClass('text-decoration-line-through text-muted', isChecked);
                        statusBadge.removeClass('status-belum status-selesai status-sedang-dikerjakan status-terlambat status-mendesak')
                                   .addClass('status-' + response.new_status);
                        
                        // Perbarui teks badge berdasarkan status baru
                        if (response.new_status === 'selesai') {
                            statusBadge.text('Selesai');
                            statusBadge.css('background-color', 'var(--success-color)'); // Set warna success
                        } else {
                            // Re-evaluate urgent/late status if task becomes 'belum' again
                            let deadlineText = taskItem.find('p.small.text-muted').text();
                            let datePart = deadlineText.split('Deadline: ')[1].split(', Pukul')[0].trim();
                            let timePart = deadlineText.split('Pukul ')[1].trim();

                            const monthNamesIndo = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                            const monthNamesEng = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            
                            let dateParts = datePart.split(' ');
                            let dayOfMonth = dateParts[1];
                            let monthShortIndo = dateParts[2];
                            let year = dateParts[3];
                            
                            let monthIndex = monthNamesIndo.indexOf(monthShortIndo);
                            let monthShortEng = monthNamesEng[monthIndex];
                            
                            let formattedDeadlineString = `${monthShortEng} ${dayOfMonth}, ${year} ${timePart}:00`;
                            let fullDeadline = new Date(formattedDeadlineString);
                            
                            let now = new Date();
                            if (fullDeadline < now) {
                                statusBadge.text('Terlambat');
                                statusBadge.removeClass('status-warning').addClass('status-danger');
                            } else if ((fullDeadline.getTime() - now.getTime()) / (1000 * 60 * 60) <= 24) { // within 24 hours
                                statusBadge.text('Mendesak');
                                statusBadge.removeClass('status-danger').addClass('status-warning');
                            } else {
                                statusBadge.text('Belum');
                                statusBadge.removeClass('status-warning').addClass('status-danger');
                            }
                        }
                        
                        // Opsional: refresh statistik umum
                        location.reload(); // Untuk update statistik progress bar dan list
                    } else {
                        alert('Gagal memperbarui status tugas: ' + response.message);
                        $(this).prop('checked', !isChecked); // Kembalikan ke status semula jika gagal
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    alert('Terjadi kesalahan saat memperbarui status tugas.');
                    $(this).prop('checked', !isChecked); // Kembalikan ke status semula jika gagal
                }
            });
        });

        // Populate Edit Task Modal
        $(document).on('click', '.edit-task-btn', function() {
            const id = $(this).data('id');
            const nama = $(this).data('nama');
            const deskripsi = $(this).data('deskripsi');
            const deadline = $(this).data('deadline'); // Format YYYY-MM-DDTHH:MM

            $('#editTaskId').val(id);
            $('#editNamaTugas').val(nama);
            $('#editDeskripsiTugas').val(deskripsi);
            $('#editDeadlineTugas').val(deadline);
        });

        // Populate Edit Event Modal
        $(document).on('click', '.edit-event-btn', function() {
            const id = $(this).data('id');
            const nama = $(this).data('nama');
            const deskripsi = $(this).data('deskripsi');
            const tanggal = $(this).data('tanggal'); // Format YYYY-MM-DD

            $('#editEventId').val(id);
            $('#editNamaAcara').val(nama);
            $('#editDeskripsiAcara').val(deskripsi);
            $('#editTanggalAcara').val(tanggal);
        });

    }); // END of $(document).ready(function() { ... });
</script>
</body>
</html>