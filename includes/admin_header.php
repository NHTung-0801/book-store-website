<?php
// includes/admin_header.php

// 1. Khởi tạo session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. BỨC TƯỜNG BẢO MẬT
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: /bookstore/pages/auth/login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['fullname']);
$baseUrl = '/bookstore';
$current_page = basename($_SERVER['PHP_SELF']);

// 3. ĐẾM SỐ ĐƠN HÀNG CHỜ XÁC NHẬN ĐỂ HIỂN THỊ THÔNG BÁO ĐỎ
require_once __DIR__ . '/../config/db.php';
$stmtPending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$pendingOrdersCount = (int) $stmtPending->fetchColumn();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị - Book Store</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= $baseUrl ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= $baseUrl ?>/assets/css/admin_style.css" rel="stylesheet">
</head>
<body>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <a href="<?= $baseUrl ?>/admin/index.php" class="text-decoration-none d-flex align-items-center gap-2">
            <i class="bi bi-book-half text-warning fs-4"></i>
            <div>
                <div class="text-white fw-bold lh-1">Book Store</div>
                <div class="text-warning" style="font-size:.7rem;">Admin Panel</div>
            </div>
        </a>
    </div>

    <nav class="mt-2 pb-4">
        <div class="nav-section">Tổng quan</div>
        <a href="<?= $baseUrl ?>/admin/index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>

        <div class="nav-section">Quản lý</div>
        <a href="<?= $baseUrl ?>/admin/books.php" class="nav-link <?= ($current_page == 'books.php') ? 'active' : '' ?>">
            <i class="bi bi-book"></i>Quản lý sách
        </a>
        <a href="<?= $baseUrl ?>/admin/categories.php" class="nav-link <?= ($current_page == 'categories.php') ? 'active' : '' ?>">
            <i class="bi bi-tags"></i>Thể loại
        </a>
        
        <a href="<?= $baseUrl ?>/admin/orders.php" class="nav-link <?= ($current_page == 'orders.php') ? 'active' : '' ?> d-flex align-items-center">
            <i class="bi bi-bag-check"></i>Đơn hàng
            <?php if ($pendingOrdersCount > 0): ?>
                <span class="badge bg-danger rounded-pill ms-auto"><?= $pendingOrdersCount ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= $baseUrl ?>/admin/users.php" class="nav-link <?= ($current_page == 'users.php') ? 'active' : '' ?>">
            <i class="bi bi-people"></i>Thành viên
        </a>

        <div class="nav-section">Hệ thống</div>
        <a href="<?= $baseUrl ?>/index.php" class="nav-link" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i>Xem website
        </a>
        <a href="<?= $baseUrl ?>/pages/auth/logout.php" class="nav-link text-danger-emphasis">
            <i class="bi bi-box-arrow-right"></i>Đăng xuất
        </a>
    </nav>
</aside>

<div class="admin-main">
    <div class="admin-topbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div>
                <h5 class="mb-0 fw-bold">
                    <?php 
                        $titles = [
                            'index.php' => 'Dashboard',
                            'books.php' => 'Quản lý sách',
                            'categories.php' => 'Quản lý thể loại',
                            'orders.php' => 'Quản lý đơn hàng',
                            'users.php' => 'Quản lý thành viên'
                        ];
                        echo $titles[$current_page] ?? 'Bảng điều khiển';
                    ?>
                </h5>
                <p class="text-muted small mb-0"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y') ?></p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="text-end d-none d-sm-block">
                <p class="mb-0 fw-semibold small"><?= $adminName ?></p>
                <p class="text-muted mb-0" style="font-size:.75rem;">Quản trị viên</p>
            </div>
            <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center fw-bold text-dark" style="width:38px;height:38px;font-size:.9rem;">
                <?= strtoupper(mb_substr($adminName, 0, 1)) ?>
            </div>
        </div>
    </div>

    <div class="p-4 flex-grow-1">