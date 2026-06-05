<?php
// actions/add_to_cart.php
// File này CHỈ xử lý logic — không có giao diện HTML

require_once __DIR__ . '/../config/db.php';

// Khởi tạo session để kiểm tra trạng thái đăng nhập
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. KIỂM TRA ĐĂNG NHẬP ────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    // Lưu thông báo yêu cầu đăng nhập
    $_SESSION['sweet_alert'] = [
        'icon'  => 'warning',
        'title' => 'Chưa đăng nhập',
        'text'  => 'Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng.'
    ];
    header('Location: ../login.php');
    exit;
}

// ── 2. CHỈ CHẤP NHẬN METHOD POST ─────────────────────────────────────────────
// Chặn truy cập trực tiếp qua URL (GET request)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// ── 3. LẤY VÀ VALIDATE DỮ LIỆU ĐẦU VÀO ─────────────────────────────────────
$userId  = (int) $_SESSION['user_id'];
$bookId  = filter_input(INPUT_POST, 'book_id',  FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

// Kiểm tra book_id và quantity phải là số nguyên dương hợp lệ
if (!$bookId || $bookId <= 0 || !$quantity || $quantity <= 0) {
    $_SESSION['sweet_alert'] = [
        'icon'  => 'error',
        'title' => 'Lỗi dữ liệu',
        'text'  => 'Số lượng hoặc sản phẩm không hợp lệ.'
    ];
    header('Location: ../index.php');
    exit;
}

// ── 4. KIỂM TRA SÁCH CÓ TỒN TẠI VÀ CÒN HÀNG KHÔNG ─────────────────────────
$stmtBook = $pdo->prepare("
    SELECT id, stock_quantity, title
    FROM   books
    WHERE  id = ?
    LIMIT  1
");
$stmtBook->execute([$bookId]);
$book = $stmtBook->fetch();

// Sách không tồn tại
if (!$book) {
    $_SESSION['sweet_alert'] = [
        'icon'  => 'error',
        'title' => 'Lỗi',
        'text'  => 'Không tìm thấy sản phẩm này.'
    ];
    header('Location: ../index.php');
    exit;
}

// Sách hết hàng → redirect về trang chi tiết với thông báo lỗi
if ($book['stock_quantity'] <= 0) {
    $_SESSION['sweet_alert'] = [
        'icon'  => 'error',
        'title' => 'Hết hàng',
        'text'  => 'Rất tiếc, sản phẩm này hiện đã hết hàng.'
    ];
    header("Location: ../product.php?id={$bookId}");
    exit;
}

// Giới hạn số lượng đặt không vượt quá tồn kho thực tế
$quantity = min($quantity, $book['stock_quantity']);

// ── 5. KIỂM TRA SÁCH ĐÃ CÓ TRONG GIỎ HÀNG CỦA USER CHƯA ───────────────────
$stmtCheck = $pdo->prepare("
    SELECT quantity
    FROM   cart
    WHERE  user_id = ?
      AND  book_id = ?
    LIMIT  1
");
$stmtCheck->execute([$userId, $bookId]);
$cartItem = $stmtCheck->fetch();

if ($cartItem) {
    // ── 5a. ĐÃ CÓ → UPDATE CỘNG DỒN QUANTITY ────────────────────────────────
    $newQuantity = $cartItem['quantity'] + $quantity;
    $newQuantity = min($newQuantity, $book['stock_quantity']); // Không vượt quá kho

    $stmtUpdate = $pdo->prepare("
        UPDATE cart
        SET    quantity = ?
        WHERE  user_id  = ?
          AND  book_id  = ?
    ");
    $stmtUpdate->execute([$newQuantity, $userId, $bookId]);

} else {
    // ── 5b. CHƯA CÓ → INSERT MỚI VÀO GIỎ HÀNG ───────────────────────────────
    $stmtInsert = $pdo->prepare("
        INSERT INTO cart (user_id, book_id, quantity)
        VALUES (?, ?, ?)
    ");
    $stmtInsert->execute([$userId, $bookId, $quantity]);
}

// ── 6. LƯU THÔNG BÁO THÀNH CÔNG VÀ CHUYỂN HƯỚNG ────────────────────────────
$_SESSION['sweet_alert'] = [
    'icon'     => 'success',
    'title'    => 'Đã thêm vào giỏ',
    'text'     => '« ' . htmlspecialchars($book['title']) . ' » đã được thêm vào giỏ hàng!',
    'toast'    => true,           // Dùng dạng toast (thông báo nhỏ gọn)
    'position' => 'top-end'       // Hiển thị ở góc trên bên phải
];

header('Location: ../cart.php');
exit;