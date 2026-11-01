<?php
// includes/functions.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

// 1. Làm sạch chuỗi chống XSS
function h($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// 2. Chuyển hướng nhanh
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

// 3. Quy đổi điểm hệ 10 sang điểm Chữ, Hệ 4 và trạng thái Đạt
function convert_grade($diem10): array {
    if ($diem10 === null || $diem10 === '') {
        return ['chu' => '-', 'he4' => 0.0, 'dat' => false];
    }
    $d = (float)$diem10;
    if ($d >= 8.5) return ['chu' => 'A',  'he4' => 4.0, 'dat' => true];
    if ($d >= 8.0) return ['chu' => 'B+', 'he4' => 3.5, 'dat' => true];
    if ($d >= 7.0) return ['chu' => 'B',  'he4' => 3.0, 'dat' => true];
    if ($d >= 6.5) return ['chu' => 'C+', 'he4' => 2.5, 'dat' => true];
    if ($d >= 5.5) return ['chu' => 'C',  'he4' => 2.0, 'dat' => true];
    if ($d >= 5.0) return ['chu' => 'D+', 'he4' => 1.5, 'dat' => true];
    if ($d >= 4.0) return ['chu' => 'D',  'he4' => 1.0, 'dat' => true];
    return ['chu' => 'F', 'he4' => 0.0, 'dat' => false];
}

// 4. Xếp loại học lực theo GPA hệ 4
function get_academic_rank(float $gpa): string {
    if ($gpa >= 3.6) return 'Xuất sắc';
    if ($gpa >= 3.2) return 'Giỏi';
    if ($gpa >= 2.5) return 'Khá';
    if ($gpa >= 2.0) return 'Trung bình';
    return 'Yếu';
}

// 5. Tính GPA có trọng số tín chỉ (Nếu học lại cùng mã môn thì chỉ lấy lần điểm cao nhất)
function calculate_student_gpa(PDO $pdo, string $ma_sv, ?string $hoc_ky = null): array {
    $sql = "
        SELECT d.ma_mh, d.diem_tong, d.hoc_ky, mh.ten_mh, mh.so_tin_chi
        FROM diem d
        JOIN mon_hoc mh ON d.ma_mh = mh.ma_mh
        WHERE d.ma_sv = ?
    ";
    $params = [$ma_sv];

    if ($hoc_ky !== null && $hoc_ky !== '') {
        $sql .= " AND d.hoc_ky = ?";
        $params[] = $hoc_ky;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Lọc lấy bản ghi có diem_tong cao nhất cho mỗi môn học
    $best_grades = [];
    foreach ($rows as $r) {
        $mmh = $r['ma_mh'];
        if (!isset($best_grades[$mmh]) || (float)$r['diem_tong'] > (float)$best_grades[$mmh]['diem_tong']) {
            $best_grades[$mmh] = $r;
        }
    }

    $tong_diem_he4_nhan_tc = 0.0;
    $tong_tin_chi = 0;
    $tin_chi_tich_luy = 0;
    $mon_no = [];

    foreach ($best_grades as $g) {
        $stc = (int)$g['so_tin_chi'];
        $conv = convert_grade($g['diem_tong']);

        $tong_tin_chi += $stc;
        $tong_diem_he4_nhan_tc += ($conv['he4'] * $stc);

        if ($conv['dat']) {
            $tin_chi_tich_luy += $stc;
        } else {
            $mon_no[] = [
                'ma_mh'      => $g['ma_mh'],
                'ten_mh'     => $g['ten_mh'],
                'so_tin_chi' => $stc,
                'hoc_ky'     => $g['hoc_ky'],
                'diem_tong'  => (float)$g['diem_tong'],
                'diem_chu'   => $conv['chu']
            ];
        }
    }

    $gpa = ($tong_tin_chi > 0) ? round($tong_diem_he4_nhan_tc / $tong_tin_chi, 2) : 0.00;

    return [
        'gpa'              => $gpa,
        'xep_loai'         => ($tong_tin_chi > 0) ? get_academic_rank($gpa) : 'Chưa xếp loại',
        'tin_chi_tich_luy' => $tin_chi_tich_luy,
        'tong_tin_chi'     => $tong_tin_chi,
        'mon_no'           => $mon_no
    ];
}

/**
 * 6. Tự động đồng bộ tài khoản ngầm (Silent Auto-Sync)
 * Tự động tạo và hash mật khẩu ngày sinh chuẩn Bcrypt cho sinh viên
 */
function auto_sync_student_accounts(PDO $pdo): void {
    // 1. Kiểm tra tài khoản admin (admin / admin123)
    $admin_check = $pdo->query("SELECT id, password FROM taikhoan WHERE username = 'admin'")->fetch();
    if (!$admin_check || !password_verify('admin123', $admin_check['password'])) {
        $hash_admin = password_hash('admin123', PASSWORD_DEFAULT);
        if ($admin_check) {
            $pdo->prepare("UPDATE taikhoan SET password = ?, doi_mat_khau = 0 WHERE username = 'admin'")->execute([$hash_admin]);
        } else {
            $pdo->prepare("INSERT INTO taikhoan (username, password, role, doi_mat_khau) VALUES ('admin', ?, 'admin', 0)")->execute([$hash_admin]);
        }
    }

    // 2. Tự động cấp tài khoản cho sinh viên chưa có trong bảng taikhoan
    $missing_students = $pdo->query("
        SELECT sv.ma_sv, sv.ngay_sinh 
        FROM sinhvien sv 
        LEFT JOIN taikhoan tk ON sv.ma_sv = tk.ma_sv 
        WHERE tk.id IS NULL
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($missing_students)) {
        $stmt_ins = $pdo->prepare("
            INSERT INTO taikhoan (username, password, role, ma_sv, doi_mat_khau) 
            VALUES (?, ?, 'sinhvien', ?, 1)
        ");
        foreach ($missing_students as $st) {
            $raw_pass = date('dmY', strtotime($st['ngay_sinh']));
            $stmt_ins->execute([$st['ma_sv'], password_hash($raw_pass, PASSWORD_DEFAULT), $st['ma_sv']]);
        }
    }
}

// Chạy ngầm mỗi khi hệ thống khởi động
if (isset($pdo)) {
    auto_sync_student_accounts($pdo);
}