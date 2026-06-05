<?php
// fix.php
require_once __DIR__ . '/config/db.php';

// Để chính PHP trên máy bạn tự mã hóa số 123456
$hash = password_hash('123456', PASSWORD_DEFAULT);

// Cập nhật thẳng vào Database
$pdo->query("UPDATE users SET password = '$hash' WHERE username = 'admin'");

echo "<h1>Đã khôi phục thành công!</h1>";
echo "<p>Mật khẩu của admin giờ chắc chắn là: <strong>123456</strong></p>";
?>