<?php
// student/profile.php
require_once __DIR__ . '/../includes/functions.php';
require_role('sinhvien');

$ma_sv = $_SESSION['ma_sv'] ?? '';
if ($ma_sv === '') {
    redirect(BASE_URL . '/logout.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $so_dt   = trim($_POST['so_dt'] ?? '');
    $dia_chi = trim($_POST['dia_chi'] ?? '');

    try {
        $upd = $pdo->prepare("UPDATE sinhvien SET so_dt = ?, dia_chi = ? WHERE ma_sv = ?");
        $upd->execute([$so_dt, $dia_chi, $ma_sv]);
        $success = 'Đã cập nhật thông tin liên hệ thành công!';
    } catch (PDOException $e) {
        $error = 'Lỗi cập nhật: ' . $e->getMessage();
    }
}

$stmt = $pdo->prepare("
    SELECT sv.*, l.ten_lop, l.chuyen_nganh AS nganh, l.khoa_hoc
    FROM sinhvien sv
    JOIN lop l ON sv.ma_lop = l.ma_lop
    WHERE sv.ma_sv = ?
");
$stmt->execute([$ma_sv]);
$student = $stmt->fetch();

$page_title = 'Hồ sơ cá nhân';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white fw-bold">HỒ SƠ CÁ NHÂN SINH VIÊN</div>
            <div class="card-body p-4">
                <?php if ($error !== ''): ?><div class="alert alert-danger py-2 small"><?= h($error) ?></div><?php endif; ?>
                <?php if ($success !== ''): ?><div class="alert alert-success py-2 small"><?= h($success) ?></div><?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>
                    <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">1. THÔNG TIN CỐ ĐỊNH (CHỈ XEM)</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Mã sinh viên</label>
                            <input type="text" class="form-control bg-light" value="<?= h($student['ma_sv']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Họ và tên</label>
                            <input type="text" class="form-control bg-light" value="<?= h($student['ho_ten']) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Ngày sinh</label>
                            <input type="text" class="form-control bg-light" value="<?= date('d/m/Y', strtotime($student['ngay_sinh'])) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Giới tính</label>
                            <input type="text" class="form-control bg-light" value="<?= h($student['gioi_tinh']) ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted">Lớp học</label>
                            <input type="text" class="form-control bg-light" value="<?= h($student['ma_lop']) ?> (<?= h($student['ten_lop']) ?>)" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Ngành đào tạo</label>
                            <input type="text" class="form-control bg-light" value="<?= h($student['nganh']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Email liên lạc</label>
                            <input type="text" class="form-control bg-light" value="<?= h($student['email']) ?>" readonly>
                        </div>
                    </div>

                    <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">2. THÔNG TIN LIÊN HỆ ĐƯỢC PHÉP CHỈNH SỬA</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Số điện thoại</label>
                            <input type="text" name="so_dt" class="form-control" placeholder="09xxxxxxxx" value="<?= h($student['so_dt'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Địa chỉ nơi ở hiện tại</label>
                            <textarea name="dia_chi" class="form-control" rows="3" placeholder="Nhập địa chỉ cư trú hiện tại..."><?= h($student['dia_chi'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-danger px-4 fw-bold">Lưu thông tin liên hệ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>