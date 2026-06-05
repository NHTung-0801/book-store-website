<?php
// reset_pass.php
require_once __DIR__ . '/config/db.php';

// Mật khẩu mới bạn muốn đặt
$new_password = '123456';

// Dùng hàm chuẩn của PHP để băm mật khẩu
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    // Cập nhật vào cơ sở dữ liệu cho tài khoản 'admin'
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$hashed_password]);
    
    echo "<h1>Thành công!</h1>";
    echo "<p>Mật khẩu của tài khoản <strong>admin</strong> đã được đổi thành: <strong>123456</strong></p>";
    echo "<p>Hãy quay lại <a href='/bookstore/login.php'>Trang đăng nhập</a> để thử lại.</p>";
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage();
}
?>