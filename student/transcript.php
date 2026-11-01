<?php
// student/transcript.php
require_once __DIR__ . '/../includes/functions.php';
require_role('sinhvien');

$ma_sv = $_SESSION['ma_sv'] ?? '';
if ($ma_sv === '') {
    redirect(BASE_URL . '/logout.php');
}

$stmtSV = $pdo->prepare("
    SELECT sv.*, l.ten_lop, l.chuyen_nganh AS nganh, l.khoa_hoc
    FROM sinhvien sv
    JOIN lop l ON sv.ma_lop = l.ma_lop
    WHERE sv.ma_sv = ?
");
$stmtSV->execute([$ma_sv]);
$student = $stmtSV->fetch();

$stmtSemList = $pdo->prepare("SELECT DISTINCT hoc_ky FROM diem WHERE ma_sv = ? ORDER BY hoc_ky ASC");
$stmtSemList->execute([$ma_sv]);
$available_semesters = $stmtSemList->fetchAll(PDO::FETCH_COLUMN);

$selected_sem = trim($_GET['semester'] ?? '');

$where_clauses = ["d.ma_sv = ?"];
$params = [$ma_sv];

if ($selected_sem !== '') {
    $where_clauses[] = "d.hoc_ky = ?";
    $params[] = $selected_sem;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

$sql = "
    SELECT d.*, mh.ten_mh, mh.so_tin_chi 
    FROM diem d 
    JOIN mon_hoc mh ON d.ma_mh = mh.ma_mh 
    $where_sql 
    ORDER BY d.hoc_ky ASC, mh.ma_mh ASC
";
$stmtGrades = $pdo->prepare($sql);
$stmtGrades->execute($params);
$raw_grades = $stmtGrades->fetchAll();

$grouped_grades = [];
foreach ($raw_grades as $rg) {
    $grouped_grades[$rg['hoc_ky']][] = $rg;
}

$overall_calc = calculate_student_gpa($pdo, $ma_sv);

$page_title = 'Kết quả học tập';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h4 class="fw-bold mb-0">KẾT QUẢ HỌC TẬP CHI TIẾT</h4>
</div>

<!-- Bộ lọc kỳ học -->
<div class="card mb-4 shadow-sm no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="form-label fw-bold mb-0">Học kỳ hiển thị:</label>
            </div>
            <div class="col-auto">
                <select name="semester" class="form-select form-select-sm">
                    <option value="">-- Toàn bộ các học kỳ --</option>
                    <?php foreach ($available_semesters as $s): ?>
                        <option value="<?= h($s) ?>" <?= ($selected_sem === $s) ? 'selected' : '' ?>>Học kỳ <?= h($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-secondary">Xem</button>
                <?php if ($selected_sem !== ''): ?>
                    <a href="transcript.php" class="btn btn-sm btn-outline-secondary">Xem tất cả</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Tóm tắt toàn khóa -->
<div class="alert alert-secondary bg-white d-flex justify-content-between align-items-center mb-4">
    <div>
        <strong>GPA Tích lũy toàn khóa:</strong> <span class="fs-5 fw-bold text-danger"><?= number_format($overall_calc['gpa'], 2) ?></span>
    </div>
    <div>
        <strong>Xếp loại học lực:</strong> <span class="fs-5 fw-bold text-dark text-success"><?= h($overall_calc['xep_loai']) ?></span>
    </div>
    <div>
        <strong>Tín chỉ tích lũy:</strong> <span class="fs-5 fw-bold text-dark text-info"><?= (int)$overall_calc['tin_chi_tich_luy'] ?> / <?= (int)$overall_calc['tong_tin_chi'] ?></span>
    </div>
</div>

<!-- Danh sách điểm theo từng kỳ -->
<?php if (empty($grouped_grades)): ?>
    <div class="alert alert-info text-center py-4">Chưa có kết quả học tập nào được ghi nhận.</div>
<?php else: foreach ($grouped_grades as $sem => $sub_list): 
    $sem_calc = calculate_student_gpa($pdo, $ma_sv, $sem);
?>
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <span class="fw-bold text-danger">HỌC KỲ: <?= h($sem) ?></span>
            <span>
                GPA kỳ: <strong class="text-danger"><?= number_format($sem_calc['gpa'], 2) ?></strong> | 
                Xếp loại: <strong><?= h($sem_calc['xep_loai']) ?></strong> | 
                Số TC đạt: <strong><?= (int)$sem_calc['tin_chi_tich_luy'] ?></strong>
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
                        <th style="width: 10%">Kết quả</th>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>