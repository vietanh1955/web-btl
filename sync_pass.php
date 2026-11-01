<?php
// BTL/sync_pass.php
require_once __DIR__ . '/includes/functions.php';

try {
    // 1. Đồng bộ mật khẩu admin thành admin123
    $pass_admin = password_hash('admin123', PASSWORD_DEFAULT);
    $stmtAdmin = $pdo->prepare("UPDATE taikhoan SET password = ?, doi_mat_khau = 0 WHERE username = 'admin'");
    $stmtAdmin->execute([$pass_admin]);

    // 2. Đồng bộ tất cả tài khoản sinh viên theo đúng ngày sinh DDMMYYYY
    $sql = "
        SELECT tk.username, sv.ngay_sinh 
        FROM taikhoan tk 
        JOIN sinhvien sv ON tk.ma_sv = sv.ma_sv
    ";
    $students = $pdo->query($sql)->fetchAll();

    $stmtUpd = $pdo->prepare("UPDATE taikhoan SET password = ?, doi_mat_khau = 1 WHERE username = ?");

    $count = 0;
    foreach ($students as $st) {
        $raw_pass = date('dmY', strtotime($st['ngay_sinh']));
        $hash     = password_hash($raw_pass, PASSWORD_DEFAULT);
        $stmtUpd->execute([$hash, $st['username']]);
        $count++;
    }

    echo "<h1>ĐỒNG BỘ MẬT KHẨU THÀNH CÔNG!</h1>";
    echo "<ul>";
    echo "<li>Tài khoản <strong>admin</strong>: Mật khẩu là <code>admin123</code> (Đổi mật khẩu: KHÔNG)</li>";
    echo "<li>Đã cập nhật <strong>{$count} tài khoản sinh viên</strong> theo đúng ngày sinh <code>DDMMYYYY</code> (Đổi mật khẩu: CÓ)</li>";
    echo "</ul>";
    echo "<p><a href='login.php'>Bấm vào đây để tới trang Đăng nhập</a></p>";

} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage();
}