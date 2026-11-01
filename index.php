<?php
// index.php
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    if (!empty($_SESSION['doi_mat_khau']) && (int)$_SESSION['doi_mat_khau'] === 1) {
        redirect(BASE_URL . '/change_password.php');
    }
    if ($_SESSION['role'] === 'admin') {
        redirect(BASE_URL . '/admin/index.php');
    } else {
        redirect(BASE_URL . '/student/index.php');
    }
} else {
    redirect(BASE_URL . '/login.php');
}