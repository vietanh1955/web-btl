<?php
// admin/reports.php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$classes_list = $pdo->query("SELECT ma_lop, ten_lop FROM lop ORDER BY ma_lop ASC")->fetchAll();

$report_type = $_GET['type'] ?? 'gpa';         // 'gpa' hoặc 'debt'
$scope       = $_GET['scope'] ?? 'all';        // 'all' hoặc 'class'
$ma_lop      = trim($_GET['ma_lop'] ?? '');
$time_mode   = $_GET['time'] ?? 'accumulated'; // 'accumulated' hoặc 'semester'
$hoc_ky      = trim($_GET['hoc_ky'] ?? '2025-1');

$where_clauses = [];
$params = [];

if ($scope === 'class' && $ma_lop !== '') {
    $where_clauses[] = "sv.ma_lop = ?";
    $params[] = $ma_lop;
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$sql_sv = "
    SELECT sv.ma_sv, sv.ho_ten, sv.ma_lop, l.ten_lop
    FROM sinhvien sv
    JOIN lop l ON sv.ma_lop = l.ma_lop
    $where_sql
    ORDER BY sv.ma_sv ASC
";
$stmt_sv = $pdo->prepare($sql_sv);
$stmt_sv->execute($params);
$students = $stmt_sv->fetchAll();

$report_data = [];
$sem_param = ($time_mode === 'semester') ? $hoc_ky : null;

foreach ($students as $sv) {
    $calc = calculate_student_gpa($pdo, $sv['ma_sv'], $sem_param);

    if ($report_type === 'gpa') {
        if ($calc['tong_tin_chi'] > 0) {
            $report_data[] = [
                'ma_sv'            => $sv['ma_sv'],
                'ho_ten'           => $sv['ho_ten'],
                'ma_lop'           => $sv['ma_lop'],
                'ten_lop'          => $sv['ten_lop'],
                'gpa'              => $calc['gpa'],
                'xep_loai'         => $calc['xep_loai'],
                'tin_chi_tich_luy' => $calc['tin_chi_tich_luy'],
                'tong_tin_chi'     => $calc['tong_tin_chi']
            ];
        }
    } elseif ($report_type === 'debt') {
        if (!empty($calc['mon_no'])) {
            $report_data[] = [
                'ma_sv'   => $sv['ma_sv'],
                'ho_ten'  => $sv['ho_ten'],
                'ma_lop'  => $sv['ma_lop'],
                'ten_lop' => $sv['ten_lop'],
                'gpa'     => $calc['gpa'],
                'mon_no'  => $calc['mon_no']
            ];
        }
    }
}

if ($report_type === 'gpa') {
    usort($report_data, fn($a, $b) => $b['gpa'] <=> $a['gpa']);
}

$page_title = 'Báo cáo & Xếp hạng học tập';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">BÁO CÁO & XẾP HẠNG HỌC TẬP</h4>
    <div>
        <a href="export_csv.php?type=reports&class=<?= urlencode($filter_class ?? '') ?>&semester=<?= urlencode($filter_sem ?? '') ?>" class="btn btn-success fw-bold">
            Xuất CSV
        </a>
    </div>
</div>

<!-- Bộ lọc -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">Loại báo cáo</label>
                <select name="type" class="form-select" id="selectType">
                    <option value="gpa" <?= ($report_type === 'gpa') ? 'selected' : '' ?>>Bảng xếp hạng GPA</option>
                    <option value="debt" <?= ($report_type === 'debt') ? 'selected' : '' ?>>Danh sách Sinh viên nợ môn</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-bold">Phạm vi</label>
                <select name="scope" class="form-select" id="selectScope">
                    <option value="all" <?= ($scope === 'all') ? 'selected' : '' ?>>Toàn trường</option>
                    <option value="class" <?= ($scope === 'class') ? 'selected' : '' ?>>Theo Lớp học</option>
                </select>
            </div>

            <div class="col-md-3" id="classWrapper" style="<?= ($scope === 'class') ? '' : 'display:none;' ?>">
                <label class="form-label fw-bold">Chọn Lớp học</label>
                <select name="ma_lop" class="form-select">
                    <option value="">-- Chọn lớp --</option>
                    <?php foreach ($classes_list as $c): ?>
                        <option value="<?= h($c['ma_lop']) ?>" <?= ($ma_lop === $c['ma_lop']) ? 'selected' : '' ?>>
                            <?= h($c['ma_lop']) ?> - <?= h($c['ten_lop']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-bold">Thời gian</label>
                <select name="time" class="form-select" id="selectTime">
                    <option value="accumulated" <?= ($time_mode === 'accumulated') ? 'selected' : '' ?>>Tích lũy toàn khóa</option>
                    <option value="semester" <?= ($time_mode === 'semester') ? 'selected' : '' ?>>Theo Học kỳ</option>
                </select>
            </div>

            <div class="col-md-2" id="semesterWrapper" style="<?= ($time_mode === 'semester') ? '' : 'display:none;' ?>">
                <label class="form-label fw-bold">Học kỳ</label>
                <input type="text" name="hoc_ky" class="form-control" placeholder="VD: 2025-1" value="<?= h($hoc_ky) ?>">
            </div>

            <div class="col-md-auto">
                <button type="submit" class="btn btn-secondary px-4 fw-bold">Lọc báo cáo</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-bold">
            <?= ($report_type === 'gpa') ? 'BẢNG XẾP HẠNG GPA' : 'DANH SÁCH SINH VIÊN CÒN NỢ MÔN HỌC' ?>
            (<?= ($scope === 'all') ? 'Toàn trường' : 'Lớp ' . h($ma_lop) ?> - 
             <?= ($time_mode === 'accumulated') ? 'Tích lũy' : 'Học kỳ ' . h($hoc_ky) ?>)
        </span>
        <span class="badge bg-danger fs-6">Tổng: <?= count($report_data) ?> sinh viên</span>
    </div>

    <?php if ($report_type === 'gpa'): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8%">Hạng</th>
                        <th style="width: 14%">Mã SV</th>
                        <th class="text-start">Họ và tên</th>
                        <th style="width: 14%">Lớp</th>
                        <th style="width: 14%">Tín chỉ tích lũy</th>
                        <th style="width: 12%">GPA</th>
                        <th style="width: 15%">Xếp loại</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr><td colspan="7" class="py-4 text-muted">Không có dữ liệu điểm thỏa mãn điều kiện lọc.</td></tr>
                    <?php else: foreach ($report_data as $idx => $row): ?>
                        <tr>
                            <td class="fw-bold fs-6 <?= ($idx < 5) ? 'text-danger' : '' ?>">#<?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark"><?= h($row['ma_sv']) ?></td>
                            <td class="text-start fw-bold">
                                <a href="student_detail.php?ma_sv=<?= urlencode($row['ma_sv']) ?>" class="text-decoration-none text-dark">
                                    <?= h($row['ho_ten']) ?>
                                </a>
                            </td>
                            <td><span class="text-dark"><?= h($row['ma_lop']) ?></span></td>
                            <td><?= (int)$row['tin_chi_tich_luy'] ?> / <?= (int)$row['tong_tin_chi'] ?></td>
                            <td class="fw-bold fs-6 text-dark"><?= number_format($row['gpa'], 2) ?></td>
                            <td><span class="badge bg-success"><?= h($row['xep_loai']) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%" class="text-center">STT</th>
                        <th style="width: 13%">Mã SV</th>
                        <th style="width: 20%">Họ và tên</th>
                        <th style="width: 12%">Lớp</th>
                        <th style="width: 10%" class="text-center">GPA</th>
                        <th style="width: 10%" class="text-center">Số môn nợ</th>
                        <th>Danh sách môn nợ chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr><td colspan="7" class="py-4 text-center text-success fw-bold">Không có sinh viên nào nợ môn.</td></tr>
                    <?php else: foreach ($report_data as $idx => $row): ?>
                        <tr>
                            <td class="text-center"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-dark"><?= h($row['ma_sv']) ?></td>
                            <td class="fw-bold"><?= h($row['ho_ten']) ?></td>
                            <td><?= h($row['ma_lop']) ?></td>
                            <td class="text-center fw-bold text-danger"><?= number_format($row['gpa'], 2) ?></td>
                            <td class="text-center"><span class="badge bg-danger fs-6"><?= count($row['mon_no']) ?></span></td>
                            <td>
                                <ul class="mb-0 ps-3 small">
                                    <?php foreach ($row['mon_no'] as $m): ?>
                                        <li>
                                            <strong><?= h($m['ma_mh']) ?></strong> - <?= h($m['ten_mh']) ?> 
                                            (<?= (int)$m['so_tin_chi'] ?> TC): 
                                            <span class="text-danger fw-bold">Điểm: <?= number_format($m['diem_tong'], 2) ?> (<?= h($m['diem_chu']) ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('selectScope')?.addEventListener('change', function() {
    document.getElementById('classWrapper').style.display = (this.value === 'class') ? 'block' : 'none';
});

document.getElementById('selectTime')?.addEventListener('change', function() {
    document.getElementById('semesterWrapper').style.display = (this.value === 'semester') ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>