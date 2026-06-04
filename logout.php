<?php
// logout.php

// Khởi tạo session để có thể thao tác xóa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bước 1: Xóa toàn bộ biến trong mảng $_SESSION
$_SESSION = [];

// Bước 2: Xóa cookie session trên trình duyệt người dùng
// (Nếu không làm bước này, cookie cũ vẫn còn dù session server đã bị hủy)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,   // Đặt thời gian hết hạn về quá khứ để xóa cookie
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Bước 3: Hủy hoàn toàn session trên server
session_destroy();

// Bước 4: Redirect về trang chủ
header('Location: /bookstore/index.php');
exit;