<?php
// actions/remove_cart.php
// Xóa một sách khỏi giỏ hàng — không có giao diện

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. KIỂM TRA ĐĂNG NHẬP ────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    $_SESSION['sweet_alert'] = [
        'icon'  => 'warning',
        'title' => 'Chưa đăng nhập',
        'text'  => 'Vui lòng đăng nhập để thao tác với giỏ hàng.'
    ];
    header('Location: ../login.php');
    exit;
}

// ── 2. CHỈ NHẬN POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cart.php');
    exit;
}

// ── 3. VALIDATE DỮ LIỆU ──────────────────────────────────────────────────────
$userId = (int) $_SESSION['user_id'];
$bookId = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);

if (!$bookId || $bookId <= 0) {
    $_SESSION['sweet_alert'] = [
        'icon'     => 'error',
        'title'    => 'Lỗi dữ liệu',
        'text'     => 'Sản phẩm không hợp lệ.',
        'toast'    => true,
        'position' => 'top-end'
    ];
    header('Location: ../cart.php');
    exit;
}

// ── 4. LẤY TÊN SÁCH ĐỂ HIỂN THỊ THÔNG BÁO CHO ĐẸP ─────────────────────────────
$stmtBook = $pdo->prepare("SELECT title FROM books WHERE id = ? LIMIT 1");
$stmtBook->execute([$bookId]);
$bookTitle = $stmtBook->fetchColumn();

// Nếu vì lý do nào đó không tìm thấy tên sách, dùng chữ "Sản phẩm" làm mặc định
$displayName = $bookTitle ? htmlspecialchars($bookTitle) : 'Sản phẩm';

// ── 5. XÓA KHỎI GIỎ HÀNG ────────────────────────────────────────────────────
// Điều kiện WHERE gồm cả user_id để đảm bảo user chỉ xóa được sách của mình
$stmt = $pdo->prepare("
    DELETE FROM cart
    WHERE  user_id = ?
      AND  book_id = ?
");
$stmt->execute([$userId, $bookId]);

// ── 6. LƯU THÔNG BÁO THÀNH CÔNG VÀ CHUYỂN HƯỚNG ──────────────────────────────
$_SESSION['sweet_alert'] = [
    'icon'     => 'success',
    'title'    => 'Đã xóa',
    'text'     => '« ' . $displayName . ' » đã được xóa khỏi giỏ hàng.',
    'toast'    => true,
    'position' => 'top-end'
];

header('Location: ../cart.php');
exit;