<?php
session_start();
require_once __DIR__ . '/../config/db.php';

function login($username, $password) {
    global $conn;
    $stmt = $conn->prepare('SELECT id, password FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            return true;
        }
    }
    return false;
}

function register($username, $password) {
    global $conn;
    // Cek apakah username sudah ada
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return false; // Username sudah terdaftar
    }
    $stmt->close();

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Masukkan pengguna baru ke database
    $stmt = $conn->prepare('INSERT INTO users (username, password, created_at) VALUES (?, ?, NOW())');
    $stmt->bind_param('ss', $username, $hash);
    
    if ($stmt->execute()) {
        $stmt->close();
        return true; // Registrasi berhasil
    } else {
        error_log("Error during user registration: " . $conn->error); // Log error
        $stmt->close();
        return false; // Registrasi gagal
    }
}


function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fungsi untuk mendapatkan username pengguna yang sedang login
function get_username() {
    global $conn;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT username FROM users WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                return $row['username'];
            }
            $stmt->close();
        } else {
            error_log("Error preparing get_username query: " . $conn->error);
        }
    }
    return 'Pengguna'; // Default jika tidak ditemukan atau tidak login
}

// Fungsi untuk mendapatkan ID pengguna yang sedang login
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}
?>