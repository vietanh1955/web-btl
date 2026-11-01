<?php
// change_password.php
require_once __DIR__ . '/includes/functions.php';

if (empty($_SESSION['user_id'])) {
    redirect(BASE_URL . '/login.php');
}

$error   = '';
$success = '';
$is_forced = (!empty($_SESSION['doi_mat_khau']) && (int)$_SESSION['doi_mat_khau'] === 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if ($current_pass === '' || $new_pass === '' || $confirm_pass === '') {
        $error = 'Vui lòng điền đầy đủ cả 3 trường mật khẩu!';
    } elseif (strlen($new_pass) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự!';
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } elseif ($current_pass === $new_pass) {
        $error = 'Mật khẩu mới phải khác mật khẩu hiện tại!';
    } else {
        $stmt = $pdo->prepare("SELECT password FROM taikhoan WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_hash = $stmt->fetchColumn();

        if (!$current_hash || !password_verify($current_pass, $current_hash)) {
            $error = 'Mật khẩu hiện tại không đúng!';
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE taikhoan SET password = ?, doi_mat_khau = 0 WHERE id = ?");
            $upd->execute([$new_hash, $_SESSION['user_id']]);

            $_SESSION['doi_mat_khau'] = 0;
            $is_forced = false;
            $success = 'Đã cập nhật mật khẩu mới thành công!';
        }
    }
}

$page_title = 'Đổi mật khẩu';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center mt-4">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white fw-bold">THAY ĐỔI MẬT KHẨU CÁ NHÂN</div>
            <div class="card-body p-4">
                <?php if ($is_forced): ?>
                    <div class="alert alert-warning py-2 small">
                        <strong>Bắt buộc:</strong> Vì lý do bảo mật, bạn cần đổi mật khẩu mặc định trong lần đăng nhập đầu tiên trước khi sử dụng hệ thống.
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
                <?php endif; ?>

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success py-2 small"><?= h($success) ?></div>
                    <div class="text-center mt-3">
                        <a href="<?= ($_SESSION['role'] === 'admin') ? BASE_URL . '/admin/index.php' : BASE_URL . '/student/index.php' ?>" class="btn btn-success fw-bold px-4">
                            Tiếp tục vào Hệ thống &raquo;
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mật khẩu hiện tại</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mật khẩu mới (tối thiểu 6 ký tự)</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nhập lại mật khẩu mới</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <?php if (!$is_forced): ?>
                                <a href="<?= ($_SESSION['role'] === 'admin') ? BASE_URL . '/admin/index.php' : BASE_URL . '/student/index.php' ?>" class="btn btn-outline-secondary">Hủy</a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger">Đăng xuất</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-danger fw-bold px-4">Lưu mật khẩu</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>