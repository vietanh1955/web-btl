<?php
// includes/header.php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$username_display = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? h($page_title) . ' - QLSV PTIT' : 'Hệ thống Quản lý Sinh viên' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css" media="print">
    <style>
        :root {
            --ptit-red: #b02a37;
            --ptit-dark-red: #8f1f2a;
        }
        body {
            background-color: #f8f9fa;
        }
        .sidebar-ptit {
            width: 250px;
            min-height: 100vh;
            background-color: var(--ptit-red);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }
        .sidebar-ptit .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            padding: 12px 20px;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .sidebar-ptit .nav-link:hover,
        .sidebar-ptit .nav-link.active {
            color: #ffffff !important;
            background-color: var(--ptit-dark-red);
            font-weight: 700;
        }
        .main-wrapper {
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .top-navbar {
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 24px;
        }
        @media (max-width: 768px) {
            .sidebar-ptit { position: static; width: 100%; min-height: auto; }
            .main-wrapper { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar dọc bên trái -->
<div class="sidebar-ptit d-flex flex-column no-print">
    <div class="px-4 py-3 border-bottom border-white border-opacity-25">
        <a class="navbar-brand fw-bold text-white fs-5" href="<?= BASE_URL ?>/<?= ($role === 'admin') ? 'admin/index.php' : 'student/index.php' ?>">
            QLSV PTIT
        </a>
    </div>

    <ul class="nav flex-column mb-auto py-2">
        <?php if ($role === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/index.php">Tổng quan</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($current_page, 'class') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/classes.php">Lớp học</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($current_page, 'subject') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/subjects.php">Môn học</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($current_page, 'student') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/students.php">Sinh viên</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($current_page, 'grade') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/grade_entry.php">Nhập điểm</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($current_page, 'report') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/reports.php">Báo cáo</a>
            </li>
        <?php elseif ($role === 'sinhvien'): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/index.php">Tổng quan</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($current_page, 'transcript') !== false) ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/transcript.php">Bảng điểm</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($current_page === 'profile.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/student/profile.php">Hồ sơ cá nhân</a>
            </li>
        <?php endif; ?>
    </ul>
</div>

<!-- Khung chứa nội dung bên phải -->
<div class="main-wrapper">
    <!-- Thanh trên cùng hiển thị tài khoản -->
    <div class="top-navbar d-flex justify-content-end align-items-center no-print">
        <div class="dropdown">
            <a class="dropdown-toggle text-dark text-decoration-none" href="#" role="button" data-bs-toggle="dropdown">
                Xin chào, <strong><?= h($username_display) ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/change_password.php">Đổi mật khẩu</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">Đăng xuất</a></li>
            </ul>
        </div>
    </div>

    <!-- Vùng nội dung của từng trang -->
    <div class="container-fluid px-4 py-4 content-wrapper flex-grow-1">