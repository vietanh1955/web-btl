<?php
// admin/index.php
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$current_semester = '2025-1';

// 1. Thống kê 3 chỉ số tổng quan (Bỏ tổng môn học, chia đều 3 cột)
$total_students = (int)$pdo->query("SELECT COUNT(*) FROM sinhvien")->fetchColumn();
$total_classes  = (int)$pdo->query("SELECT COUNT(*) FROM lop")->fetchColumn();

$stmt_grades_count = $pdo->prepare("SELECT COUNT(*) FROM diem WHERE hoc_ky = ?");
$stmt_grades_count->execute([$current_semester]);
$total_grade_records = (int)$stmt_grades_count->fetchColumn();

$stmt_pass = $pdo->prepare("SELECT COUNT(*) FROM diem WHERE hoc_ky = ? AND diem_tong >= 4.0");
$stmt_pass->execute([$current_semester]);
$pass_records = (int)$stmt_pass->fetchColumn();

$pass_rate = ($total_grade_records > 0) ? round(($pass_records / $total_grade_records) * 100, 1) : 0.0;

// 2. BIỂU ĐỒ CỘT: TÍNH ĐIỂM TRUNG BÌNH (GPA) CỦA CÁC LỚP
$classes = $pdo->query("SELECT ma_lop, ten_lop FROM lop ORDER BY ma_lop ASC")->fetchAll(PDO::FETCH_ASSOC);
$bar_chart_data = [];

foreach ($classes as $cl) {
    // Lấy toàn bộ sinh viên thuộc lớp
    $stmt_sv = $pdo->prepare("SELECT ma_sv FROM sinhvien WHERE ma_lop = ?");
    $stmt_sv->execute([$cl['ma_lop']]);
    $sv_list = $stmt_sv->fetchAll(PDO::FETCH_COLUMN);

    $class_gpa_sum = 0;
    $sv_counted = 0;
    foreach ($sv_list as $msv) {
        $academic = calculate_student_gpa($pdo, $msv, $current_semester);
        if ($academic['tong_tin_chi'] > 0) {
            $class_gpa_sum += $academic['gpa'];
            $sv_counted++;
        }
    }

    $avg_gpa = ($sv_counted > 0) ? round($class_gpa_sum / $sv_counted, 2) : 0.0;
    $bar_chart_data[] = [
        'label' => $cl['ma_lop'],
        'value' => $avg_gpa
    ];
}

// 3. BIỂU ĐỒ TRÒN: PHÂN BỐ XẾP LOẠI GPA HỌC KỲ CỦA TOÀN BỘ SINH VIÊN
$all_students_ids = $pdo->query("SELECT ma_sv FROM sinhvien")->fetchAll(PDO::FETCH_COLUMN);
$gpa_distribution = [
    'Xuất sắc (>= 3.6)'  => 0,
    'Giỏi (3.2 - 3.59)'   => 0,
    'Khá (2.5 - 3.19)'    => 0,
    'Trung bình (2.0 - 2.49)' => 0,
    'Yếu / Kém (< 2.0)'  => 0,
];

$dist_colors = [
    'Xuất sắc (>= 3.6)'  => '#198754', // Xanh lá đậm
    'Giỏi (3.2 - 3.59)'   => '#20c997', // Xanh ngọc
    'Khá (2.5 - 3.19)'    => '#0d6efd', // Xanh dương
    'Trung bình (2.0 - 2.49)' => '#ffc107', // Vàng
    'Yếu / Kém (< 2.0)'  => '#dc3545', // Đỏ
];

$total_evaluated_students = 0;
foreach ($all_students_ids as $msv) {
    $acad = calculate_student_gpa($pdo, $msv, $current_semester);
    if ($acad['tong_tin_chi'] > 0) {
        $total_evaluated_students++;
        $g = $acad['gpa'];
        if ($g >= 3.6) {
            $gpa_distribution['Xuất sắc (>= 3.6)']++;
        } elseif ($g >= 3.2) {
            $gpa_distribution['Giỏi (3.2 - 3.59)']++;
        } elseif ($g >= 2.5) {
            $gpa_distribution['Khá (2.5 - 3.19)']++;
        } elseif ($g >= 2.0) {
            $gpa_distribution['Trung bình (2.0 - 2.49)']++;
        } else {
            $gpa_distribution['Yếu / Kém (< 2.0)']++;
        }
    }
}

$donut_chart_data = [];
foreach ($gpa_distribution as $level => $count) {
    if ($count > 0) {
        $pct = ($total_evaluated_students > 0) ? round(($count / $total_evaluated_students) * 100, 1) : 0;
        $donut_chart_data[] = [
            'label'   => $level,
            'value'   => $count,
            'percent' => $pct,
            'color'   => $dist_colors[$level]
        ];
    }
}

// 4. Lấy danh sách Top 5 Sinh viên có GPA tích lũy cao nhất
$all_sv = $pdo->query("
    SELECT sv.ma_sv, sv.ho_ten, sv.ma_lop, l.ten_lop
    FROM sinhvien sv
    JOIN lop l ON sv.ma_lop = l.ma_lop
")->fetchAll(PDO::FETCH_ASSOC);

$ranking = [];
foreach ($all_sv as $sv) {
    $calc = calculate_student_gpa($pdo, $sv['ma_sv']);
    if ($calc['tong_tin_chi'] > 0) {
        $ranking[] = [
            'ma_sv'            => $sv['ma_sv'],
            'ho_ten'           => $sv['ho_ten'],
            'ma_lop'           => $sv['ma_lop'],
            'gpa'              => $calc['gpa'],
            'tin_chi_tich_luy' => $calc['tin_chi_tich_luy'],
            'xep_loai'         => $calc['xep_loai']
        ];
    }
}
usort($ranking, fn($a, $b) => $b['gpa'] <=> $a['gpa']);
$top5_students = array_slice($ranking, 0, 5);

$page_title = 'Bảng điều khiển Quản trị';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0 text-danger">TỔNG QUAN HỆ THỐNG ĐÀO TẠO</h4>
    <span class="badge bg-danger">Học kỳ hiện tại: <?= h($current_semester) ?></span>
</div>

<!-- 3 Thẻ thống kê KPI (Bỏ môn học, viền đỏ PTIT đồng nhất) -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-start border-danger border-4 h-100">
            <div class="card-body">
                <div class="text-uppercase fw-bold text-muted small mb-1">Tổng Sinh viên</div>
                <div class="fs-2 fw-bold text-danger mb-1"><?= (int)$total_students ?></div>
                <div class="text-muted small">Hồ sơ đang quản lý</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-start border-danger border-4 h-100">
            <div class="card-body">
                <div class="text-uppercase fw-bold text-muted small mb-1">Tổng Lớp học</div>
                <div class="fs-2 fw-bold text-danger mb-1"><?= (int)$total_classes ?></div>
                <div class="text-muted small">Lớp hành chính đào tạo</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-start border-danger border-4 h-100">
            <div class="card-body">
                <div class="text-uppercase fw-bold text-muted small mb-1">Tỷ lệ Qua môn</div>
                <div class="fs-2 fw-bold text-danger mb-1"><?= $pass_rate ?>%</div>
                <div class="text-muted small"><?= $pass_records ?> / <?= $total_grade_records ?> lượt môn đạt</div>
            </div>
        </div>
    </div>
</div>

<!-- 2 Biểu đồ HTML5 Canvas -->
<div class="row g-4 mb-4">
    <!-- 1. BIỂU ĐỒ CỘT: ĐIỂM TRUNG BÌNH (GPA) CÁC LỚP -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">ĐIỂM TRUNG BÌNH (GPA HỆ 4) THEO LỚP</span>
            </div>
            <div class="card-body">
                <div style="position: relative; width: 100%; height: 320px;">
                    <canvas id="barChart" style="display: block; width: 100%; height: 100%;"></canvas>
                    <div id="barTooltip" style="position: absolute; display: none; pointer-events: none; background: rgba(33, 37, 41, 0.95); color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; transform: translate(-50%, -120%); white-space: nowrap; z-index: 10;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. BIỂU ĐỒ TRÒN: PHÂN BỐ GPA HỌC KỲ TOÀN KHÓA -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark">PHÂN BỐ GPA SINH VIÊN HỌC KỲ</span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <div style="position: relative; width: 100%; height: 250px;">
                    <canvas id="donutChart" style="display: block; width: 100%; height: 100%;"></canvas>
                    <div id="donutTooltip" style="position: absolute; display: none; pointer-events: none; background: rgba(33, 37, 41, 0.95); color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; transform: translate(-50%, -120%); white-space: nowrap; z-index: 10;"></div>
                </div>
                <div id="donutLegend" class="d-flex flex-wrap justify-content-center gap-2 pt-3 border-top mt-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- Bảng Top 5 GPA (Chữ đen, không bold, không badge) -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">TOP 5 SINH VIÊN CÓ GPA TÍCH LŨY CAO NHẤT</span>
        <a href="reports.php" 
           class="btn btn-sm fw-semibold" 
           style="background-color: #ffffff; color: #dc3545; border: 1px solid #dc3545;" 
           onmouseover="this.style.backgroundColor='#dc3545'; this.style.color='#ffffff';" 
           onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#dc3545';">
            Xem toàn bộ xếp hạng &raquo;
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0 text-center">
            <thead class="table-light">
                <tr>
                    <th style="width: 10%">Hạng</th>
                    <th style="width: 15%">Mã SV</th>
                    <th class="text-start">Họ và tên</th>
                    <th style="width: 15%">Lớp</th>
                    <th style="width: 15%">Tín chỉ tích lũy</th>
                    <th style="width: 15%">GPA (Hệ 4)</th>
                    <th style="width: 15%">Xếp loại</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top5_students)): ?>
                    <tr><td colspan="7" class="py-4 text-muted">Chưa có dữ liệu điểm sinh viên.</td></tr>
                <?php else: foreach ($top5_students as $idx => $st): ?>
                    <tr>
                        <td class="text-dark">#<?= $idx + 1 ?></td>
                        <td class="text-dark"><?= h($st['ma_sv']) ?></td>
                        <td class="text-start">
                            <a href="student_detail.php?ma_sv=<?= urlencode($st['ma_sv']) ?>" class="text-decoration-none text-dark">
                                <?= h($st['ho_ten']) ?>
                            </a>
                        </td>
                        <td class="text-dark"><?= h($st['ma_lop']) ?></td>
                        <td class="text-dark"><?= (int)$st['tin_chi_tich_luy'] ?></td>
                        <td class="text-dark"><?= number_format($st['gpa'], 2) ?></td>
                        <td class="text-dark"><?= h($st['xep_loai']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const barData   = <?= json_encode($bar_chart_data, JSON_NUMERIC_CHECK) ?>;
    const donutData = <?= json_encode($donut_chart_data, JSON_NUMERIC_CHECK) ?>;

    // ==========================================
    // 1. BIỂU ĐỒ CỘT (GPA THEO LỚP - THANG ĐIỂM 4.0)
    // ==========================================
    const barCanvas = document.getElementById('barChart');
    const barTooltip = document.getElementById('barTooltip');
    if (barCanvas) {
        const ctx = barCanvas.getContext('2d');
        let bars = [];
        let hoveredBar = -1;

        function renderBarChart() {
            const rect = barCanvas.parentElement.getBoundingClientRect();
            if (!rect.width) return;

            const dpr = window.devicePixelRatio || 1;
            barCanvas.width = rect.width * dpr;
            barCanvas.height = rect.height * dpr;
            ctx.resetTransform?.();
            ctx.scale(dpr, dpr);

            const w = rect.width;
            const h = rect.height;
            ctx.clearRect(0, 0, w, h);
            bars = [];

            if (barData.length === 0) {
                ctx.fillStyle = '#6c757d';
                ctx.font = '14px system-ui';
                ctx.textAlign = 'center';
                ctx.fillText('Chưa có dữ liệu lớp học', w / 2, h / 2);
                return;
            }

            const pBottom = 40, pTop = 30, pLeft = 45, pRight = 20;
            const cWidth = w - pLeft - pRight;
            const cHeight = h - pTop - pBottom;

            const maxVal = 4.0; // Thang điểm tối đa hệ 4
            const step = 4;

            // Đường lưới trục Y (0.0, 1.0, 2.0, 3.0, 4.0)
            ctx.font = '12px system-ui, sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            for (let i = 0; i <= step; i++) {
                const val = (maxVal / step) * i;
                const y = h - pBottom - (i / step) * cHeight;

                ctx.beginPath();
                ctx.strokeStyle = '#e9ecef';
                ctx.lineWidth = 1;
                ctx.setLineDash([3, 3]);
                ctx.moveTo(pLeft, y);
                ctx.lineTo(w - pRight, y);
                ctx.stroke();
                ctx.setLineDash([]);

                ctx.fillStyle = '#6c757d';
                ctx.fillText(val.toFixed(1), pLeft - 8, y);
            }

            const slotWidth = cWidth / barData.length;
            const barW = Math.min(slotWidth * 0.45, 60);

            barData.forEach((item, idx) => {
                const xCenter = pLeft + slotWidth * idx + slotWidth / 2;
                const x = xCenter - barW / 2;
                const barH = (item.value / maxVal) * cHeight;
                const y = h - pBottom - barH;

                bars.push({ x, y, w: barW, h: barH, data: item, xCenter });

                const isHovered = (hoveredBar === idx);
                ctx.fillStyle = isHovered ? '#b02a37' : '#dc3545';

                ctx.beginPath();
                if (ctx.roundRect) {
                    ctx.roundRect(x, y, barW, barH, [4, 4, 0, 0]);
                } else {
                    ctx.rect(x, y, barW, barH);
                }
                ctx.fill();

                // Điểm số GPA trên đầu cột
                ctx.fillStyle = isHovered ? '#b02a37' : '#212529';
                ctx.font = isHovered ? 'bold 12px system-ui' : '11px system-ui';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                ctx.fillText(Number(item.value).toFixed(2), xCenter, y - 4);

                // Mã lớp trục X
                ctx.fillStyle = '#212529';
                ctx.font = '12px system-ui';
                ctx.textBaseline = 'top';
                ctx.fillText(item.label, xCenter, h - pBottom + 10);
            });
        }

        barCanvas.addEventListener('mousemove', function (e) {
            const rect = barCanvas.getBoundingClientRect();
            const mx = e.clientX - rect.left;
            const my = e.clientY - rect.top;

            let found = -1;
            for (let i = 0; i < bars.length; i++) {
                const b = bars[i];
                if (mx >= b.x && mx <= b.x + b.w && my >= b.y && my <= b.y + b.h) {
                    found = i;
                    break;
                }
            }

            if (found !== hoveredBar) {
                hoveredBar = found;
                renderBarChart();
            }

            if (found !== -1) {
                const b = bars[found];
                barTooltip.style.display = 'block';
                barTooltip.style.left = b.xCenter + 'px';
                barTooltip.style.top = b.y + 'px';
                barTooltip.innerHTML = `<strong>Lớp:</strong> ${b.data.label}<br><strong>GPA TB:</strong> ${Number(b.data.value).toFixed(2)}`;
                barCanvas.style.cursor = 'pointer';
            } else {
                barTooltip.style.display = 'none';
                barCanvas.style.cursor = 'default';
            }
        });

        barCanvas.addEventListener('mouseleave', function () {
            hoveredBar = -1;
            renderBarChart();
            barTooltip.style.display = 'none';
        });

        window.addEventListener('resize', renderBarChart);
        renderBarChart();
    }

    // ==========================================
    // 2. BIỂU ĐỒ TRÒN (PHÂN BỐ GPA HỌC KỲ)
    // ==========================================
    const donutCanvas = document.getElementById('donutChart');
    const donutTooltip = document.getElementById('donutTooltip');
    const legendBox = document.getElementById('donutLegend');

    if (donutCanvas && donutData.length > 0) {
        const ctx = donutCanvas.getContext('2d');
        let slices = [];
        let hoveredSlice = -1;
        const total = donutData.reduce((acc, cur) => acc + cur.value, 0);

        if (legendBox) {
            legendBox.innerHTML = '';
            donutData.forEach(item => {
                const div = document.createElement('div');
                div.className = 'd-flex align-items-center me-2 mb-1';
                div.style.fontSize = '0.75rem';
                div.innerHTML = `
                    <span style="display:inline-block; width:10px; height:10px; background-color:${item.color}; border-radius:2px; margin-right:4px;"></span>
                    <span class="text-secondary">${item.label}:</span>
                    <strong class="ms-1 text-dark">${item.value} (${item.percent}%)</strong>
                `;
                legendBox.appendChild(div);
            });
        }

        function renderDonutChart() {
            const rect = donutCanvas.parentElement.getBoundingClientRect();
            if (!rect.width) return;

            const dpr = window.devicePixelRatio || 1;
            donutCanvas.width = rect.width * dpr;
            donutCanvas.height = rect.height * dpr;
            ctx.resetTransform?.();
            ctx.scale(dpr, dpr);

            const w = rect.width;
            const h = rect.height;
            ctx.clearRect(0, 0, w, h);

            if (total === 0) {
                ctx.fillStyle = '#6c757d';
                ctx.font = '14px system-ui';
                ctx.textAlign = 'center';
                ctx.fillText('Chưa có dữ liệu GPA học kỳ', w / 2, h / 2);
                return;
            }

            const cx = w / 2;
            const cy = h / 2;
            const radius = Math.min(cx, cy) - 15;
            const innerRadius = radius * 0.58;

            slices = [];
            let startAngle = -0.5 * Math.PI;

            donutData.forEach((item, idx) => {
                const sliceAngle = (item.value / total) * (Math.PI * 2);
                const endAngle = startAngle + sliceAngle;
                const isHovered = (hoveredSlice === idx);

                let ox = 0, oy = 0;
                if (isHovered) {
                    const mid = startAngle + sliceAngle / 2;
                    ox = Math.cos(mid) * 6;
                    oy = Math.sin(mid) * 6;
                }

                ctx.beginPath();
                ctx.arc(cx + ox, cy + oy, radius, startAngle, endAngle);
                ctx.arc(cx + ox, cy + oy, innerRadius, endAngle, startAngle, true);
                ctx.closePath();
                ctx.fillStyle = item.color;
                ctx.fill();

                slices.push({
                    start: startAngle,
                    end: endAngle,
                    data: item
                });

                startAngle = endAngle;
            });

            ctx.fillStyle = '#212529';
            ctx.font = 'bold 13px system-ui';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(`${total} sinh viên`, cx, cy);
        }

        donutCanvas.addEventListener('mousemove', function (e) {
            const rect = donutCanvas.getBoundingClientRect();
            const mx = e.clientX - rect.left;
            const my = e.clientY - rect.top;

            const cx = rect.width / 2;
            const cy = rect.height / 2;
            const dx = mx - cx;
            const dy = my - cy;
            const dist = Math.sqrt(dx * dx + dy * dy);

            const radius = Math.min(cx, cy) - 15;
            const innerRadius = radius * 0.58;

            let angle = Math.atan2(dy, dx);
            if (angle < -0.5 * Math.PI) angle += 2 * Math.PI;

            let found = -1;
            if (dist >= innerRadius && dist <= radius + 6) {
                for (let i = 0; i < slices.length; i++) {
                    if (angle >= slices[i].start && angle <= slices[i].end) {
                        found = i;
                        break;
                    }
                }
            }

            if (found !== hoveredSlice) {
                hoveredSlice = found;
                renderDonutChart();
            }

            if (found !== -1) {
                const s = slices[found].data;
                donutTooltip.style.display = 'block';
                donutTooltip.style.left = mx + 'px';
                donutTooltip.style.top = my + 'px';
                donutTooltip.innerHTML = `<strong>${s.label}:</strong> ${s.value} SV (${s.percent}%)`;
                donutCanvas.style.cursor = 'pointer';
            } else {
                donutTooltip.style.display = 'none';
                donutCanvas.style.cursor = 'default';
            }
        });

        donutCanvas.addEventListener('mouseleave', function () {
            hoveredSlice = -1;
            renderDonutChart();
            donutTooltip.style.display = 'none';
        });

        window.addEventListener('resize', renderDonutChart);
        renderDonutChart();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>