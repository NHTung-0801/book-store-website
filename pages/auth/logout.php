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

// Bước 3: Hủy hoàn toàn session đăng nhập cũ trên server
session_destroy();

// ==============================================================================
// Bước 4: TÍCH HỢP SWEETALERT2
// Khởi tạo một session hoàn toàn mới chỉ để chứa thông báo đăng xuất thành công
// ==============================================================================
session_start();
$_SESSION['sweet_alert'] = [
    'icon'  => 'success',
    'title' => 'Đã đăng xuất',
    'text'  => 'Hẹn gặp lại bạn lần sau!',
    'toast' => true, // Có thể bật dạng toast (thông báo nhỏ góc màn hình) nếu muốn
    'position' => 'center'
];

// Bước 5: Redirect về trang chủ
header('Location: /bookstore/index.php');
exit;