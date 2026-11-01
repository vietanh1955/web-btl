<?php
// admin/students.php - Quản lý Sinh viên tập trung (Full CRUD trong 1 file)
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

// ==========================================================
// 1. XỬ LÝ POST (CREATE / UPDATE / DELETE - MÔ HÌNH PRG)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    // --- CREATE: THÊM SINH VIÊN MỚI ---
    if ($action === 'add') {
        $ma_sv     = strtoupper(trim($_POST['ma_sv'] ?? ''));
        $ho_ten    = trim($_POST['ho_ten'] ?? '');
        $ngay_sinh = trim($_POST['ngay_sinh'] ?? '');
        $gioi_tinh = trim($_POST['gioi_tinh'] ?? 'Nam');
        $ma_lop    = trim($_POST['ma_lop'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $so_dt     = trim($_POST['so_dt'] ?? '');

        if ($ma_sv === '' || $ho_ten === '' || $ngay_sinh === '' || $ma_lop === '' || $email === '') {
            redirect("students.php?err=" . urlencode("Vui lòng điền đầy đủ các thông tin bắt buộc!"));
        }

        try {
            $pdo->beginTransaction();

            // 1. Thêm vào bảng sinhvien
            $stmt = $pdo->prepare("INSERT INTO sinhvien (ma_sv, ho_ten, ngay_sinh, gioi_tinh, ma_lop, email, so_dt) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ma_sv, $ho_ten, $ngay_sinh, $gioi_tinh, $ma_lop, $email, $so_dt]);

            // 2. Tạo tài khoản đăng nhập mặc định (Mật khẩu là ngày sinh ddmmyyyy)
            $dob_clean = date('dmY', strtotime($ngay_sinh));
            $pass_hash = password_hash($dob_clean, PASSWORD_DEFAULT);
            try {
                $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role, doi_mat_khau) VALUES (?, ?, 'sinhvien', 1)");
                $stmt_user->execute([$ma_sv, $pass_hash]);
            } catch (Exception $e) {
                // Bỏ qua nếu dự án không bắt buộc bảng users riêng
            }

            $pdo->commit();
            redirect("students.php?msg=" . urlencode("Thêm mới sinh viên $ma_sv thành công! Mật khẩu mặc định: $dob_clean"));
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = (strpos($e->getMessage(), 'Duplicate') !== false) 
                ? "Mã sinh viên '$ma_sv' hoặc Email đã tồn tại!" 
                : "Lỗi cơ sở dữ liệu: " . $e->getMessage();
            redirect("students.php?err=" . urlencode($msg));
        }
    }

    // --- UPDATE: CHỈNH SỬA THÔNG TIN ---
    if ($action === 'edit') {
        $ma_sv     = strtoupper(trim($_POST['ma_sv'] ?? ''));
        $ho_ten    = trim($_POST['ho_ten'] ?? '');
        $ngay_sinh = trim($_POST['ngay_sinh'] ?? '');
        $gioi_tinh = trim($_POST['gioi_tinh'] ?? 'Nam');
        $ma_lop    = trim($_POST['ma_lop'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $so_dt     = trim($_POST['so_dt'] ?? '');

        if ($ma_sv === '' || $ho_ten === '' || $ngay_sinh === '' || $ma_lop === '' || $email === '') {
            redirect("students.php?err=" . urlencode("Thông tin chỉnh sửa không hợp lệ!"));
        }

        try {
            $stmt = $pdo->prepare("UPDATE sinhvien SET ho_ten = ?, ngay_sinh = ?, gioi_tinh = ?, ma_lop = ?, email = ?, so_dt = ? WHERE ma_sv = ?");
            $stmt->execute([$ho_ten, $ngay_sinh, $gioi_tinh, $ma_lop, $email, $so_dt, $ma_sv]);
            redirect("students.php?msg=" . urlencode("Cập nhật thông tin sinh viên $ma_sv thành công!"));
        } catch (PDOException $e) {
            redirect("students.php?err=" . urlencode("Lỗi cơ sở dữ liệu: " . $e->getMessage()));
        }
    }

    // --- DELETE: XÓA SINH VIÊN ---
    if ($action === 'delete') {
        $ma_sv = trim($_POST['ma_sv'] ?? '');
        if ($ma_sv === '') {
            redirect("students.php?err=" . urlencode("Mã sinh viên không hợp lệ!"));
        }

        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM diem WHERE ma_sv = ?")->execute([$ma_sv]);
            try { $pdo->prepare("DELETE FROM email_log WHERE ma_sv = ?")->execute([$ma_sv]); } catch (Exception $e) {}
            $pdo->prepare("DELETE FROM sinhvien WHERE ma_sv = ?")->execute([$ma_sv]);
            try { $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$ma_sv]); } catch (Exception $e) {}

            $pdo->commit();
            redirect("students.php?msg=" . urlencode("Đã xóa hoàn toàn hồ sơ sinh viên $ma_sv!"));
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            redirect("students.php?err=" . urlencode("Lỗi cơ sở dữ liệu: " . $e->getMessage()));
        }
    }
}

// ==========================================================
// 2. TRUY VẤN DỮ LIỆU & PHÂN TRANG (READ)
// ==========================================================
$classes_list = $pdo->query("SELECT ma_lop, ten_lop FROM lop ORDER BY ma_lop ASC")->fetchAll();

$search       = trim($_GET['search'] ?? '');
$filter_class = trim($_GET['class'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 10;
$offset       = ($page - 1) * $limit;

$where_clauses = [];
$params        = [];

if ($search !== '') {
    $where_clauses[] = "(sv.ma_sv LIKE ? OR sv.ho_ten LIKE ? OR sv.email LIKE ? OR sv.so_dt LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($filter_class !== '') {
    $where_clauses[] = "sv.ma_lop = ?";
    $params[] = $filter_class;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM sinhvien sv $where_sql");
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total_rows / $limit);

$sql = "
    SELECT sv.*, l.ten_lop 
    FROM sinhvien sv 
    JOIN lop l ON sv.ma_lop = l.ma_lop 
    $where_sql 
    ORDER BY sv.ma_sv ASC 
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Sinh viên';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0 text-danger">QUẢN LÝ HỒ SƠ SINH VIÊN</h4>
    <div class="d-flex gap-2">
        <!-- Nút Xuất CSV xanh lá chuẩn -->
        <a href="export_csv.php?type=students&search=<?= urlencode($search) ?>&class=<?= urlencode($filter_class) ?>" 
           class="btn btn-success fw-bold">
            Xuất CSV
        </a>
        <!-- Nút Thêm mới mở Modal -->
        <button type="button" class="btn btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            + Thêm Sinh viên mới
        </button>
    </div>
</div>

<!-- Khối hiển thị thông báo -->
<?php if (isset($_GET['msg'])): ?><div id="alertBox" class="alert alert-success py-2 small"><?= h($_GET['msg']) ?></div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div id="alertBox" class="alert alert-danger py-2 small"><?= h($_GET['err']) ?></div><?php endif; ?>

<!-- Bộ lọc & Tìm kiếm -->
<div class="card mb-3 shadow-sm border-0">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Tìm theo Mã SV, Họ tên, Email, Số ĐT..." value="<?= h($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="class" class="form-select">
                    <option value="">-- Tất cả các lớp --</option>
                    <?php foreach ($classes_list as $cl): ?>
                        <option value="<?= h($cl['ma_lop']) ?>" <?= ($filter_class === $cl['ma_lop']) ? 'selected' : '' ?>>
                            <?= h($cl['ma_lop']) ?> - <?= h($cl['ten_lop']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-secondary fw-semibold">Lọc dữ liệu</button>
                <?php if ($search !== '' || $filter_class !== ''): ?>
                    <a href="students.php" class="btn btn-secondary fw-semibold">Đặt lại</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bảng Sinh viên: Chữ đen nét thường -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 text-center">
            <thead class="table-light">
                <tr>
                    <th style="width: 12%">Mã SV</th>
                    <th class="text-start">Họ và tên</th>
                    <th style="width: 11%">Ngày sinh</th>
                    <th style="width: 8%">Giới tính</th>
                    <th style="width: 12%">Lớp</th>
                    <th class="text-start">Email</th>
                    <th style="width: 11%">Số ĐT</th>
                    <th style="width: 18%">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="8" class="py-4 text-muted">Không tìm thấy sinh viên nào.</td></tr>
                <?php else: foreach ($students as $s): ?>
                    <tr>
                        <td class="text-dark"><?= h($s['ma_sv']) ?></td>
                        <td class="text-start">
                            <a href="student_detail.php?ma_sv=<?= urlencode($s['ma_sv']) ?>" class="text-decoration-none text-dark">
                                <?= h($s['ho_ten']) ?>
                            </a>
                        </td>
                        <td class="text-dark"><?= date('d/m/Y', strtotime($s['ngay_sinh'])) ?></td>
                        <td class="text-dark"><?= h($s['gioi_tinh']) ?></td>
                        <td class="text-dark"><?= h($s['ma_lop']) ?></td>
                        <td class="text-start text-dark"><?= h($s['email']) ?></td>
                        <td class="text-dark"><?= h($s['so_dt'] ?: '-') ?></td>
                        <td class="text-nowrap">
                            <!-- Nút Xem chi tiết chuyển sang student_detail.php -->
                            <a href="student_detail.php?ma_sv=<?= urlencode($s['ma_sv']) ?>" 
                               class="btn btn-sm fw-semibold me-1" 
                               style="background-color: #ffffff; color: #495057; border: 1px solid #ced4da;"
                               onmouseover="this.style.backgroundColor='#6c757d'; this.style.color='#ffffff';"
                               onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#495057';">
                                Xem
                            </a>

                            <!-- Nút Sửa mở Modal tại chỗ -->
                            <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold me-1 btn-edit-student"
                                    data-masv="<?= h($s['ma_sv']) ?>"
                                    data-hoten="<?= h($s['ho_ten']) ?>"
                                    data-ngaysinh="<?= h($s['ngay_sinh']) ?>"
                                    data-gioitinh="<?= h($s['gioi_tinh']) ?>"
                                    data-malop="<?= h($s['ma_lop']) ?>"
                                    data-email="<?= h($s['email']) ?>"
                                    data-sodt="<?= h($s['so_dt'] ?? '') ?>">
                                Sửa
                            </button>

                            <!-- Nút Xóa qua POST form -->
                            <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa sinh viên <?= h($s['ma_sv']) ?>? Thao tác này sẽ xóa đồng thời điểm thi và tài khoản liên quan!')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="ma_sv" value="<?= h($s['ma_sv']) ?>">
                                <button type="submit" class="btn btn-sm fw-semibold" 
                                        style="color: #dc3545 !important; background-color: #ffffff; border: 1px solid #dc3545;"
                                        onmouseover="this.style.color='#ffffff'; this.style.backgroundColor='#dc3545';"
                                        onmouseout="this.style.color='#dc3545'; this.style.backgroundColor='#ffffff';">
                                    Xóa
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Phân trang -->
<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&class=<?= urlencode($filter_class) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- ========================================== -->
<!-- MODAL THÊM SINH VIÊN MỚI                   -->
<!-- ========================================== -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">THÊM SINH VIÊN MỚI</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Mã sinh viên <span class="text-danger">*</span></label>
                    <input type="text" name="ma_sv" class="form-control text-uppercase" required placeholder="VD: B23DCAT001">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label>
                    <input type="text" name="ho_ten" class="form-control" required placeholder="VD: Nguyễn Văn A">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Ngày sinh <span class="text-danger">*</span></label>
                    <input type="date" name="ngay_sinh" class="form-control" required>
                    <small class="text-muted">Mật khẩu mặc định: DDMYYYY</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Giới tính <span class="text-danger">*</span></label>
                    <select name="gioi_tinh" class="form-select">
                        <option value="Nam">Nam</option>
                        <option value="Nu">Nữ</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Lớp hành chính <span class="text-danger">*</span></label>
                    <select name="ma_lop" class="form-select" required>
                        <option value="">-- Chọn lớp học --</option>
                        <?php foreach ($classes_list as $cl): ?>
                            <option value="<?= h($cl['ma_lop']) ?>">
                                <?= h($cl['ma_lop']) ?> - <?= h($cl['ten_lop']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Email liên lạc <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required placeholder="email@domain.com">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">Số điện thoại</label>
                    <input type="text" name="so_dt" class="form-control" placeholder="09xxxxxxxx">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-danger fw-bold px-4">Lưu Sinh viên</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL CHỈNH SỬA SINH VIÊN                  -->
<!-- ========================================== -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="ma_sv" id="edit_ma_sv">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">CHỈNH SỬA SINH VIÊN: <span id="edit_display_masv" class="text-danger"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Họ và tên <span class="text-danger">*</span></label>
                    <input type="text" name="ho_ten" id="edit_ho_ten" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Ngày sinh <span class="text-danger">*</span></label>
                    <input type="date" name="ngay_sinh" id="edit_ngay_sinh" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Giới tính</label>
                    <select name="gioi_tinh" id="edit_gioi_tinh" class="form-select">
                        <option value="Nam">Nam</option>
                        <option value="Nu">Nữ</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Lớp hành chính <span class="text-danger">*</span></label>
                    <select name="ma_lop" id="edit_ma_lop" class="form-select" required>
                        <?php foreach ($classes_list as $cl): ?>
                            <option value="<?= h($cl['ma_lop']) ?>">
                                <?= h($cl['ma_lop']) ?> - <?= h($cl['ten_lop']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Số điện thoại</label>
                    <input type="text" name="so_dt" id="edit_so_dt" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark px-4">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editModalEl = document.getElementById('editStudentModal');
    if (editModalEl && typeof bootstrap !== 'undefined') {
        const editModal = new bootstrap.Modal(editModalEl);
        document.querySelectorAll('.btn-edit-student').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_ma_sv').value = this.dataset.masv;
                document.getElementById('edit_display_masv').textContent = this.dataset.masv;
                document.getElementById('edit_ho_ten').value = this.dataset.hoten;
                document.getElementById('edit_ngay_sinh').value = this.dataset.ngaysinh;
                document.getElementById('edit_gioi_tinh').value = this.dataset.gioitinh;
                document.getElementById('edit_ma_lop').value = this.dataset.malop;
                document.getElementById('edit_email').value = this.dataset.email;
                document.getElementById('edit_so_dt').value = this.dataset.sodt;
                editModal.show();
            });
        });
    }

    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
        if (window.history.replaceState) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({path: cleanUrl}, '', cleanUrl);
        }
        setTimeout(() => {
            alertBox.style.transition = 'opacity 0.5s ease';
            alertBox.style.opacity = '0';
            setTimeout(() => alertBox.remove(), 500);
        }, 3000);
    }
});
</script>