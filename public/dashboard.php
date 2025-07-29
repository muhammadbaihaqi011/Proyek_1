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
    while ($row = $task_dates_result_month->fetch_assoc()) {
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
    while ($row = $event_dates_result_month->fetch_assoc()) {
        $dates_with_activity[] = $row['tanggal'];
    }
    $stmt_event_dates_month->close();
} else {
    error_log("Error preparing monthly event dates query: " . $conn->error);
}

$dates_with_activity = array_unique($dates_with_activity);


// === DATA UNTUK DAFTAR TUGAS & ACARA LENGKAP DI SIDEBAR KANAN ===
$all_tasks = [];
$stmt_all_tasks = $conn->prepare("SELECT id, nama_tugas, deskripsi, deadline, status FROM tugas WHERE user_id = ? AND status != 'selesai' ORDER BY deadline ASC");
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
$stmt_all_events = $conn->prepare("SELECT id, nama_acara, deskripsi, tanggal FROM acara WHERE user_id = ? AND tanggal >= CURDATE() ORDER BY tanggal ASC");
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
if ($stmt_completed_tasks === false) {
    error_log("Error preparing completed tasks query: " . $conn->error);
} else {
    $stmt_completed_tasks->bind_param("i", $user_id);
    $stmt_completed_tasks->execute();
    $completed_tasks_result = $stmt_completed_tasks->get_result();
    while ($t = $completed_tasks_result->fetch_assoc()) {
        $completed_tasks[] = $t;
    }
    $stmt_completed_tasks->close();
}

$past_events = [];
$stmt_past_events = $conn->prepare("SELECT id, nama_acara, deskripsi, tanggal FROM acara WHERE user_id = ? AND tanggal < CURDATE() ORDER BY tanggal DESC");
if ($stmt_past_events === false) {
    error_log("Error preparing past events query: " . $conn->error);
} else {
    $stmt_past_events->bind_param("i", $user_id);
    $stmt_past_events->execute();
    $past_events_result = $stmt_past_events->get_result();
    while ($a = $past_events_result->fetch_assoc()) {
        $past_events[] = $a;
    }
    $stmt_past_events->close();
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
                header('Location: dashboard.php?display_month=' . $display_month . '&display_year=' . $display_year);
                exit;
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

// PROSES UPDATE STATUS TUGAS (MELALUI AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_task_status') {
    $task_id = $_POST['task_id'];
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
    exit;
}

// PROSES DELETE TUGAS (MELALUI AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task') {
    $task_ids = $_POST['id']; // Bisa berupa satu ID atau array ID
    if (!is_array($task_ids)) {
        $task_ids = [$task_ids];
    }
    $success = true;
    $message = [];
    $deleted_ids = [];
    $conn->begin_transaction();
    foreach ($task_ids as $task_id) {
        $stmt = $conn->prepare("DELETE FROM tugas WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $task_id, $user_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $deleted_ids[] = $task_id;
                }
            } else {
                $success = false;
                $message[] = "ID {$task_id}: " . $stmt->error;
                error_log("Error deleting task (AJAX), ID {$task_id}: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $success = false;
            $message[] = "Gagal menyiapkan query hapus tugas: " . $conn->error;
            error_log("Error preparing delete task query (AJAX): " . $conn->error);
        }
    }
    if ($success) {
        $conn->commit();
    } else {
        $conn->rollback();
    }
    echo json_encode(['success' => $success, 'message' => implode("; ", $message), 'deleted_ids' => $deleted_ids]);
    exit;
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

// PROSES DELETE ACARA (MELALUI AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event') {
    $event_ids = [];
    if (isset($_POST['id'])) {
        if (is_array($_POST['id'])) {
            $event_ids = $_POST['id'];
        } else {
            $event_ids = [$_POST['id']];
        }
    } elseif (isset($_POST['id[]'])) {
        if (is_array($_POST['id[]'])) {
            $event_ids = $_POST['id[]'];
        } else {
            $event_ids = [$_POST['id[]']];
        }
    }

    $success = true;
    $message = [];
    $deleted_ids = [];

    $conn->begin_transaction();
    try {
        foreach ($event_ids as $event_id) {
            $stmt = $conn->prepare("DELETE FROM acara WHERE id = ? AND user_id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $event_id, $user_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $deleted_ids[] = $event_id;
                    }
                } else {
                    $success = false;
                    $message[] = "ID {$event_id}: " . $stmt->error;
                    error_log("Error deleting event (AJAX), ID {$event_id}: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $success = false;
                $message[] = "Gagal menyiapkan query hapus acara: " . $conn->error;
                error_log("Error preparing delete event query (AJAX): " . $conn->error);
                throw new Exception("Query preparation failed");
            }
        }
        if ($success) {
            $conn->commit();
        } else {
            $conn->rollback();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $success = false;
        $message[] = "Exception: " . $e->getMessage();
        error_log("Transaction Exception for delete event: " . $e->getMessage());
    }
    echo json_encode(['success' => $success, 'message' => implode("; ", $message), 'deleted_ids' => $deleted_ids]);
    exit;
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
$mot_today = $motivasi[date('z') % count(array_keys($motivasi))];


// Statistik Ringkasan
$total_tasks = 0;
$done_tasks = 0;
$total_events = 0;
$total_tasks_query = $conn->prepare("SELECT COUNT(*) as jml FROM tugas WHERE user_id = ?");
if ($total_tasks_query === false) {
    error_log("Error preparing total tasks query: " . $conn->error);
} else {
    $total_tasks_query->bind_param("i", $user_id);
    $total_tasks_query->execute();
    $total_tasks = $total_tasks_query->get_result()->fetch_assoc()['jml'];
    $total_tasks_query->close();
}

$done_tasks_query = $conn->prepare("SELECT COUNT(*) as jml FROM tugas WHERE user_id = ? AND status = 'selesai'");
if ($done_tasks_query === false) {
    error_log("Error preparing done tasks query: " . $conn->error);
} else {
    $done_tasks_query->bind_param("i", $user_id);
    $done_tasks_query->execute();
    $done_tasks = $done_tasks_query->get_result()->fetch_assoc()['jml'];
    $done_tasks_query->close();
}

$total_events_query = $conn->prepare("SELECT COUNT(*) as jml FROM acara WHERE user_id = ?");
if ($total_events_query === false) {
    error_log("Error preparing total events query: " . $conn->error);
} else {
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
    <title>Fokus & Selesai</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <style>
        :root {
            --primary-color: #007bff;
            /* Aksen biru standar Bootstrap */
            --secondary-color: #6c757d;
            --background-light: #f0f2f5;
            /* Background sangat terang */
            --background-dark: #2c3e50;
            /* Background gelap default */
            --card-background: #ffffff;
            /* Putih untuk kartu di light mode */
            --text-color-dark: #333;
            /* Teks gelap di light mode */
            --text-color-light: #666;
            /* Teks lebih samar di light mode */
            --border-color: #e9ecef;
            --shadow-light: 0 5px 15px rgba(0, 0, 0, 0.08);
            --shadow-medium: 0 10px 30px rgba(0, 0, 0, 0.15);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --primary-color-rgb: 0, 123, 255;
            --danger-color-rgb: 220, 53, 69;
        }

        body {
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--background-light);
            /* Default light theme */
            color: var(--text-color-dark);
            padding-top: 70px;
            min-height: 100vh;
            overflow-x: hidden;
            transition: background-color 0.3s ease, color 0.3s ease;
            position: relative;
        }

        /* Dark Theme overrides */
        body.theme-dark {
            --primary-color: #4a90e2;
            /* Softer blue for dark theme */
            --secondary-color: #b0b8c0;
            --background-light: #2c3e50;
            /* Darker background */
            --card-background: #34495e;
            /* Darker card background */
            --text-color-dark: #ecf0f1;
            --text-color-light: #bdc3c7;
            --border-color: #444;
            --shadow-light: 0 5px 15px rgba(0, 0, 0, 0.3);
            --shadow-medium: 0 10px 30px rgba(0, 0, 0, 0.4);
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --primary-color-rgb: 74, 144, 226;
            --danger-color-rgb: 231, 76, 60;
        }

        body.theme-dark .navbar {
            background-color: #2c3e50 !important;
        }

        body.theme-dark .navbar-brand,
        body.theme-dark .nav-link,
        body.theme-dark .dropdown-toggle {
            color: #ecf0f1 !important;
        }

        body.theme-dark .welcome-section {
            background: linear-gradient(45deg, #34495e, #4a6a8a);
        }

        body.theme-dark .card,
        body.theme-dark .calendar-edlink {
            background-color: var(--card-background);
            box-shadow: var(--shadow-medium);
        }

        body.theme-dark .card-header {
            background-color: #3f5870;
            color: var(--text-color-dark);
            border-bottom-color: var(--border-color);
        }

        body.theme-dark .task-item,
        body.theme-dark .event-item {
            border-bottom-color: var(--border-color);
        }

        body.theme-dark .task-item h5,
        body.theme-dark .event-item h5 {
            color: var(--text-color-dark);
        }

        body.theme-dark .task-item p,
        body.theme-dark .event-item p {
            color: var(--text-color-light);
        }

        body.theme-dark .form-control {
            background-color: #3f5870;
            color: var(--text-color-dark);
            border-color: var(--border-color);
        }

        body.theme-dark .form-control::placeholder {
            color: var(--text-color-light);
        }

        body.theme-dark .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
        }

        body.theme-dark .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }

        body.theme-dark .btn-primary:hover {
            background-color: #3a80ce;
            border-color: #3a80ce;
        }

        body.theme-dark .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: #1e1e2e;
        }

        /* Teks gelap di dark success */
        body.theme-dark .btn-info {
            background-color: var(--info-color);
            border-color: var(--info-color);
            color: #1e1e2e;
        }

        /* Teks gelap di dark info */
        body.theme-dark .btn-info:hover {
            background-color: #258cd1;
            border-color: #258cd1;
        }

        body.theme-dark .modal-content {
            background-color: var(--card-background);
            color: var(--text-color-dark);
        }

        body.theme-dark .modal-header {
            background-color: var(--primary-color);
            color: #ecf0f1;
        }

        body.theme-dark .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        body.theme-dark .dropdown-menu {
            background-color: var(--card-background);
            border-color: var(--border-color);
        }

        body.theme-dark .dropdown-item {
            color: var(--text-color-dark);
        }

        body.theme-dark .dropdown-item:hover {
            background-color: #4a6a8a;
            color: #ecf0f1;
        }

        body.theme-dark .dropdown-divider {
            border-top-color: var(--border-color);
        }

        body.theme-dark .list-group-item {
            background-color: var(--card-background);
            color: var(--text-color-dark);
            border-color: var(--border-color);
        }

        body.theme-dark .avatar-upload label {
            color: var(--primary-color);
        }

        /* Dark theme adjustment */
        body.theme-dark .task-checkbox,
        body.theme-dark .history-checkbox {
            border: 2px solid var(--primary-color);
        }

        /* Border accent di dark theme */
        body.theme-dark .task-checkbox:checked::before,
        body.theme-dark .history-checkbox:checked::before {
            color: white;
        }

        /* Centang putih di dark theme */
        body.theme-dark .floating-robot {
            background-color: #4a90e2;
        }

        /* Warna robot di dark theme */
        body.theme-dark .robot-head-small {
            background-color: #3498db;
        }

        /* Warna kepala robot di dark theme */
        body.theme-dark .robot-eye-small {
            background-color: #f1c40f;
        }

        /* Warna mata robot di dark theme */
        body.theme-dark .robot-mouth-small {
            background-color: #e74c3c;
        }

        /* Warna mulut robot di dark theme */
        body.theme-dark .calendar-edlink h4,
        body.theme-dark .calendar-edlink .weekdays span,
        body.theme-dark .calendar-edlink .text-secondary {
            color: var(--text-color-dark) !important;
        }

        body.theme-dark .task-item.urgent-task {
            background-color: #404351 !important;
        }

        /* Urgent task highlight in dark mode */


        .navbar {
            background-color: var(--primary-color) !important;
            /* Navbar biru di tema terang */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease;
            position: fixed;
            /* Tetap fixed */
            width: 100%;
            /* Lebar penuh */
            top: 0;
            /* Di bagian atas */
            left: 0;
            z-index: 1000;
            /* Pastikan navbar di atas segalanya */
        }

        .navbar-brand {
            font-weight: 700;
            color: #ffffff !important;
            /* Warna brand putih */
        }

        .welcome-section {
            background: linear-gradient(45deg, var(--primary-color), #00c6ff);
            /* Gradien biru terang */
            color: white;
            /* Teks putih di welcome section */
            padding: 50px 0;
            margin-bottom: 30px;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            animation: fadeInDown 0.8s ease-out;
            position: relative;
            overflow: hidden;
            z-index: 10;
        }

        .welcome-section-content {
            position: relative;
            z-index: 10;
        }

        .welcome-section h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
        }

        .welcome-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease-out;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: var(--shadow-light);
            margin-bottom: 25px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeIn 0.8s ease-out;
            background-color: var(--card-background);
            /* Background kartu putih */
            color: var(--text-color-dark);
            position: relative;
            z-index: 10;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .card-header {
            background-color: var(--border-color);
            /* Header kartu abu-abu muda */
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

        .task-item,
        .event-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            animation: fadeIn 0.5s ease-out;
        }

        .task-item:last-child,
        .event-item:last-child {
            border-bottom: none;
        }

        .task-item-content,
        .event-item-content {
            flex-grow: 1;
            flex-shrink: 1;
            margin-right: 15px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .task-item-actions,
        .event-item-actions {
            flex-shrink: 0;
            margin-left: auto;
            white-space: nowrap;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .history-item-content {
            flex-grow: 1;
            flex-shrink: 1;
            word-wrap: break-word;
            overflow-wrap: break-word;
            margin-right: 10px;
        }

        .history-item-actions {
            flex-shrink: 0;
            margin-left: auto;
            display: flex;
            align-items: center;
        }


        .task-item h5,
        .event-item h5 {
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-color-dark);
        }

        .task-item p,
        .event-item p {
            margin-bottom: 0;
            font-size: 0.9rem;
            color: var(--text-color-light);
        }

        .status-badge {
            font-size: 0.75em;
            padding: 0.4em 0.8em;
            border-radius: 0.375rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 5px;
        }

        .status-selesai {
            background-color: var(--success-color);
            color: white;
        }

        .status-belum {
            background-color: var(--danger-color);
            color: white;
        }

        .status-sedang-dikerjakan {
            background-color: var(--warning-color);
            color: var(--text-color-dark);
        }

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

        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: var(--text-color-dark);
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
        }

        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }

        .btn-info {
            background-color: var(--info-color);
            border-color: var(--info-color);
            color: white;
        }

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
        .calendar-edlink {
            background-color: var(--card-background);
            border-radius: 15px;
            box-shadow: var(--shadow-light);
            padding: 20px;
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeIn 0.8s ease-out;
            color: var(--text-color-dark);
            position: relative;
            /* Tambahkan untuk z-index */
            z-index: 10;
        }

        .calendar-edlink .weekdays {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            color: var(--text-color-dark);
            margin-bottom: 10px;
        }

        .calendar-edlink h4,
        .calendar-edlink .text-secondary {
            color: var(--text-color-dark);
        }

        body.theme-light .calendar-edlink h4,
        body.theme-light .calendar-edlink .weekdays span,
        body.theme-light .calendar-edlink .text-secondary {
            color: var(--text-color-dark) !important;
        }

        body.theme-dark .calendar-edlink h4,
        body.theme-dark .calendar-edlink .weekdays span,
        body.theme-dark .calendar-edlink .text-secondary {
            color: var(--text-color-dark) !important;
        }


        .calendar-edlink .days {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            flex-wrap: wrap;
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
            color: var(--text-color-dark);
            transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
            text-decoration: none;
            margin: 2px;
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
            opacity: 0.5;
            /* Fade days from other months */
        }

        .calendar-edlink .day .dot {
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--warning-color);
        }

        .daily-schedule-card {
            margin-top: 20px;
        }

        .schedule-item {
            background-color: var(--background-light);
            /* Lighter background for schedule items */
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            color: var(--text-color-dark);
        }

        .schedule-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            width: 36px;
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
            width: 20px;
            text-align: center;
            margin-right: 5px;
        }

        /* Notifikasi */
        .notification-bell {
            position: relative;
            cursor: pointer;
            font-size: 1.2rem;
            color: white;
            /* Bell putih di navbar */
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
            z-index: 100;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(var(--danger-color-rgb), 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(var(--danger-color-rgb), 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(var(--danger-color-rgb), 0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .progress-circle-bg,
        .progress-circle-fg {
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

        /* Ensure primary-color-rgb is defined for rgba usage */
        body:not(.theme-dark) {
            --primary-color-rgb: 0, 123, 255;
            --danger-color-rgb: 220, 53, 69;
        }

        body.theme-dark {
            --primary-color-rgb: 74, 144, 226;
            --danger-color-rgb: 231, 76, 60;
        }


        .list-group-item:hover {
            background-color: rgba(0, 123, 255, 0.05);
            /* Softer hover for checklist */
        }

        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background-color: var(--card-background);
            /* Pastikan background form control sesuai tema */
            color: var(--text-color-dark);
            /* Warna teks di form control */
        }

        .form-control::placeholder {
            color: var(--text-color-light);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
            outline: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s ease, border-color 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            /* Darker blue on hover */
            border-color: #0056b3;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
        }

        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }

        .btn-info {
            background-color: var(--info-color);
            border-color: var(--info-color);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
        }


        /* Styles from user's provided code, integrated and harmonized */
        .card-task,
        .card-event,
        .shadow-sm {
            box-shadow: var(--shadow-light) !important;
        }

        .card-task:hover,
        .card-event:hover,
        .list-group-item:hover,
        .btn:hover {
            box-shadow: 0 2px 12px rgba(var(--primary-color-rgb), 0.2) !important;
            transform: translateY(-2px) scale(1.03);
            transition: 0.2s;
        }

        .avatar-upload {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar-upload input[type=file] {
            display: none;
        }

        .avatar-upload label {
            cursor: pointer;
            color: var(--primary-color);
            text-decoration: underline;
        }

        /* Tema gelap override untuk label avatar */
        body.theme-dark .avatar-upload label {
            color: var(--primary-color);
        }


        /* Checkbox styling (kustom) */
        .task-checkbox,
        .history-checkbox {
            width: 20px;
            height: 20px;
            min-width: 20px;
            margin-right: 15px;
            cursor: pointer;
            position: relative;
            appearance: none;
            border: 2px solid var(--primary-color);
            /* Biru di light theme */
            border-radius: 5px;
            transition: all 0.2s ease;
            align-self: center;
        }

        /* Tema gelap override untuk checkbox */
        body.theme-dark .task-checkbox,
        body.theme-dark .history-checkbox {
            border: 2px solid var(--primary-color);
            /* Biru di dark theme */
        }

        .task-checkbox:checked,
        .history-checkbox:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .task-checkbox:checked::before,
        .history-checkbox:checked::before {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
        }

        /* Tema gelap override untuk centang checkbox */
        body.theme-dark .task-checkbox:checked::before,
        body.theme-dark .history-checkbox:checked::before {
            color: white;
            /* Tetap putih di dark theme */
        }

        .task-checkbox:focus,
        .history-checkbox:focus {
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(var(--primary-color-rgb), 0.25);
        }


        @media (min-width: 992px) {
            .sidebar-col {
                position: sticky;
                top: 80px;
                height: calc(100vh - 100px);
                overflow-y: auto;
                padding-bottom: 20px;
            }
        }

        /* FLOATING ROBOT (PENYEMANGAT KECIL) */
        .floating-robot {
            width: 50px;
            height: 60px;
            background-color: #007bff;
            /* Biru terang */
            border-radius: 10px 10px 5px 5px;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999;
            cursor: pointer;
            box-shadow: var(--shadow-light);
            animation: float 2s infinite ease-in-out;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Warna robot di tema gelap */
        body.theme-dark .floating-robot {
            background-color: #4a90e2;
            /* Softer blue for dark theme */
        }

        .robot-head-small {
            width: 30px;
            height: 30px;
            background-color: #3498db;
            /* Biru */
            border-radius: 50%;
            margin-bottom: 5px;
            position: relative;
        }

        /* Warna kepala robot di tema gelap */
        body.theme-dark .robot-head-small {
            background-color: #3498db;
            /* Konsisten di dark theme */
        }

        .robot-eye-small {
            width: 8px;
            height: 8px;
            background-color: #ffc107;
            /* Kuning */
            border-radius: 50%;
            position: absolute;
            top: 8px;
        }

        .robot-eye-small:first-child {
            left: 8px;
        }

        .robot-eye-small:last-child {
            right: 8px;
        }

        /* Warna mata robot di tema gelap */
        body.theme-dark .robot-eye-small {
            background-color: #f1c40f;
            /* Kuning di dark theme */
        }

        /* Mulut robot kecil */
        .robot-mouth-small {
            width: 20px;
            height: 15px;
            background-color: #dc3545;
            /* Merah */
            border-radius: 0 0 5px 5px;
        }

        /* Warna mulut robot di dark theme */
        body.theme-dark .robot-mouth-small {
            background-color: #e74c3c;
            /* Merah di dark theme */
        }


        @keyframes float {
            0% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(0);
            }
        }

        /* END FLOATING ROBOT */
    </style>
</head>

<body>
    <div class="floating-robot">
        <div class="robot-head-small">
            <div class="robot-eye-small"></div>
            <div class="robot-eye-small"></div>
        </div>
        <div class="robot-mouth-small"></div>
    </div>

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
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <div id="notif-area" class="small text-muted px-3">Tidak ada notifikasi baru.</div>
                        </li>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn d-flex align-items-center gap-2 text-white" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background:transparent;">
                        <img id="avatar-img" src="<?= $avatar_path ?>" class="profile-img" alt="Foto Profil">
                        <span class="fw-semibold"><i class="fa-solid fa-user me-1"></i> <?= esc($username) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                        <li>
                            <h6 class="dropdown-header">Profil Pengguna</h6>
                        </li>
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
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><button class="dropdown-item" id="showStatsBtn" type="button"><i class="fa-solid fa-chart-bar me-2"></i> Statistik Mingguan</button></li>
                        <?php if (get_user_id() == 1): // Tampilkan hanya jika user adalah admin (user_id = 1) 
                        ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="admin_users.php"><i class="fa-solid fa-users-gear me-2"></i> Halaman Admin</a></li>
                        <?php endif; ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="welcome-section text-center">
        <div class="container welcome-section-content animate__animated animate__fadeInDown">
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
                    <div class="mb-3 p-2 rounded bg-gradient" style="background:linear-gradient(90deg, var(--primary-color) 60%,#00c6ff 100%);color:#00c6ff;box-shadow:0 2px 8px rgba(var(--primary-color-rgb),0.2);">
                        <i class="fa-solid fa-quote-left"></i> <span class="fw-semibold"><?= $mot_today ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <a href="<?= $prev_month_link ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-chevron-left"></i></a>
                        <span class="fw-bold fs-5" style="color:var(--text-color-dark);"><?= $display_date_obj->format('F Y') ?></span>
                        <a href="<?= $next_month_link ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-chevron-right"></i></a>
                    </div>
                    <div class="weekdays">
                        <?php $hari = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
                        foreach ($hari as $h) echo '<span style="color:var(--text-color-dark);">' . $h . '</span>'; ?>
                    </div>
                    <div class="days mt-2">
                        <?php
                        $current_day_in_loop = clone $calendar_start_date_obj;
                        // Loop for 6 weeks (42 days) to cover the entire month generously
                        for ($i = 0; $i < 42; $i++) {
                            $dateStr = $current_day_in_loop->format('Y-m-d');
                            $dayNum = $current_day_in_loop->format('d');
                            $isToday = $dateStr === $today_str;
                            $isInDisplayMonth = $current_day_in_loop->format('m') == $display_month && $current_day_in_loop->format('Y') == $display_year;

                            $dot_html = '';
                            if (in_array($dateStr, $dates_with_activity)) {
                                $dot_html = '<span class="dot"></span>';
                            }

                            echo '<a href="?display_month=' . $display_month . '&display_year=' . $display_year . '&tgl=' . $dateStr . '" style="text-decoration:none;">';
                            echo '<div class="day' . ($isToday ? ' today' : '') . ($isInDisplayMonth ? '' : ' other-month') . '">';
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
                        <?php if ($err_tugas): ?><div class="alert alert-danger mb-3 animate__animated animate__fadeInDown" role="alert"><?= esc($err_tugas) ?></div><?php endif; ?>
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
                        <?php if ($err_acara): ?><div class="alert alert-danger mb-3 animate__animated animate__fadeInDown" role="alert"><?= esc($err_acara) ?></div><?php endif; ?>
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
                                <?php foreach ($all_tasks as $t): ?>
                                    <?php if ($t['status'] !== 'selesai'): ?>
                                        <?php
                                        $is_urgent = ($t['status'] === 'belum' && strtotime($t['deadline']) >= time() && strtotime($t['deadline']) <= strtotime('+1 day'));
                                        $is_late = ($t['status'] === 'belum' && strtotime($t['deadline']) < time());
                                        ?>
                                        <li class="list-group-item d-flex align-items-center shadow-sm mb-1 animate__animated animate__fadeInUp <?= $is_urgent ? 'urgent-task' : '' ?>" style="border-radius:12px;" data-id="<?= $t['id'] ?>" data-type="task">
                                            <input type="checkbox" class="task-checkbox" data-task-id="<?= $t['id'] ?>">
                                            <div class="task-item-content">
                                                <h5 class="mb-0"><?= esc($t['nama_tugas']) ?></h5>
                                                <p class="small text-muted mb-1">
                                                    Deadline: <?= esc(tgl_indo_edlink(date('Y-m-d', strtotime($t['deadline'])))) ?>, Pukul <?= date('H:i', strtotime($t['deadline'])) ?>
                                                </p>
                                                <?php if (!empty($t['deskripsi'])): ?>
                                                    <p class="small"><?= esc($t['deskripsi']) ?></p>
                                                <?php endif; ?>
                                                <span class="status-badge status-<?= $t['status'] ?>"><?= $is_late ? 'Terlambat' : ($is_urgent ? 'Mendesak' : 'Belum') ?></span>
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
                                                <button class="btn btn-sm btn-danger delete-item-btn" data-id="<?= $t['id'] ?>" data-type="task">
                                                    <i class="fa-solid fa-trash-alt"></i> Hapus
                                                </button>
                                            </div>
                                        </li>
                                    <?php endif; ?>
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
                            <div class="text-muted small no-items-message">Tidak ada acara yang tersedia.</div <?php else: ?>
                                <ul class="list-group">
                            <?php foreach ($all_events as $a): ?>
                                <?php if (strtotime($a['tanggal']) >= strtotime(date('Y-m-d'))): ?>
                                    <li class="list-group-item d-flex align-items-center shadow-sm mb-1 animate__animated animate__fadeInUp" style="border-radius:12px;" data-id="<?= $a['id'] ?>" data-type="event">
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
                                            <button class="btn btn-sm btn-danger delete-item-btn" data-id="<?= $a['id'] ?>" data-type="event">
                                                <i class="fa-solid fa-trash-alt"></i> Hapus
                                            </button>
                                        </div>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card animate__animated animate__fadeInRight mb-4">
                    <div class="card-header">
                        <span class="me-auto"><i class="fa-solid fa-history me-2"></i> Riwayat Tugas & Acara Selesai</span>
                        <div class="form-check form-check-inline me-2">
                            <input class="form-check-input history-select-all-checkbox" type="checkbox" id="historySelectAllCheckbox">
                            <label class="form-check-label" for="historySelectAllCheckbox">Pilih Semua</label>
                        </div>
                        <button class="btn btn-sm btn-danger" id="deleteSelectedHistoryBtn" disabled>
                            <i class="fa-solid fa-trash-alt me-1"></i> Hapus
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($completed_tasks) && empty($past_events)): ?>
                            <div class="text-muted small no-items-message">Tidak ada riwayat tugas atau acara.</div>
                        <?php else: ?>
                            <?php if (!empty($completed_tasks)): ?>
                                <h6 class="mt-2 mb-2">Tugas Selesai:</h6>
                                <ul class="list-group mb-3" id="completedTasksList">
                                    <?php foreach ($completed_tasks as $t): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center shadow-sm mb-1 animate__animated animate__fadeInUp" style="border-radius:12px;" data-id="<?= $t['id'] ?>" data-type="task">
                                            <div class="history-item-content">
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
                                                <input type="checkbox" class="history-checkbox" data-id="<?= $t['id'] ?>" data-type="task">
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!empty($past_events)): ?>
                                <h6 class="mt-4 mb-2">Acara yang Telah Berlalu:</h6>
                                <ul class="list-group" id="pastEventsList">
                                    <?php foreach ($past_events as $a): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center shadow-sm mb-1 animate__animated animate__fadeInUp" style="border-radius:12px;" data-id="<?= $a['id'] ?>" data-type="event">
                                            <div class="history-item-content">
                                                <h5 class="mb-0"><?= esc($a['nama_acara']) ?></h5>
                                                <p class="small text-muted mb-1">Tanggal: <?= esc(tgl_indo_edlink($a['tanggal'])) ?></p>
                                                <?php if (!empty($a['deskripsi'])): ?>
                                                    <p class="small"><?= esc($a['deskripsi']) ?></p>
                                                <?php endif; ?>
                                                <span class="badge bg-secondary text-white">Selesai</span>
                                            </div>
                                            <div class="history-item-actions">
                                                <input type="checkbox" class="history-checkbox" data-id="<?= $a['id'] ?>" data-type="event">
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
        // Array untuk melacak ID notifikasi yang sudah ditampilkan di sesi ini
        const notifiedItems = {}; // { taskId: true, eventId: true }

        $(document).ready(function() {
            // Theme Switcher
            const themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                themeToggle.onclick = function() {
                    document.body.classList.toggle('theme-dark');
                    localStorage.setItem('theme', document.body.classList.contains('theme-dark') ? 'dark' : 'light');
                    // Mengubah ikon berdasarkan tema
                    if (document.body.classList.contains('theme-dark')) {
                        themeToggle.querySelector('i').classList.remove('fa-moon');
                        themeToggle.querySelector('i').classList.add('fa-sun');
                    } else {
                        themeToggle.querySelector('i').classList.remove('fa-sun');
                        themeToggle.querySelector('i').classList.add('fa-moon');
                    }
                };
                // Set theme on initial load
                if (localStorage.getItem('theme') === 'dark') {
                    document.body.classList.add('theme-dark');
                    themeToggle.querySelector('i').classList.remove('fa-moon');
                    themeToggle.querySelector('i').classList.add('fa-sun');
                } else {
                    // Pastikan ikon default adalah bulan jika tema light
                    themeToggle.querySelector('i').classList.remove('fa-sun');
                    themeToggle.querySelector('i').classList.add('fa-moon');
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
                    var myModal = new bootstrap.Modal(weeklyStatsModalElement);
                    myModal.show();
                });

                weeklyStatsModalElement.addEventListener('shown.bs.modal', function() {
                    const modalBody = weeklyStatsModalElement.querySelector('.modal-body');
                    modalBody.innerHTML = '<p class="text-center text-muted">Memuat statistik...</p><div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div><canvas id="weeklyChartModalCanvas" height="180" style="display:none;"></canvas>';

                    fetch('weekly_stats.php')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response for cek_notif.php was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            modalBody.innerHTML = '<canvas id="weeklyChartModalCanvas" height="180"></canvas>';
                            const updatedCanvas = document.getElementById('weeklyChartModalCanvas');

                            if (weeklyChartModal) weeklyChartModal.destroy();

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
                    const modalBody = weeklyStatsModalElement.querySelector('.modal-body');
                    modalBody.innerHTML = '<canvas id="weeklyChartModalCanvas" height="180"></canvas>';
                });
            }

            // --- NOTIFIKASI DESKTOP DAN NAVBAR ---
            function showDesktopNotification(title, body) {
                if (Notification.permission === "granted") {
                    new Notification(title, {
                        body: body,
                        icon: 'https://cdn-icons-png.flaticon.com/512/12101/12101890.png' // Icon robot sebagai contoh
                    });
                } else if (Notification.permission === "denied") {
                    // Tidak perlu alert jika ditolak, karena pengguna sudah tahu/menolak
                    console.warn("Notifikasi desktop diblokir oleh pengguna.");
                } else { // 'default'
                    Notification.requestPermission().then(permission => {
                        if (permission === "granted") {
                            new Notification(title, {
                                body: body,
                                icon: 'https://cdn-icons-png.flaticon.com/512/12101/12101890.png'
                            });
                        } else {
                            console.warn("Notifikasi desktop tidak diizinkan oleh pengguna.");
                        }
                    });
                }
            }

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
                        let hasNewNotification = false;
                        let pesanHtml = '<div class="list-group list-group-flush">';

                        const today = new Date();
                        const todayDateString = today.toISOString().split('T')[0]; // Format YYYY-MM-DD

                        // Filter dan tampilkan notifikasi di navbar & desktop
                        data.tugas.forEach(t => {
                            // Periksa tugas yang tepat pada tanggalnya (deadline)
                            const taskDeadlineDate = t.deadline ? t.deadline.split(' ')[0] : ''; // Ambil hanya tanggalnya (YYYY-MM-DD)

                            if (taskDeadlineDate === todayDateString && t.status !== 'selesai') {
                                hasNewNotification = true;
                                pesanHtml += '<div class="list-group-item list-group-item-action py-2 animate__animated animate__fadeInLeft">' +
                                    '<i class="fa-solid fa-bell text-warning me-2"></i>' +
                                    '<strong>Tugas Hari Ini: ' + esc(t.nama_tugas) + '</strong><br>' +
                                    '<small class="text-muted">Deadline: ' + esc(t.deadline) + '</small>' +
                                    '</div>';

                                // Tampilkan notifikasi desktop jika belum pernah di-notifikasi di sesi ini
                                if (Notification.permission === "granted" && !notifiedItems['task_' + t.id]) {
                                    showDesktopNotification('Tugas Hari Ini!', 'Jangan lupa: ' + t.nama_tugas);
                                    notifiedItems['task_' + t.id] = true; // Tandai sudah di-notifikasi
                                }
                            } else if (t.status !== 'selesai') { // Tugas yang mendekati deadline (masih penting di navbar)
                                pesanHtml += '<div class="list-group-item list-group-item-action py-2 animate__animated animate__fadeInLeft">' +
                                    '<i class="fa-solid fa-circle-exclamation text-danger me-2"></i>' +
                                    '<strong>Tugas Mendekati: ' + esc(t.nama_tugas) + '</strong><br>' +
                                    '<small class="text-muted">Deadline: ' + esc(t.deadline) + '</small>' +
                                    '</div>';
                            }
                        });

                        // Asumsi cek_notif.php juga bisa memberikan data acara, jika tidak ada sesuaikan
                        // Jika data.acara ada, tambahkan logika serupa untuk acara
                        if (data.acara) {
                            data.acara.forEach(a => {
                                const eventDate = a.tanggal ? a.tanggal : ''; // Tanggal acara (YYYY-MM-DD)
                                if (eventDate === todayDateString) {
                                    hasNewNotification = true;
                                    pesanHtml += '<div class="list-group-item list-group-item-action py-2 animate__animated animate__fadeInLeft">' +
                                        '<i class="fa-solid fa-calendar-day text-info me-2"></i>' +
                                        '<strong>Acara Hari Ini: ' + esc(a.nama_acara) + '</strong><br>' +
                                        '<small class="text-muted">Tanggal: ' + esc(a.tanggal) + '</small>' +
                                        '</div>';

                                    if (Notification.permission === "granted" && !notifiedItems['event_' + a.id]) {
                                        showDesktopNotification('Acara Hari Ini!', 'Ada acara: ' + a.nama_acara);
                                        notifiedItems['event_' + a.id] = true;
                                    }
                                }
                            });
                        }

                        pesanHtml += '</div>';

                        if (hasNewNotification) {
                            notifBadge.style.display = 'block';
                            notifArea.innerHTML = pesanHtml;
                        } else {
                            notifBadge.style.display = 'none';
                            notifArea.innerHTML = '<div class="text-muted text-center py-3">Tidak ada notifikasi baru.</div>';
                        }
                    })
                    .catch(error => console.error('Error fetching notifications:', error));
            }

            // Minta izin notifikasi saat halaman dimuat (jika status 'default')
            if (Notification.permission === "default") {
                Notification.requestPermission();
            }

            // Jalankan cekNotifikasiNavbar setiap 60 detik (atau sesuai kebutuhan)
            setInterval(cekNotifikasiNavbar, 60000); // Setiap 1 menit
            // Jalankan saat halaman dimuat pertama kali
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
            // JAVASCRIPT UNTUK RIWAYAT TUGAS & ACARA (HAPUS MASSAL)
            // ===========================================
            const historySelectAllCheckbox = $('#historySelectAllCheckbox');
            const deleteSelectedHistoryBtn = $('#deleteSelectedHistoryBtn');
            const historyCheckboxes = $('.history-checkbox'); // Semua checkbox di riwayat

            function updateDeleteSelectedButtonState() {
                const checkedCount = historyCheckboxes.filter(':checked').length;
                deleteSelectedHistoryBtn.prop('disabled', checkedCount === 0);
            }

            // Event listener untuk checkbox "Pilih Semua"
            historySelectAllCheckbox.on('change', function() {
                historyCheckboxes.prop('checked', this.checked);
                updateDeleteSelectedButtonState();
                if (this.checked) {
                    // Otomatis hapus semua jika pilih semua dicentang
                    deleteSelectedHistoryBtn.trigger('click');
                }
            });

            // Event listener untuk setiap checkbox riwayat
            historyCheckboxes.on('change', function() {
                updateDeleteSelectedButtonState();
                if (!this.checked) {
                    historySelectAllCheckbox.prop('checked', false);
                } else if (historyCheckboxes.length === historyCheckboxes.filter(':checked').length) {
                    historySelectAllCheckbox.prop('checked', true);
                }
            });

            // Event listener untuk tombol "Hapus Yang Dipilih"
            deleteSelectedHistoryBtn.on('click', function() {
                const selectedTaskIds = [];
                const selectedEventIds = [];
                $('.history-checkbox:checked').each(function() {
                    const id = parseInt($(this).attr('data-id'), 10);
                    const type = $(this).attr('data-type');
                    if (type === 'task') {
                        selectedTaskIds.push(id);
                    } else if (type === 'event') {
                        selectedEventIds.push(id);
                    }
                });

                if (selectedTaskIds.length === 0 && selectedEventIds.length === 0) {
                    alert('Tidak ada item yang dipilih untuk dihapus.');
                    return;
                }

                if (!confirm('Yakin ingin menghapus item-item yang dipilih dari riwayat?')) {
                    return;
                }

                let ajaxCallsToComplete = 0;
                let errorMessages = [];

                const handleCompletion = () => {
                    ajaxCallsToComplete--;
                    if (ajaxCallsToComplete === 0) {
                        if (errorMessages.length > 0) {
                            alert('Beberapa item gagal dihapus:\n' + errorMessages.join('\n'));
                        }
                        location.reload();
                    }
                };

                // Kirim AJAX hanya jika array berisi lebih dari 0 item
                if (selectedTaskIds.length > 0) {
                    ajaxCallsToComplete++;
                    $.ajax({
                        url: 'dashboard.php',
                        type: 'POST',
                        data: $.param({
                            action: 'delete_task',
                            'id[]': selectedTaskIds
                        }),
                        dataType: 'json',
                        success: function(response) {
                            if (!response.success) {
                                errorMessages.push(`Gagal menghapus tugas: ${response.message}`);
                            }
                            handleCompletion();
                        },
                        error: function(xhr, status, error) {
                            errorMessages.push(`Terjadi kesalahan saat menghapus tugas: ${error}`);
                            handleCompletion();
                        }
                    });
                }

                if (selectedEventIds.length > 0) {
                    ajaxCallsToComplete++;
                    $.ajax({
                        url: 'dashboard.php',
                        type: 'POST',
                        data: $.param({
                            action: 'delete_event',
                            'id[]': selectedEventIds
                        }),
                        dataType: 'json',
                        success: function(response) {
                            if (!response.success) {
                                errorMessages.push(`Gagal menghapus acara: ${response.message}`);
                            }
                            handleCompletion();
                        },
                        error: function(xhr, status, error) {
                            errorMessages.push(`Terjadi kesalahan saat menghapus acara: ${error}`);
                            handleCompletion();
                        }
                    });
                }
            });

            // Inisialisasi awal tombol Hapus Yang Dipilih
            updateDeleteSelectedButtonState();

            // Update status badge dan teks langsung saat checkbox diklik
            $(document).on('change', '.task-checkbox', function() {
                const taskId = $(this).data('task-id');
                const isChecked = $(this).is(':checked');
                const taskItem = $(this).closest('.list-group-item');
                const taskName = taskItem.find('h5');
                const statusBadge = taskItem.find('.status-badge');

                $.ajax({
                    url: 'dashboard.php',
                    type: 'POST',
                    data: {
                        action: 'toggle_task_status',
                        current_status: isChecked ? 'belum' : 'selesai',
                        task_id: taskId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (response.new_status === 'selesai') {
                                // 1. Hapus dari daftar tugas
                                taskItem.remove();
                                // 2. Tambahkan ke riwayat secara dinamis
                                const completedList = $('#completedTasksList');
                                // Buat elemen baru untuk riwayat
                                let historyItem = $('<li></li>')
                                    .addClass('list-group-item d-flex justify-content-between align-items-center shadow-sm mb-1 animate__animated animate__fadeInUp')
                                    .css('border-radius', '12px')
                                    .attr('data-id', taskId)
                                    .attr('data-type', 'task');
                                let contentDiv = $('<div></div>').addClass('history-item-content');
                                contentDiv.append('<h5 class="mb-0 text-decoration-line-through text-muted">' + esc(taskName.text()) + '</h5>');
                                // Ambil deadline dan deskripsi dari taskItem
                                let deadlineText = taskItem.find('p.small.text-muted').text();
                                let deskripsiText = taskItem.find('p.small').not('.text-muted').text();
                                if (deadlineText) {
                                    contentDiv.append('<p class="small text-muted mb-1">' + esc(deadlineText) + '</p>');
                                }
                                if (deskripsiText) {
                                    contentDiv.append('<p class="small">' + esc(deskripsiText) + '</p>');
                                }
                                contentDiv.append('<span class="status-badge status-selesai">Selesai</span>');
                                let actionsDiv = $('<div></div>').addClass('history-item-actions');
                                actionsDiv.append('<input type="checkbox" class="history-checkbox" data-id="' + taskId + '" data-type="task">');
                                historyItem.append(contentDiv).append(actionsDiv);
                                completedList.append(historyItem);
                                // Re-bind event listeners untuk checkbox baru
                                $('.history-checkbox').off('change').on('change', function() {
                                    updateDeleteSelectedButtonState();
                                    if (!this.checked) {
                                        historySelectAllCheckbox.prop('checked', false);
                                    } else if ($('.history-checkbox').length === $('.history-checkbox:checked').length) {
                                        historySelectAllCheckbox.prop('checked', true);
                                    }
                                });
                                updateDeleteSelectedButtonState();
                            } else {
                                // Jika status diubah ke belum, bisa tambahkan logika jika diperlukan
                            }
                        } else {
                            alert('Gagal memperbarui status tugas: ' + response.message);
                            $(this).prop('checked', !isChecked);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        alert('Terjadi kesalahan saat memperbarui status tugas.');
                        $(this).prop('checked', !isChecked);
                    }
                });
            });
        }); // END of $(document).ready(function() { ... });
    </script>
</body>

</html>