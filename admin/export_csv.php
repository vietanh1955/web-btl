<?php
// admin/export_csv.php - Bộ xuất CSV tập trung cho toàn hệ thống
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$type = trim($_GET['type'] ?? '');

// Hàm gửi header tải file CSV với UTF-8 BOM (tránh lỗi font tiếng Việt trên Excel)
function init_csv_download($filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    // Ghi UTF-8 BOM
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    return $output;
}

switch ($type) {
    // ==========================================
    // 1. XUẤT DANH SÁCH HỒ SƠ SINH VIÊN
    // ==========================================
    case 'students':
        $search = trim($_GET['search'] ?? '');
        $class  = trim($_GET['class'] ?? '');

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = "(sv.ma_sv LIKE ? OR sv.ho_ten LIKE ? OR sv.email LIKE ?)";
            $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
        }
        if ($class !== '') {
            $where[] = "sv.ma_lop = ?";
            $params[] = $class;
        }
        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $pdo->prepare("
            SELECT sv.ma_sv, sv.ho_ten, sv.ngay_sinh, sv.gioi_tinh, sv.ma_lop, l.ten_lop, sv.email, sv.so_dt
            FROM sinhvien sv
            JOIN lop l ON sv.ma_lop = l.ma_lop
            $where_sql
            ORDER BY sv.ma_sv ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = init_csv_download('danh_sach_sinh_vien_' . date('Ymd_His') . '.csv');
        fputcsv($out, ['Mã SV', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Mã lớp', 'Tên lớp', 'Email', 'Số điện thoại']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['ma_sv'],
                $r['ho_ten'],
                $r['ngay_sinh'],
                $r['gioi_tinh'],
                $r['ma_lop'],
                $r['ten_lop'],
                $r['email'],
                $r['so_dt']
            ]);
        }
        fclose($out);
        exit;

    // ==========================================
    // 2. XUẤT BẢNG ĐIỂM THEO LỚP & MÔN HỌC
    // ==========================================
    case 'grades':
        $ma_lop = trim($_GET['ma_lop'] ?? '');
        $ma_mh  = trim($_GET['ma_mh'] ?? '');
        $hoc_ky = trim($_GET['hoc_ky'] ?? '');

        if ($ma_lop === '' || $ma_mh === '' || $hoc_ky === '') {
            exit('Thiếu tham số mã lớp, môn học hoặc học kỳ!');
        }

        $stmt = $pdo->prepare("
            SELECT sv.ma_sv, sv.ho_ten,
                   d.diem_chuyen_can, d.diem_giua_ky, d.diem_cuoi_ky, d.diem_tong
            FROM sinhvien sv
            LEFT JOIN diem d ON sv.ma_sv = d.ma_sv AND d.ma_mh = ? AND d.hoc_ky = ?
            WHERE sv.ma_lop = ?
            ORDER BY sv.ma_sv ASC
        ");
        $stmt->execute([$ma_mh, $hoc_ky, $ma_lop]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = init_csv_download("bang_diem_{$ma_lop}_{$ma_mh}_{$hoc_ky}.csv");
        fputcsv($out, ['STT', 'Mã SV', 'Họ và tên', 'Chuyên cần (10%)', 'Giữa kỳ (30%)', 'Cuối kỳ (60%)', 'Điểm tổng', 'Điểm chữ']);

        foreach ($rows as $idx => $r) {
            $tong = $r['diem_tong'] !== null ? (float)$r['diem_tong'] : '';
            $conv = convert_grade($tong);
            fputcsv($out, [
                $idx + 1,
                $r['ma_sv'],
                $r['ho_ten'],
                $r['diem_chuyen_can'] !== null ? $r['diem_chuyen_can'] : '',
                $r['diem_giua_ky'] !== null ? $r['diem_giua_ky'] : '',
                $r['diem_cuoi_ky'] !== null ? $r['diem_cuoi_ky'] : '',
                $tong !== '' ? number_format($tong, 2) : '',
                $tong !== '' ? $conv['chu'] : ''
            ]);
        }
        fclose($out);
        exit;

    // ==========================================
    // 3. XUẤT BÁO CÁO HỌC TẬP / GPA TÍCH LŨY
    // ==========================================
    case 'reports':
        $filter_class = trim($_GET['class'] ?? '');
        $filter_sem   = trim($_GET['semester'] ?? '');

        $where = $filter_class !== '' ? "WHERE sv.ma_lop = ?" : "";
        $params = $filter_class !== '' ? [$filter_class] : [];

        $stmt = $pdo->prepare("
            SELECT sv.ma_sv, sv.ho_ten, sv.ma_lop, l.ten_lop
            FROM sinhvien sv
            JOIN lop l ON sv.ma_lop = l.ma_lop
            $where
            ORDER BY sv.ma_sv ASC
        ");
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = init_csv_download('bao_cao_gpa_' . date('Ymd_His') . '.csv');
        fputcsv($out, ['STT', 'Mã SV', 'Họ và tên', 'Lớp', 'Tín chỉ tích lũy', 'GPA (Hệ 4)', 'Xếp loại']);

        $i = 1;
        foreach ($students as $sv) {
            $calc = calculate_student_gpa($pdo, $sv['ma_sv'], $filter_sem ?: null);
            fputcsv($out, [
                $i++,
                $sv['ma_sv'],
                $sv['ho_ten'],
                $sv['ma_lop'],
                (int)$calc['tin_chi_tich_luy'],
                number_format($calc['gpa'], 2),
                $calc['xep_loai']
            ]);
        }
        fclose($out);
        exit;

    default:
        exit('Yêu cầu xuất dữ liệu không hợp lệ!');
}