<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $message = "Semua field harus diisi.";
        $message_type = 'danger';
    } elseif ($password !== $confirm_password) {
        $message = "Konfirmasi password tidak cocok.";
        $message_type = 'danger';
    } elseif (strlen($password) < 6) { // Contoh: minimal 6 karakter
        $message = "Password minimal 6 karakter.";
        $message_type = 'danger';
    } else {
        if (register($username, $password)) {
            $message = "Registrasi berhasil! Silakan login.";
            $message_type = 'success';
        } else {
            $message = "Username sudah ada atau terjadi kesalahan.";
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Fokus & Selesai</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        :root {
            --primary-color: #007bff;
            --primary-color-darker: #0056b3;
            --secondary-color: #6c757d;
            --background-gradient-start: #6a11cb;
            --background-gradient-end: #2575fc;
            --card-background: #ffffff;
            --text-color-dark: #333;
            --text-color-light: #666;
            --border-color: #e9ecef;
            --shadow-light: 0 5px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 10px 30px rgba(0,0,0,0.2);
        }
        body {
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(to right, var(--background-gradient-start) 0%, var(--background-gradient-end) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .register-container {
            background-color: var(--card-background);
            padding: 40px;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.8s ease-out;
            transform: translateY(0);
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
            color: var(--text-color-dark);
        }
        .register-header h2 {
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        .register-header h2 i {
            animation: bounceIn 1s ease-out;
        }
        .register-header p {
            color: var(--text-color-light);
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
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
            background-color: var(--primary-color-darker);
            border-color: var(--primary-color-darker);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
        }
        .alert {
            margin-top: 20px;
            border-radius: 10px;
            animation: fadeInDown 0.5s ease-out;
            padding: 15px;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.95rem;
        }
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease, text-decoration 0.3s ease;
        }
        .login-link a:hover {
            text-decoration: underline;
            color: var(--primary-color-darker);
        }

        /* Animations (same as login.php for consistency) */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes bounceIn {
            0%, 20%, 40%, 60%, 80%, 100% {
                -webkit-animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
                animation-timing-function: cubic-bezier(0.215, 0.610, 0.355, 1.000);
            }
            0% {
                opacity: 0;
                -webkit-transform: scale3d(.3, .3, .3);
                transform: scale3d(.3, .3, .3);
            }
            20% {
                -webkit-transform: scale3d(1.1, 1.1, 1.1);
                transform: scale3d(1.1, 1.1, 1.1);
            }
            40% {
                -webkit-transform: scale3d(.9, .9, .9);
                transform: scale3d(.9, .9, .9);
            }
            60% {
                opacity: 1;
                -webkit-transform: scale3d(1.03, 1.03, 1.03);
                transform: scale3d(1.03, 1.03, 1.03);
            }
            80% {
                -webkit-transform: scale3d(.97, .97, .97);
                transform: scale3d(.97, .97, .97);
            }
            100% {
                opacity: 1;
                -webkit-transform: scale3d(1, 1, 1);
                transform: scale3d(1, 1, 1);
            }
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="register-container animate__animated animate__fadeIn">
        <div class="register-header">
            <h2 class="animate__animated animate__bounceIn"><i class="fa-solid fa-user-plus"></i> Buat Akun Baru</h2>
            <p>Daftar sekarang untuk mulai fokus!</p>
        </div>
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= esc($message_type) ?> animate__animated animate__fadeInDown" role="alert">
                <?= esc($message) ?>
            </div>
        <?php endif; ?>
        <form action="register.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Daftar</button>
            </div>
        </form>
        <div class="login-link">
            Sudah punya akun? <a href="login.php">Login di sini</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>