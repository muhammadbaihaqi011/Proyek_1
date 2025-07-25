<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    // Jika sudah login, cek apakah user adalah admin sebelum redirect
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
        redirect('admin_users.php'); // Redirect ke halaman admin
    } else {
        redirect('dashboard.php'); // Redirect ke dashboard biasa
    }
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login($username, $password)) {
        // Login berhasil, sekarang cek user_id untuk redirect
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
            redirect('admin_users.php'); // Redirect ke halaman admin jika user_id adalah 1
        } else {
            redirect('dashboard.php'); // Redirect ke dashboard biasa untuk user_id lainnya
        }
    } else {
        $error_message = "Username atau password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fokus & Selesai</title>
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
        .login-container {
            background-color: var(--card-background);
            padding: 40px;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.8s ease-out;
            transform: translateY(0); /* Ensure no initial transform for animation */
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
            color: var(--text-color-dark);
        }
        .login-header h2 {
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        .login-header h2 i {
            animation: bounceIn 1s ease-out;
        }
        .login-header p {
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
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.95rem;
        }
        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease, text-decoration 0.3s ease;
        }
        .register-link a:hover {
            text-decoration: underline;
            color: var(--primary-color-darker);
        }

        /* Animations */
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
    <div class="login-container animate__animated animate__fadeIn">
        <div class="login-header">
            <h2 class="animate__animated animate__bounceIn"><i class="fa-solid fa-calendar-check"></i> Fokus & Selesai</h2>
            <p>Silakan login untuk melanjutkan</p>
        </div>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger animate__animated animate__fadeInDown" role="alert">
                <?= esc($error_message) ?>
            </div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Login</button>
            </div>
        </form>
        <div class="register-link">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>