<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_login(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }

    // Ràng buộc: Nếu tài khoản có cờ doi_mat_khau = 1 thì ép vào trang đổi mật khẩu
    $current_script = basename($_SERVER['PHP_SELF']);
    if (!empty($_SESSION['doi_mat_khau']) && (int)$_SESSION['doi_mat_khau'] === 1) {
        if ($current_script !== 'change_password.php' && $current_script !== 'logout.php') {
            header("Location: " . BASE_URL . "/change_password.php");
            exit;
        }
    }
}

function require_role(string $required_role): void {
    check_login();
    if ($_SESSION['role'] !== $required_role) {
        if ($_SESSION['role'] === 'admin') {
            header("Location: " . BASE_URL . "/admin/index.php");
        } else {
            header("Location: " . BASE_URL . "/student/index.php");
        }
        exit;
    }
}