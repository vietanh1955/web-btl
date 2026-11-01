<?php
// admin/subjects.php - Single-file CRUD Môn học hoàn chỉnh
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

// ==========================================================
// 1. XỬ LÝ POST (CREATE / UPDATE / DELETE - MÔ HÌNH PRG)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    // --- CREATE: THÊM MÔN HỌC MỚI ---
    if ($action === 'add') {
        $ma_mh  = strtoupper(trim($_POST['ma_mh'] ?? ''));
        $ten_mh = trim($_POST['ten_mh'] ?? '');
        $stc    = (int)($_POST['so_tin_chi'] ?? 0);

        if ($ma_mh === '' || $ten_mh === '' || $stc <= 0) {
            redirect("subjects.php?err=" . urlencode("Vui lòng nhập đầy đủ thông tin, số tín chỉ phải lớn hơn 0!"));
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO mon_hoc (ma_mh, ten_mh, so_tin_chi) VALUES (?, ?, ?)");
            $stmt->execute([$ma_mh, $ten_mh, $stc]);
            redirect("subjects.php?msg=" . urlencode("Thêm mới môn học $ma_mh thành công!"));
        } catch (PDOException $e) {
            $msg = (strpos($e->getMessage(), 'Duplicate') !== false) 
                ? "Mã môn học '$ma_mh' đã tồn tại!" 
                : "Lỗi cơ sở dữ liệu: " . $e->getMessage();
            redirect("subjects.php?err=" . urlencode($msg));
        }
    }

    // --- UPDATE: CẬP NHẬT MÔN HỌC ---
    if ($action === 'edit') {
        $ma_mh  = strtoupper(trim($_POST['ma_mh'] ?? ''));
        $ten_mh = trim($_POST['ten_mh'] ?? '');
        $stc    = (int)($_POST['so_tin_chi'] ?? 0);

        if ($ma_mh === '' || $ten_mh === '' || $stc <= 0) {
            redirect("subjects.php?err=" . urlencode("Thông tin môn học không hợp lệ!"));
        }

        try {
            $stmt = $pdo->prepare("UPDATE mon_hoc SET ten_mh = ?, so_tin_chi = ? WHERE ma_mh = ?");
            $stmt->execute([$ten_mh, $stc, $ma_mh]);
            redirect("subjects.php?msg=" . urlencode("Cập nhật môn học $ma_mh thành công!"));
        } catch (PDOException $e) {
            redirect("subjects.php?err=" . urlencode("Lỗi cập nhật: " . $e->getMessage()));
        }
    }

    // --- DELETE: XÓA MÔN HỌC (GỘP TRỰC TIẾP) ---
if ($action === 'delete') {
        $ma_mh = trim($_POST['ma_mh'] ?? '');

        // Lấy số lượt điểm liên quan
        $check_grades = $pdo->prepare("SELECT COUNT(*) FROM diem WHERE ma_mh = ?");
        $check_grades->execute([$ma_mh]);
        $so_diem = (int)$check_grades->fetchColumn();

        if ($so_diem > 0) {
            redirect("subjects.php?err=" . urlencode("Không thể xóa môn $ma_mh vì đã có $so_diem bản ghi điểm!"));
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM mon_hoc WHERE ma_mh = ?");
            $stmt->execute([$ma_mh]);
            redirect("subjects.php?msg=" . urlencode("Đã xóa môn học $ma_mh thành công!"));
        } catch (PDOException $e) {
            redirect("subjects.php?err=" . urlencode("Lỗi cơ sở dữ liệu: " . $e->getMessage()));
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
    $where  = "WHERE mh.ma_mh LIKE ? OR mh.ten_mh LIKE ?";
    $params = ["%$search%", "%$search%"];
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM mon_hoc mh $where");
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total_rows / $limit);

$sql = "
    SELECT mh.*, COUNT(d.id) AS so_luot_diem 
    FROM mon_hoc mh 
    LEFT JOIN diem d ON mh.ma_mh = d.ma_mh 
    $where
    GROUP BY mh.ma_mh, mh.ten_mh, mh.so_tin_chi 
    ORDER BY mh.ma_mh ASC 
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quản lý Môn học';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0 text-danger">DANH SÁCH MÔN HỌC</h4>
    <button class="btn btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
        + Thêm Môn học mới
    </button>
</div>

<!-- Khối hiển thị thông báo -->
<?php if (isset($_GET['msg'])): ?><div id="alertBox" class="alert alert-success py-2 small"><?= h($_GET['msg']) ?></div><?php endif; ?>
<?php if (isset($_GET['err'])): ?><div id="alertBox" class="alert alert-danger py-2 small"><?= h($_GET['err']) ?></div><?php endif; ?>

<!-- Tìm kiếm -->
<div class="card mb-3 shadow-sm border-0">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1">
                <input type="text" name="search" class="form-control" placeholder="Tìm theo Mã môn, Tên môn học..." value="<?= h($search) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary fw-semibold">Tìm kiếm</button>
                <?php if ($search !== ''): ?>
                    <a href="subjects.php" class="btn btn-secondary fw-semibold">Xóa lọc</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bảng Môn học: Chữ đen thường, không badge, không bold thừa -->
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 text-center">
            <thead class="table-light">
                <tr>
                    <th style="width: 15%">Mã môn</th>
                    <th class="text-start">Tên môn học</th>
                    <th style="width: 15%">Số tín chỉ</th>
                    <th style="width: 15%">Lượt nhập điểm</th>
                    <th style="width: 18%">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                    <tr><td colspan="5" class="py-4 text-muted">Không tìm thấy môn học nào.</td></tr>
                <?php else: foreach ($subjects as $s): ?>
                    <tr>
                        <td class="text-dark"><?= h($s['ma_mh']) ?></td>
                        <td class="text-start text-dark"><?= h($s['ten_mh']) ?></td>
                        <td class="text-dark"><?= (int)$s['so_tin_chi'] ?></td>
                        <td class="text-dark"><?= (int)$s['so_luot_diem'] ?></td>
                        <td class="text-nowrap">
                            <!-- Nút Sửa: Màu vàng, chữ đen nổi bật -->
                            <button type="button" class="btn btn-sm btn-warning text-dark fw-semibold me-1 btn-edit-sub"
                                    data-mamh="<?= h($s['ma_mh']) ?>"
                                    data-tenmh="<?= h($s['ten_mh']) ?>"
                                    data-stc="<?= (int)$s['so_tin_chi'] ?>">Sửa</button>

                            <!-- Nút Xóa: Gửi qua POST nội bộ -->
                            <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa môn <?= h($s['ma_mh']) ?>?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="ma_mh" value="<?= h($s['ma_mh']) ?>">
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

<!-- Modal Thêm Môn -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">Thêm Môn học mới</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Mã môn học <span class="text-danger">*</span></label>
                    <input type="text" name="ma_mh" class="form-control text-uppercase" required placeholder="VD: INT1155">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Tên môn học <span class="text-danger">*</span></label>
                    <input type="text" name="ten_mh" class="form-control" required placeholder="VD: Tin học cơ sở 2">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Số tín chỉ <span class="text-danger">*</span></label>
                    <input type="number" name="so_tin_chi" class="form-control" required min="1" max="10" value="3">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-danger fw-bold">Lưu Môn học</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Sửa Môn -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="ma_mh" id="edit_ma_mh">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">Chỉnh sửa Môn: <span id="edit_display_mamh" class="text-danger"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Tên môn học <span class="text-danger">*</span></label>
                    <input type="text" name="ten_mh" id="edit_ten_mh" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Số tín chỉ <span class="text-danger">*</span></label>
                    <input type="number" name="so_tin_chi" id="edit_so_tin_chi" class="form-control" required min="1" max="10">
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
    const editModalEl = document.getElementById('editSubjectModal');
    if (editModalEl && typeof bootstrap !== 'undefined') {
        const editModal = new bootstrap.Modal(editModalEl);
        document.querySelectorAll('.btn-edit-sub').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_ma_mh').value = this.dataset.mamh;
                document.getElementById('edit_display_mamh').textContent = this.dataset.mamh;
                document.getElementById('edit_ten_mh').value = this.dataset.tenmh;
                document.getElementById('edit_so_tin_chi').value = this.dataset.stc;
                editModal.show();
            });
        });
    }

    // Tự động làm mờ và ẩn thông báo sau 3s
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