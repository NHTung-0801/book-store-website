<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'bookstore_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Ném exception khi có lỗi
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Trả về mảng kết hợp
    PDO::ATTR_EMULATE_PREPARES   => false,                    // Dùng prepared statement thật
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Production: ghi log thay vì hiển thị lỗi ra màn hình
    error_log("DB Connection Error: " . $e->getMessage());
    die(json_encode(['error' => 'Không thể kết nối cơ sở dữ liệu. Vui lòng thử lại sau.']));
}