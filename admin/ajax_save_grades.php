<?php
// admin/ajax_save_grades.php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');
require_role('admin');

verify_csrf_token($_POST['csrf_token'] ?? '');

$ma_mh     = trim($_POST['ma_mh'] ?? '');
$hoc_ky    = trim($_POST['hoc_ky'] ?? '');
$bang_diem = $_POST['diem'] ?? [];

if ($ma_mh === '' || $hoc_ky === '' || empty($bang_diem) || !is_array($bang_diem)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Dữ liệu gửi lên không hợp lệ hoặc danh sách điểm trống!'
    ]);
    exit;
}

$ds_loi = [];

// BƯỚC 1: Kiểm duyệt toàn bộ dữ liệu trước khi mở Transaction
foreach ($bang_diem as $ma_sv => $cot) {
    $cc_raw = trim((string)($cot['cc'] ?? ''));
    $gk_raw = trim((string)($cot['gk'] ?? ''));
    $ck_raw = trim((string)($cot['ck'] ?? ''));

    $fields = [
        'Chuyên cần' => $cc_raw,
        'Giữa kỳ'    => $gk_raw,
        'Cuối kỳ'    => $ck_raw
    ];

    foreach ($fields as $ten_cot => $val) {
        if ($val === '') {
            $ds_loi[] = "SV $ma_sv: Điểm $ten_cot đang bị bỏ trống.";
        } elseif (!is_numeric($val)) {
            $ds_loi[] = "SV $ma_sv: Điểm $ten_cot ('$val') không phải là số hợp lệ.";
        } else {
            $num = (float)$val;
            if ($num < 0 || $num > 10) {
                $ds_loi[] = "SV $ma_sv: Điểm $ten_cot ($val) nằm ngoài dải [0.0 - 10.0].";
            }
        }
    }
}

// Nếu có bất kỳ ô nào lỗi -> Rollback ngay, trả về danh sách ô sai
if (!empty($ds_loi)) {
    echo json_encode([
        'status' => 'error',
        'errors' => $ds_loi
    ]);
    exit;
}

// BƯỚC 2: Thực thi trong 1 TRANSACTION duy nhất
try {
    $pdo->beginTransaction();

    $sql = "
        INSERT INTO diem (ma_sv, ma_mh, hoc_ky, diem_chuyen_can, diem_giua_ky, diem_cuoi_ky)
        VALUES (:ma_sv, :ma_mh, :hoc_ky, :cc, :gk, :ck)
        ON DUPLICATE KEY UPDATE
            diem_chuyen_can = VALUES(diem_chuyen_can),
            diem_giua_ky    = VALUES(diem_giua_ky),
            diem_cuoi_ky    = VALUES(diem_cuoi_ky)
    ";
    $stmt = $pdo->prepare($sql);

    foreach ($bang_diem as $ma_sv => $cot) {
        $stmt->execute([
            ':ma_sv'  => $ma_sv,
            ':ma_mh'  => $ma_mh,
            ':hoc_ky' => $hoc_ky,
            ':cc'     => round((float)$cot['cc'], 2),
            ':gk'     => round((float)$cot['gk'], 2),
            ':ck'     => round((float)$cot['ck'], 2)
        ]);
    }

    $pdo->commit();
    echo json_encode([
        'status'  => 'success',
        'message' => 'Đã lưu điểm thành công cho toàn bộ ' . count($bang_diem) . ' sinh viên!'
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'error',
        'errors' => ['Lỗi cơ sở dữ liệu: ' . $e->getMessage()]
    ]);
}