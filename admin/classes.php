<?php
// admin/classes.php - Single-file CRUD hoàn chỉnh
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

// ==========================================================
// 1. XỬ LÝ POST (CREATE / UPDATE / DELETE - MÔ HÌNH PRG)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    // --- CREATE: THÊM LỚP MỚI ---
    if ($action === 'add') {
        $ma_lop       = strtoupper(trim($_POST['ma_lop'] ?? ''));
        $ten_lop      = trim($_POST['ten_lop'] ?? '');
        $chuyen_nganh = trim($_POST['chuyen_nganh'] ?? '');
        $khoa_hoc     = (int)($_POST['khoa_hoc'] ?? 0);

        if ($ma_lop === '' || $ten_lop === '' || $khoa_hoc <= 1900) {
            redirect("classes.php?err=" . urlencode("Vui lòng điền đầy đủ và chính xác thông tin lớp học!"));
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO lop (ma_lop, ten_lop, chuyen_nganh, khoa_hoc) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ma_lop, $ten_lop, $chuyen_nganh, $khoa_hoc]);
            redirect("classes.php?msg=" . urlencode("Thêm mới lớp $ma_lop thành công!"));
        } catch (PDOException $e) {
            $msg = (strpos($e->getMessage(), 'Duplicate') !== false) ? "Mã lớp '$ma_lop' đã tồn tại!" : "Lỗi: " . $e->getMessage();
            redirect("classes.php?err=" . urlencode($msg));
        }
    }

    // --- UPDATE: CẬP NHẬT LỚP ---
    if ($action === 'edit') {
        $ma_lop       = strtoupper(trim($_POST['ma_lop'] ?? ''));
        $ten_lop      = trim($_POST['ten_lop'] ?? '');
        $chuyen_nganh = trim($_POST['chuyen_nganh'] ?? '');
        $khoa_hoc     = (int)($_POST['khoa_hoc'] ?? 0);

        if ($ma_lop === '' || $ten_lop === '' || $khoa_hoc <= 1900) {
            redirect("classes.php?err=" . urlencode("Thông tin chỉnh sửa không hợp lệ!"));
        }

        try {
            $stmt = $pdo->prepare("UPDATE lop SET ten_lop = ?, chuyen_nganh = ?, khoa_hoc = ? WHERE ma_lop = ?");
            $stmt->execute([$ten_lop, $chuyen_nganh, $khoa_hoc, $ma_lop]);
            redirect("classes.php?msg=" . urlencode("Cập nhật lớp $ma_lop thành công!"));
        } catch (PDOException $e) {
            redirect("classes.php?err=" . urlencode("Lỗi cập nhật: " . $e->getMessage()));
        }
    }

    // --- DELETE: XÓA LỚP ---
if ($action === 'delete') {
        $ma_lop = trim($_POST['ma_lop'] ?? '');

        // Lấy số lượng sinh viên trực thuộc
        $check_sv = $pdo->prepare("SELECT COUNT(*) FROM sinhvien WHERE ma_lop = ?");
        $check_sv->execute([$ma_lop]);
        $so_sv = (int)$check_sv->fetchColumn();

        if ($so_sv > 0) {
            redirect("classes.php?err=" . urlencode("Không thể xóa lớp $ma_lop vì đang có $so_sv sinh viên trực thuộc!"));
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM lop WHERE ma_lop = ?");
            $stmt->execute([$ma_lop]);
            redirect("classes.php?msg=" . urlencode("Đã xóa lớp $ma_lop thành công!"));
        } catch (PDOException $e) {
            redirect("classes.php?err=" . urlencode("Lỗi cơ sở dữ liệu: " . $e->getMessage()));
        }
    }
}

// ==========================================================
// 2. TRUY VẤN DỮ LIỆU & PHÂN TRANG (READ)
// ==========================================================
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$params = [];
$where  = "";
if ($search !== '') {
    $where  = "WHERE l.ma_lop LIKE ? OR l.ten_lop LIKE ? OR l.chuyen_nganh LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM lop l $where");
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total_rows / $limit);

$sql = "
    SELECT l.*, COUNT(sv.ma_sv) AS si_so 
    FROM lop l 
    LEFT JOIN sinhvien sv ON l.ma_lop = sv.ma_lop 
    $where
    GROUP BY l.ma_lop, l.ten_lop, l.chuyen_nganh, l.khoa_hoc 
    ORDER BY l.ma_lop ASC 
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Lớp học';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0 text-danger">DANH SÁCH LỚP HỌC</h4>
    <button type="button" class="btn btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#addClassModal">
        + Thêm Lớp học mới
    </button>
</div>

<!-- Thông báo tự tắt sau 3s -->
<?php if (isset($_GET['msg'])): ?><div id="alertBox" class="alert alert-success py-2 small"><?= h($_GET['msg']) ?></div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div id="alertBox" class="alert alert-danger py-2 small"><?= h($_GET['err']) ?></div><?php endif; ?>

<!-- Tìm kiếm & Lọc -->
<div class="card mb-3 shadow-sm border-0">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1">
                <input type="text" name="search" class="form-control" placeholder="Tìm theo Mã lớp, Tên lớp, Chuyên ngành..." value="<?= h($search) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary fw-semibold">Tìm kiếm</button>
                <?php if ($search !== ''): ?>
                    <a href="classes.php" class="btn btn-secondary fw-semibold">Xóa lọc</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bảng Lớp học: Chữ đen thường, không badge, không bold -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 text-center">
            <thead class="table-light">
                <tr>
                    <th style="width: 15%">Mã lớp</th>
                    <th class="text-start">Tên lớp</th>
                    <th class="text-start">Chuyên ngành</th>
                    <th style="width: 12%">Khóa tuyển sinh</th>
                    <th style="width: 10%">Sĩ số</th>
                    <th style="width: 18%">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classes)): ?>
                    <tr><td colspan="6" class="py-4 text-muted">Không tìm thấy lớp học nào.</td></tr>
                <?php else: foreach ($classes as $c): ?>
                    <tr>
                        <td class="text-dark"><?= h($c['ma_lop']) ?></td>
                        <td class="text-start text-dark"><?= h($c['ten_lop']) ?></td>
                        <td class="text-start text-dark"><?= h($c['chuyen_nganh']) ?></td>
                        <td class="text-dark"><?= (int)$c['khoa_hoc'] ?></td>
                        <td class="text-dark"><?= (int)$c['si_so'] ?></td>
                        <td class="text-nowrap">
                            <!-- Nút Sửa -->
                            <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold me-1 btn-edit"
                                    data-malop="<?= h($c['ma_lop']) ?>"
                                    data-tenlop="<?= h($c['ten_lop']) ?>"
                                    data-chuyennganh="<?= h($c['chuyen_nganh']) ?>"
                                    data-khoahoc="<?= (int)$c['khoa_hoc'] ?>">Sửa</button>

                            <!-- Nút Xóa qua POST ngay tại trang -->
                            <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa lớp <?= h($c['ma_lop']) ?>?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="ma_lop" value="<?= h($c['ma_lop']) ?>">
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
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Modal Thêm Lớp -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">Thêm Lớp học mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Mã lớp <span class="text-danger">*</span></label>
                    <input type="text" name="ma_lop" class="form-control text-uppercase" required placeholder="VD: D23CQAT01">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Tên lớp <span class="text-danger">*</span></label>
                    <input type="text" name="ten_lop" class="form-control" required placeholder="VD: An toàn Thông tin 01">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Chuyên ngành <span class="text-danger">*</span></label>
                    <input type="text" name="chuyen_nganh" class="form-control" required placeholder="VD: An toàn thông tin">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Khóa tuyển sinh (Năm) <span class="text-danger">*</span></label>
                    <input type="number" name="khoa_hoc" class="form-control" required value="2023">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-danger fw-bold">Lưu Lớp học</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Sửa Lớp -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="ma_lop" id="edit_ma_lop">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">Chỉnh sửa Lớp: <span id="edit_display_malop" class="text-danger"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Tên lớp <span class="text-danger">*</span></label>
                    <input type="text" name="ten_lop" id="edit_ten_lop" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Chuyên ngành <span class="text-danger">*</span></label>
                    <input type="text" name="chuyen_nganh" id="edit_chuyen_nganh" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Khóa tuyển sinh (Năm) <span class="text-danger">*</span></label>
                    <input type="number" name="khoa_hoc" id="edit_khoa_hoc" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark">Cập nhật thay đổi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editModalEl = document.getElementById('editClassModal');
    if (editModalEl && typeof bootstrap !== 'undefined') {
        const editModal = new bootstrap.Modal(editModalEl);
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('edit_ma_lop').value = this.dataset.malop;
                document.getElementById('edit_display_malop').textContent = this.dataset.malop;
                document.getElementById('edit_ten_lop').value = this.dataset.tenlop;
                document.getElementById('edit_chuyen_nganh').value = this.dataset.chuyennganh;
                document.getElementById('edit_khoa_hoc').value = this.dataset.khoahoc;
                editModal.show();
            });
        });
    }

    // Tự làm mờ thông báo và dọn URL
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