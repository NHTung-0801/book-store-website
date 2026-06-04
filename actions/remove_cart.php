<?php
// actions/remove_cart.php
// Xóa một sách khỏi giỏ hàng — không có giao diện

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. KIỂM TRA ĐĂNG NHẬP ────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
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
    header('Location: ../cart.php?status=error');
    exit;
}

// ── 4. XÓA KHỎI GIỎ HÀNG ────────────────────────────────────────────────────
// Điều kiện WHERE gồm cả user_id để đảm bảo user chỉ xóa được sách của mình
$stmt = $pdo->prepare("
    DELETE FROM cart
    WHERE  user_id = ?
      AND  book_id = ?
");
$stmt->execute([$userId, $bookId]);

header('Location: ../cart.php?status=removed');
exit;