<?php
// student/index.php
require_once __DIR__ . '/../includes/functions.php';
require_role('sinhvien');

$ma_sv = $_SESSION['ma_sv'] ?? '';
if ($ma_sv === '') {
    redirect(BASE_URL . '/logout.php');
}

$stmt = $pdo->prepare("
    SELECT sv.*, l.ten_lop, l.chuyen_nganh AS nganh, l.khoa_hoc AS nam_nhap_hoc
    FROM sinhvien sv
    JOIN lop l ON sv.ma_lop = l.ma_lop
    WHERE sv.ma_sv = ?
");
$stmt->execute([$ma_sv]);
$student = $stmt->fetch();

if (!$student) {
    die("Không tìm thấy thông tin hồ sơ sinh viên!");
}

$academic = calculate_student_gpa($pdo, $ma_sv);

// Lấy danh sách điểm các học kỳ để vẽ biểu đồ tiến độ
$stmtSemesters = $pdo->prepare("
    SELECT DISTINCT hoc_ky 
    FROM diem 
    WHERE ma_sv = ? 
    ORDER BY hoc_ky ASC
");
$stmtSemesters->execute([$ma_sv]);
$sem_list = $stmtSemesters->fetchAll(PDO::FETCH_COLUMN);

$chart_labels = [];
$chart_gpas   = [];

foreach ($sem_list as $sem) {
    $sem_calc = calculate_student_gpa($pdo, $ma_sv, $sem);
    $chart_labels[] = "Kỳ " . $sem;
    $chart_gpas[]   = $sem_calc['gpa'];
}

$page_title = 'Trang chủ Sinh viên';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-4 bg-danger text-white shadow-sm border-0">
    <div class="card-body p-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-1">Xin chào, <?= h($student['ho_ten']) ?>!</h4>
            <p class="mb-0 opacity-75">
                Mã SV: <strong><?= h($student['ma_sv']) ?></strong> | 
                Lớp: <strong><?= h($student['ma_lop']) ?> (<?= h($student['ten_lop']) ?>)</strong> | 
                Ngành: <strong><?= h($student['nganh']) ?></strong>
            </p>
        </div>
        <div>
            <a href="transcript.php" class="btn btn-light fw-bold text-danger">Tra cứu bảng điểm &raquo;</a>
        </div>
    </div>
</div>

<!-- 4 Thẻ KPI Tích lũy -->
<div class="row g-3 mb-4">
    <!-- Card 1: GPA Tích lũy (Giữ màu đỏ PTIT) -->
    <div class="col-md-3">
        <div class="card shadow-sm border-danger text-center">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">GPA Tích lũy (Hệ 4)</div>
                <h2 class="fw-bold text-danger my-2"><?= number_format($academic['gpa'], 2) ?></h2>
                <div class="small text-muted">Thang điểm 4.00</div>
            </div>
        </div>
    </div>

    <!-- Card 2: Xếp loại học lực (Chuyển sang màu đen) -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Xếp loại học lực</div>
                <h2 class="fw-bold text-dark my-2"><?= h($academic['xep_loai']) ?></h2>
                <div class="small text-muted">Dựa trên GPA toàn khóa</div>
            </div>
        </div>
    </div>

    <!-- Card 3: Tín chỉ tích lũy (Chuyển sang màu đen) -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Tín chỉ tích lũy</div>
                <h2 class="fw-bold text-dark my-2"><?= (int)$academic['tin_chi_tich_luy'] ?> / <?= (int)$academic['tong_tin_chi'] ?></h2>
                <div class="small text-muted">Số tín chỉ đã đạt</div>
            </div>
        </div>
    </div>

    <!-- Card 4: Môn nợ / Học lại (Chuyển sang màu đen) -->
    <div class="col-md-3">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Môn nợ / Học lại</div>
                <h2 class="fw-bold text-dark my-2">
                    <?= count($academic['mon_no']) ?>
                </h2>
                <div class="small text-muted">Học phần điểm F</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Cột trái: Biểu đồ đường Canvas thuần -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light fw-bold">TIẾN ĐỘ HỌC TẬP (GPA QUA TỪNG HỌC KỲ)</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <?php if (empty($chart_labels)): ?>
                    <div class="text-center py-5 text-muted">Chưa có dữ liệu học kỳ để hiển thị biểu đồ.</div>
                <?php else: ?>
                    <canvas id="studentLineCanvas" width="560" height="260" style="max-width: 100%;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cột phải: Cảnh báo môn nợ -->
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light fw-bold">DANH SÁCH MÔN HỌC CHƯA HOÀN THÀNH</div>
            <div class="card-body">
                <?php if (empty($academic['mon_no'])): ?>
                    <div class="alert alert-success d-flex align-items-center mb-0">
                        <div>
                            <strong>Kết quả xuất sắc!</strong> Bạn hiện không có môn học nào bị nợ. Hãy tiếp tục duy trì thành tích tốt!
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger py-2 mb-3 small">
                        Bạn còn <strong><?= count($academic['mon_no']) ?> môn học</strong> chưa đạt chuẩn (Điểm F). Cần lưu ý đăng ký học lại sớm!
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($academic['mon_no'] as $m): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <div class="fw-bold text-danger"><?= h($m['ma_mh']) ?> - <?= h($m['ten_mh']) ?></div>
                                    <div class="small text-muted"><?= (int)$m['so_tin_chi'] ?> tín chỉ | Học kỳ: <?= h($m['hoc_ky']) ?></div>
                                </div>
                                <span class="badge bg-danger rounded-pill">
                                    Tổng: <?= number_format($m['diem_tong'], 2) ?> (<?= h($m['diem_chu']) ?>)
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($chart_labels)): ?>
<script>
// Vẽ Biểu đồ Đường (Line Chart) tiến độ GPA bằng HTML5 Canvas thuần
(function renderLineChart() {
    const canvas = document.getElementById('studentLineCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const labels = <?= json_encode($chart_labels) ?>;
    const values = <?= json_encode($chart_gpas) ?>;

    const W = canvas.width;
    const H = canvas.height;
    const padLeft = 40, padRight = 30, padTop = 30, padBottom = 40;
    const chartW = W - padLeft - padRight;
    const chartH = H - padTop - padBottom;

    ctx.clearRect(0, 0, W, H);

    // Vẽ lưới tọa độ thang điểm 0 - 4.0
    ctx.strokeStyle = '#dee2e6';
    ctx.fillStyle = '#6c757d';
    ctx.font = '12px sans-serif';
    ctx.textAlign = 'right';

    for (let i = 0; i <= 4; i++) {
        const y = padTop + chartH - (i / 4) * chartH;
        ctx.beginPath();
        ctx.moveTo(padLeft, y);
        ctx.lineTo(W - padRight, y);
        ctx.stroke();
        ctx.fillText(i.toFixed(1), padLeft - 6, y + 4);
    }

    if (values.length === 0) return;

    // Tính tọa độ các điểm
    const points = [];
    values.forEach((val, i) => {
        const x = (values.length === 1) 
            ? padLeft + chartW / 2 
            : padLeft + (i / (values.length - 1)) * chartW;
        const y = padTop + chartH - (val / 4.0) * chartH;
        points.push({ x, y, val, label: labels[i] });
    });

    // Vẽ đường nối giữa các điểm
    ctx.strokeStyle = '#0d6efd';
    ctx.lineWidth = 3;
    ctx.beginPath();
    points.forEach((pt, i) => {
        if (i === 0) ctx.moveTo(pt.x, pt.y);
        else ctx.lineTo(pt.x, pt.y);
    });
    ctx.stroke();

    // Vẽ các chấm tròn điểm số và nhãn
    points.forEach(pt => {
        // Chấm tròn
        ctx.fillStyle = '#0d6efd';
        ctx.beginPath();
        ctx.arc(pt.x, pt.y, 6, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 2;
        ctx.stroke();

        // Điểm số trên đỉnh
        ctx.fillStyle = '#0d6efd';
        ctx.font = 'bold 12px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(pt.val.toFixed(2), pt.x, pt.y - 10);

        // Nhãn học kỳ dưới đáy
        ctx.fillStyle = '#212529';
        ctx.font = '12px sans-serif';
        ctx.fillText(pt.label, pt.x, padTop + chartH + 20);
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>