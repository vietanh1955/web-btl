<?php
// admin/student_detail.php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$ma_sv = trim($_GET['ma_sv'] ?? '');
if ($ma_sv === '') {
    redirect('students.php');
}

$stmt = $pdo->prepare("
    SELECT sv.*, l.ten_lop, l.chuyen_nganh, l.khoa_hoc
    FROM sinhvien sv
    JOIN lop l ON sv.ma_lop = l.ma_lop
    WHERE sv.ma_sv = ?
");
$stmt->execute([$ma_sv]);
$student = $stmt->fetch();

if (!$student) {
    redirect("students.php?err=" . urlencode("Không tìm thấy sinh viên!"));
}

// Xử lý Reset Mật khẩu theo mô hình PRG (Post/Redirect/Get) để không bị popup Resubmit khi F5
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reset_pwd'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    try {
        $default_raw_pass = date('dmY', strtotime($student['ngay_sinh']));
        $new_hash = password_hash($default_raw_pass, PASSWORD_DEFAULT);

        $upd = $pdo->prepare("UPDATE taikhoan SET password = ?, doi_mat_khau = 1 WHERE ma_sv = ?");
        $upd->execute([$new_hash, $ma_sv]);
        
        // Chuyển hướng sang GET để làm sạch request POST
        redirect("student_detail.php?ma_sv=" . urlencode($ma_sv) . "&reset_success=1");
    } catch (PDOException $e) {
        redirect("student_detail.php?ma_sv=" . urlencode($ma_sv) . "&reset_err=" . urlencode($e->getMessage()));
    }
}

// Nhận thông báo từ GET
$reset_msg = isset($_GET['reset_success']) ? "Đã đặt lại về mật khẩu mặc định!" : '';
$reset_err = isset($_GET['reset_err']) ? "Lỗi: " . $_GET['reset_err'] : '';

// Tính toán GPA tích lũy
$academic = calculate_student_gpa($pdo, $ma_sv);

// Lấy toàn bộ điểm thi gom theo từng học kỳ
$stmtGrades = $pdo->prepare("
    SELECT d.*, mh.ten_mh, mh.so_tin_chi 
    FROM diem d 
    JOIN mon_hoc mh ON d.ma_mh = mh.ma_mh 
    WHERE d.ma_sv = ? 
    ORDER BY d.hoc_ky ASC, mh.ma_mh ASC
");
$stmtGrades->execute([$ma_sv]);
$raw_grades = $stmtGrades->fetchAll();

$semesters = [];
foreach ($raw_grades as $rg) {
    $semesters[$rg['hoc_ky']][] = $rg;
}

$page_title = 'Hồ sơ sinh viên: ' . $student['ho_ten'];
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Thanh điều hướng và nút thao tác -->
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <!-- Nút quay lại: Nền trắng, chỉ hiện màu đỏ khi hover -->
    <a href="students.php" class="btn fw-semibold" style="background-color: #ffffff; color: #dc3545; border: 1px solid #dc3545;" 
    onmouseover="this.style.backgroundColor='#dc3545'; this.style.color='#ffffff';" 
    onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#dc3545';">
        &laquo; Quay lại danh sách
    </a>
    
    <!-- Cụm nút đồng bộ màu đỏ PTIT -->
    <div class="d-flex gap-2">
        <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn đặt lại mật khẩu của sinh viên này về mặc định?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action_reset_pwd" value="1">
            <button type="submit" class="btn btn-danger fw-semibold">
                Đặt lại mật khẩu
            </button>
        </form>
    </div>
</div>

<!-- KHỐI THÔNG BÁO CÓ TIMER 3S TỰ TẮT -->
<?php if ($reset_msg !== ''): ?>
    <div id="alertBox" class="alert alert-success py-2 no-print small">
        <?= h($reset_msg) ?>
    </div>
<?php endif; ?>

<?php if ($reset_err !== ''): ?>
    <div id="alertBox" class="alert alert-danger py-2 no-print small">
        <?= h($reset_err) ?>
    </div>
<?php endif; ?>

<!-- Thông tin cá nhân -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-danger text-white fw-bold">THÔNG TIN HỒ SƠ SINH VIÊN</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th style="width: 35%">Mã sinh viên:</th><td class="fw-primary"><?= h($student['ma_sv']) ?></td></tr>
                    <tr><th>Họ và tên:</th><td class="fw-primary"><?= h($student['ho_ten']) ?></td></tr>
                    <tr><th>Ngày sinh:</th><td><?= date('d/m/Y', strtotime($student['ngay_sinh'])) ?></td></tr>
                    <tr><th>Giới tính:</th><td><?= h($student['gioi_tinh']) ?></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th style="width: 35%">Lớp học:</th><td><?= h($student['ma_lop']) ?> - <?= h($student['ten_lop']) ?></td></tr>
                    <tr><th>Ngành học:</th><td><?= h($student['chuyen_nganh']) ?> (Khóa <?= (int)$student['khoa_hoc'] ?>)</td></tr>
                    <tr><th>Email:</th><td><?= h($student['email']) ?></td></tr>
                    <tr><th>Số điện thoại:</th><td><?= h($student['so_dt'] ?: '-') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 3 Thẻ tóm tắt GPA (Viền đỏ đồng nhất) -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-start border-danger border-4 text-center">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">GPA TÍCH LŨY (HỆ 4)</div>
                <h3 class="fw-bold text-danger mb-0"><?= number_format($academic['gpa'], 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-start border-danger border-4 text-center">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">XẾP LOẠI HỌC LỰC</div>
                <h3 class="fw-bold text-success mb-0"><?= h($academic['xep_loai']) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-start border-danger border-4 text-center">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">TÍN CHỈ TÍCH LŨY ĐẠT</div>
                <h3 class="fw-bold text-dark mb-0"><?= (int)$academic['tin_chi_tich_luy'] ?> / <?= (int)$academic['tong_tin_chi'] ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Bảng điểm chi tiết gom theo kỳ -->
<h5 class="fw-bold mb-3">KẾT QUẢ HỌC TẬP CHI TIẾT THEO HỌC KỲ</h5>

<?php if (empty($semesters)): ?>
    <div class="alert alert-info">Sinh viên chưa có dữ liệu điểm học phần nào.</div>
<?php else: foreach ($semesters as $sem => $sub_list): 
    $sem_calc = calculate_student_gpa($pdo, $ma_sv, $sem);
?>
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark">HỌC KỲ: <?= h($sem) ?></span>
            <span>
                GPA kỳ: <strong class="text-danger"><?= number_format($sem_calc['gpa'], 2) ?></strong> 
                | Xếp loại: <strong><?= h($sem_calc['xep_loai']) ?></strong>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th style="width: 12%">Mã môn</th>
                        <th class="text-start">Tên môn học</th>
                        <th style="width: 8%">Số TC</th>
                        <th style="width: 10%">Chuyên cần</th>
                        <th style="width: 10%">Giữa kỳ</th>
                        <th style="width: 10%">Cuối kỳ</th>
                        <th style="width: 10%">Điểm tổng</th>
                        <th style="width: 10%">Điểm chữ</th>
                        <th style="width: 8%">Hệ 4</th>
                        <th style="width: 10%">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sub_list as $row): 
                        $conv = convert_grade($row['diem_tong']);
                    ?>
                    <tr class="<?= !$conv['dat'] ? 'table-danger' : '' ?>">
                        <td class="fw-bold"><?= h($row['ma_mh']) ?></td>
                        <td class="text-start"><?= h($row['ten_mh']) ?></td>
                        <td><?= (int)$row['so_tin_chi'] ?></td>
                        <td><?= number_format($row['diem_chuyen_can'], 2) ?></td>
                        <td><?= number_format($row['diem_giua_ky'], 2) ?></td>
                        <td><?= number_format($row['diem_cuoi_ky'], 2) ?></td>
                        <td class="fw-bold"><?= number_format($row['diem_tong'], 2) ?></td>
                        <td class="fw-bold"><?= h($conv['chu']) ?></td>
                        <td><?= number_format($conv['he4'], 1) ?></td>
                        <td>
                            <span class="badge <?= $conv['dat'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $conv['dat'] ? 'Đạt' : 'Học lại' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; endif; ?>

<!-- Timer 3s làm mờ thông báo + Xóa param trên URL để F5 không bị hỏi resubmit -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
        // 1. Dọn sạch URL ngay lập tức (loại bỏ ?reset_success=1)
        if (window.history.replaceState) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?ma_sv=<?= urlencode($ma_sv) ?>';
            window.history.replaceState({path: cleanUrl}, '', cleanUrl);
        }

        // 2. Chờ 3 giây rồi làm mờ dần và gỡ bỏ alert
        setTimeout(function () {
            alertBox.style.transition = 'opacity 0.5s ease';
            alertBox.style.opacity = '0';
            setTimeout(function () {
                alertBox.remove();
            }, 500);
        }, 3000);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>