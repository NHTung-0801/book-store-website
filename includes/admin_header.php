<?php
// includes/admin_header.php

// 1. Khởi tạo session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. BỨC TƯỜNG BẢO MẬT (Rất quan trọng)
// Kiểm tra nếu chưa đăng nhập HOẶC role không phải là 1 (Admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    // Ngay lập tức chuyển hướng về trang đăng nhập hoặc trang chủ
    header('Location: /bookstore/login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['fullname']);
$baseUrl = '/bookstore';

// Lấy tên file hiện tại để làm sáng (active) menu tương ứng
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị - Book Store</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" 
          rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" 
          rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9; /* Màu nền xám nhạt giúp làm nổi bật các bảng dữ liệu */
        }
        .admin-navbar {
            background-color: #0f3460; /* Màu xanh đậm khác biệt với trang khách để dễ nhận diện */
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark admin-navbar shadow-sm sticky-top">
    <div class="container-fluid px-4">
        
        <a class="navbar-brand fw-bold" href="<?= $baseUrl ?>/admin/index.php">
            <i class="bi bi-shield-lock-fill text-warning me-2"></i>Admin Panel
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'index.php') ? 'active fw-bold text-warning' : '' ?>" 
                       href="<?= $baseUrl ?>/admin/index.php">
                        <i class="bi bi-speedometer2 me-1"></i>Tổng quan
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'categories.php') ? 'active fw-bold text-warning' : '' ?>" 
                       href="<?= $baseUrl ?>/admin/categories.php">
                        <i class="bi bi-tags me-1"></i>Thể loại
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'books.php') ? 'active fw-bold text-warning' : '' ?>" 
                       href="<?= $baseUrl ?>/admin/books.php">
                        <i class="bi bi-book me-1"></i>Sách
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'orders.php') ? 'active fw-bold text-warning' : '' ?>" 
                       href="<?= $baseUrl ?>/admin/orders.php">
                        <i class="bi bi-cart-check me-1"></i>Đơn hàng
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'users.php') ? 'active fw-bold text-warning' : '' ?>" 
                       href="<?= $baseUrl ?>/admin/users.php">
                        <i class="bi bi-people me-1"></i>Người dùng
                    </a>
                </li>

            </ul>

            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3">
                    <a class="nav-link text-light" href="<?= $baseUrl ?>/index.php" target="_blank" title="Mở trang khách hàng trong thẻ mới">
                        <i class="bi bi-shop me-1"></i>Xem Cửa hàng
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-badge fs-5 me-1"></i> <?= $adminName ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <a class="dropdown-item text-danger" href="<?= $baseUrl ?>/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid p-4">