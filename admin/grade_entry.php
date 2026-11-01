<?php
// admin/grade_entry.php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$classes_list  = $pdo->query("SELECT ma_lop, ten_lop FROM lop ORDER BY ma_lop ASC")->fetchAll();
$subjects_list = $pdo->query("SELECT ma_mh, ten_mh, so_tin_chi FROM mon_hoc ORDER BY ma_mh ASC")->fetchAll();

$ma_lop  = trim($_GET['ma_lop'] ?? '');
$ma_mh   = trim($_GET['ma_mh'] ?? '');
$hoc_ky  = trim($_GET['hoc_ky'] ?? '2025-1');

$students_with_grades = [];
if ($ma_lop !== '' && $ma_mh !== '' && $hoc_ky !== '') {
    $stmt = $pdo->prepare("
        SELECT sv.ma_sv, sv.ho_ten,
               d.diem_chuyen_can, d.diem_giua_ky, d.diem_cuoi_ky, d.diem_tong
        FROM sinhvien sv
        LEFT JOIN diem d ON sv.ma_sv = d.ma_sv AND d.ma_mh = ? AND d.hoc_ky = ?
        WHERE sv.ma_lop = ?
        ORDER BY sv.ma_sv ASC
    ");
    $stmt->execute([$ma_mh, $hoc_ky, $ma_lop]);
    $students_with_grades = $stmt->fetchAll();
}

$page_title = 'Nhập điểm môn học theo lớp';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">NHẬP ĐIỂM THEO LỚP & MÔN HỌC</h4>
    <?php if (!empty($students_with_grades)): ?>
        <a href="export_csv.php?type=grades&ma_lop=<?= urlencode($ma_lop) ?>&ma_mh=<?= urlencode($ma_mh) ?>&hoc_ky=<?= urlencode($hoc_ky) ?>" class="btn btn-success fw-bold">
            Xuất CSV
        </a>
    <?php endif; ?>
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Chọn Lớp học <span class="text-danger">*</span></label>
                <select name="ma_lop" class="form-select" required>
                    <option value="">-- Chọn lớp học --</option>
                    <?php foreach ($classes_list as $c): ?>
                        <option value="<?= h($c['ma_lop']) ?>" <?= ($ma_lop === $c['ma_lop']) ? 'selected' : '' ?>>
                            <?= h($c['ma_lop']) ?> - <?= h($c['ten_lop']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Chọn Môn học <span class="text-danger">*</span></label>
                <select name="ma_mh" class="form-select" required>
                    <option value="">-- Chọn môn học --</option>
                    <?php foreach ($subjects_list as $s): ?>
                        <option value="<?= h($s['ma_mh']) ?>" <?= ($ma_mh === $s['ma_mh']) ? 'selected' : '' ?>>
                            <?= h($s['ma_mh']) ?> - <?= h($s['ten_mh']) ?> (<?= (int)$s['so_tin_chi'] ?> TC)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Học kỳ <span class="text-danger">*</span></label>
                <input type="text" name="hoc_ky" class="form-control" required placeholder="VD: 2025-1" value="<?= h($hoc_ky) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary fw-bold">Tải danh sách</button>
            </div>
        </form>
    </div>
</div>

<?php if ($ma_lop === '' || $ma_mh === '' || $hoc_ky === ''): ?>
    <div class="alert alert-info text-center py-4">
        Vui lòng chọn <strong>Lớp học</strong>, <strong>Môn học</strong> và <strong>Học kỳ</strong> để hiển thị danh sách nhập điểm.
    </div>
<?php elseif (empty($students_with_grades)): ?>
    <div class="alert alert-warning text-center py-4">
        Lớp <strong><?= h($ma_lop) ?></strong> hiện chưa có sinh viên nào.
    </div>
<?php else: ?>
    <div id="alertBox"></div>

    <form id="gradeForm">
        <?= csrf_field() ?>
        <input type="hidden" name="ma_mh" value="<?= h($ma_mh) ?>">
        <input type="hidden" name="hoc_ky" value="<?= h($hoc_ky) ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold">
                    Danh sách sinh viên lớp <span class="text-dark"><?= h($ma_lop) ?></span> (<?= count($students_with_grades) ?> SV)
                </span>
                <span class="text-muted small">Quy tắc: CC (10%) + GK (30%) + CK (60%)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%">STT</th>
                            <th style="width: 14%">Mã SV</th>
                            <th class="text-start">Họ và tên</th>
                            <th style="width: 15%">Chuyên cần (10%)</th>
                            <th style="width: 15%">Giữa kỳ (30%)</th>
                            <th style="width: 15%">Cuối kỳ (60%)</th>
                            <th style="width: 13%">Điểm tổng (Dự tính)</th>
                            <th style="width: 10%">Điểm chữ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_with_grades as $idx => $st): 
                            $msv  = $st['ma_sv'];
                            $cc   = $st['diem_chuyen_can'] !== null ? (float)$st['diem_chuyen_can'] : '';
                            $gk   = $st['diem_giua_ky'] !== null ? (float)$st['diem_giua_ky'] : '';
                            $ck   = $st['diem_cuoi_ky'] !== null ? (float)$st['diem_cuoi_ky'] : '';
                            $tong = $st['diem_tong'] !== null ? (float)$st['diem_tong'] : '';
                            $conv = convert_grade($tong);
                        ?>
                        <tr id="row-<?= h($msv) ?>">
                            <td><?= $idx + 1 ?></td>
                            <td class="text-dark"><?= h($msv) ?></td>
                            <td class="text-start fw-bold"><?= h($st['ho_ten']) ?></td>
                            <td>
                                <input type="number" step="0.01" min="0" max="10" 
                                       name="diem[<?= h($msv) ?>][cc]" 
                                       class="form-control text-center score-input score-cc" 
                                       data-msv="<?= h($msv) ?>"
                                       value="<?= $cc !== '' ? number_format($cc, 2, '.', '') : '' ?>" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" max="10" 
                                       name="diem[<?= h($msv) ?>][gk]" 
                                       class="form-control text-center score-input score-gk" 
                                       data-msv="<?= h($msv) ?>"
                                       value="<?= $gk !== '' ? number_format($gk, 2, '.', '') : '' ?>" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" max="10" 
                                       name="diem[<?= h($msv) ?>][ck]" 
                                       class="form-control text-center score-input score-ck" 
                                       data-msv="<?= h($msv) ?>"
                                       value="<?= $ck !== '' ? number_format($ck, 2, '.', '') : '' ?>" required>
                            </td>
                            <td class="fw-bold fs-6 text-dark score-total" id="total-<?= h($msv) ?>">
                                <?= $tong !== '' ? number_format($tong, 2) : '-' ?>
                            </td>
                            <td class="fw-bold score-letter" id="letter-<?= h($msv) ?>">
                                <span class="badge <?= $conv['dat'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= h($conv['chu']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center py-3">
                <span class="text-dark small fw-bold">
                    * Ràng buộc: Tất cả các ô phải có giá trị từ 0 đến 10. Nếu có 1 ô sai, hệ thống sẽ Rollback toàn bộ.
                </span>
                <button type="submit" class="btn btn-danger fw-bold px-4" id="btnSaveGrades">
                    Lưu toàn bộ bảng điểm
                </button>
            </div>
        </div>
    </form>
<?php endif; ?>

<script>
function calculateClientTotal(msv) {
    const row = document.getElementById('row-' + msv);
    if (!row) return;
    const ccVal = parseFloat(row.querySelector('.score-cc').value);
    const gkVal = parseFloat(row.querySelector('.score-gk').value);
    const ckVal = parseFloat(row.querySelector('.score-ck').value);

    const totalEl = document.getElementById('total-' + msv);
    const letterEl = document.getElementById('letter-' + msv);

    if (!isNaN(ccVal) && !isNaN(gkVal) && !isNaN(ckVal) &&
        ccVal >= 0 && ccVal <= 10 && gkVal >= 0 && gkVal <= 10 && ckVal >= 0 && ckVal <= 10) {
        
        const total = Math.round((ccVal * 0.10 + gkVal * 0.30 + ckVal * 0.60) * 100) / 100;
        totalEl.textContent = total.toFixed(2);

        let letter = 'F';
        let isPass = false;
        if (total >= 8.5) { letter = 'A'; isPass = true; }
        else if (total >= 8.0) { letter = 'B+'; isPass = true; }
        else if (total >= 7.0) { letter = 'B'; isPass = true; }
        else if (total >= 6.5) { letter = 'C+'; isPass = true; }
        else if (total >= 5.5) { letter = 'C'; isPass = true; }
        else if (total >= 5.0) { letter = 'D+'; isPass = true; }
        else if (total >= 4.0) { letter = 'D'; isPass = true; }

        letterEl.innerHTML = `<span class="badge ${isPass ? 'bg-success' : 'bg-danger'}">${letter}</span>`;
    } else {
        totalEl.textContent = '-';
        letterEl.innerHTML = '<span class="badge bg-secondary">-</span>';
    }
}

document.querySelectorAll('.score-input').forEach(input => {
    input.addEventListener('input', function() {
        calculateClientTotal(this.dataset.msv);
    });
});

document.getElementById('gradeForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('btnSaveGrades');
    const alertBox = document.getElementById('alertBox');
    btn.disabled = true;
    btn.textContent = 'Đang kiểm tra & lưu...';
    alertBox.innerHTML = '';

    const formData = new FormData(this);

    try {
        const res = await fetch('ajax_save_grades.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        btn.disabled = false;
        btn.textContent = 'Lưu toàn bộ bảng điểm';

        if (data.status === 'success') {
            alertBox.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Thành công!</strong> ${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            let errHtml = `<div class="alert alert-danger" role="alert">
                <h6 class="fw-bold mb-2">Đã hủy bỏ thao tác lưu (Rollback)! Phát hiện các ô không hợp lệ:</h6>
                <ul class="mb-0">`;
            if (data.errors && Array.isArray(data.errors)) {
                data.errors.forEach(err => { errHtml += `<li>${err}</li>`; });
            } else {
                errHtml += `<li>${data.message || 'Lỗi không xác định'}</li>`;
            }
            errHtml += `</ul></div>`;
            alertBox.innerHTML = errHtml;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    } catch (err) {
        btn.disabled = false;
        btn.textContent = 'Lưu toàn bộ bảng điểm';
        alertBox.innerHTML = '<div class="alert alert-danger">Lỗi kết nối máy chủ!</div>';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>