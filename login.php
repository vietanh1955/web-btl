<?php
// login.php
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirect(BASE_URL . '/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ Tên đăng nhập và Mật khẩu!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM taikhoan WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']      = (int)$user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['role']         = $user['role'];
            $_SESSION['ma_sv']        = $user['ma_sv'];
            $_SESSION['doi_mat_khau'] = (int)$user['doi_mat_khau'];

            if ((int)$user['doi_mat_khau'] === 1) {
                redirect(BASE_URL . '/change_password.php');
            }

            if ($user['role'] === 'admin') {
                redirect(BASE_URL . '/admin/index.php');
            } else {
                redirect(BASE_URL . '/student/index.php');
            }
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Hệ thống Quản lý Sinh viên PTIT</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body {
            background-color: #e9ecef;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            width: 100%;
            max-width: 420px;
        }
    </style>
</head>
<body>

<div class="login-box px-3">
    <div class="card shadow border-0 p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-danger mb-1">QLSV PTIT</h3>
            <p class="text-muted small mb-0">Hệ thống Quản lý Hồ sơ & Điểm Sinh viên</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-bold">Tên đăng nhập</label>
                <input type="text" name="username" class="form-control" placeholder="Nhập Mã SV hoặc admin" required autofocus value="<?= h($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Mật khẩu</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-danger w-100 fw-bold py-2 mt-2">ĐĂNG NHẬP</button>
        </form>
    </div>
</div>

</body>
</html>