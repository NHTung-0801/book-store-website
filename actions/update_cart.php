<?php
// actions/update_cart.php
// Cập nhật số lượng sách trong giỏ hàng — không có giao diện

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
    header('Location: ../pages/auth/login.php');
    exit;
}

// ── 2. CHỈ NHẬN POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/shop/cart.php');
    exit;
}

// ── 3. VALIDATE DỮ LIỆU ──────────────────────────────────────────────────────
$userId   = (int) $_SESSION['user_id'];
$bookId   = filter_input(INPUT_POST, 'book_id',  FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if (!$bookId || $bookId <= 0 || !$quantity || $quantity <= 0) {
    $_SESSION['sweet_alert'] = [
        'icon'     => 'error',
        'title'    => 'Lỗi dữ liệu',
        'text'     => 'Số lượng hoặc sản phẩm không hợp lệ.',
        'toast'    => true,
        'position' => 'top-end'
    ];
    header('Location: ../pages/shop/cart.php');
    exit;
}

// ── 4. KIỂM TRA TỒN KHO THỰC TẾ ─────────────────────────────────────────────
// Cập nhật: Lấy thêm title để hiển thị thông báo cho đẹp
$stmtStock = $pdo->prepare("SELECT stock_quantity, title FROM books WHERE id = ? LIMIT 1");
$stmtStock->execute([$bookId]);
$book = $stmtStock->fetch();

if (!$book) {
    $_SESSION['sweet_alert'] = [
        'icon'     => 'error',
        'title'    => 'Lỗi',
        'text'     => 'Không tìm thấy sản phẩm này.',
        'toast'    => true,
        'position' => 'top-end'
    ];
    header('Location: ../pages/shop/cart.php');
    exit;
}

// Giới hạn số lượng không vượt quá tồn kho
$quantity = min($quantity, $book['stock_quantity']);

// ── 5. CẬP NHẬT SỐ LƯỢNG TRONG GIỎ ──────────────────────────────────────────
// Chỉ update nếu item đó thực sự thuộc về user này (bảo mật)
$stmt = $pdo->prepare("
    UPDATE cart
    SET    quantity = ?
    WHERE  user_id  = ?
      AND  book_id  = ?
");
$stmt->execute([$quantity, $userId, $bookId]);

// ── 6. LƯU THÔNG BÁO VÀ CHUYỂN HƯỚNG ─────────────────────────────────────────
$_SESSION['sweet_alert'] = [
    'icon'     => 'success',
    'title'    => 'Đã cập nhật',
    'text'     => 'Số lượng của « ' . htmlspecialchars($book['title']) . ' » đã thay đổi.',
    'toast'    => true,
    'position' => 'top-end'
];

header('Location: ../pages/shop/cart.php');
exit;