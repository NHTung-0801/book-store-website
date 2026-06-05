<?php
// includes/header.php
ob_start();
// Khởi tạo session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập và phân quyền
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin    = $isLoggedIn && $_SESSION['role'] == 1;
$fullname   = $isLoggedIn ? htmlspecialchars($_SESSION['fullname']) : '';

// Xác định đường dẫn gốc để dùng cho href (tránh lỗi đường dẫn tương đối)
$baseUrl = '/bookstore';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Store</title>

    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <!-- CSS tùy chỉnh riêng của dự án -->
    <link href="<?= $baseUrl ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ========== NAVBAR ========== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow">
    <div class="container">

        <!-- Logo / Tên thương hiệu -->
        <a class="navbar-brand fw-bold fs-4" href="<?= $baseUrl ?>/index.php">
            <i class="bi bi-book-half me-2 text-warning"></i>Book Store
        </a>

        <!-- Nút toggle cho màn hình nhỏ (mobile) -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nội dung Navbar (co lại trên mobile) -->
        <div class="collapse navbar-collapse" id="mainNavbar">

            <!-- Menu bên trái -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/index.php">
                        <i class="bi bi-house-door me-1"></i>Trang chủ
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>
                    <!-- Giỏ hàng — chỉ hiện khi đã đăng nhập -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>/cart.php">
                            <i class="bi bi-cart3 me-1"></i>Giỏ hàng
                        </a>
                    </li>

                    <!-- Đơn hàng của tôi -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>/my_orders.php">
                            <i class="bi bi-bag-check me-1"></i>Đơn hàng của tôi
                        </a>
                    </li>

                    <?php if ($isAdmin): ?>
                        <!-- Chỉ Admin mới thấy link vào trang quản trị -->
                        <li class="nav-item">
                            <a class="nav-link text-warning fw-semibold"
                               href="<?= $baseUrl ?>/admin/index.php">
                                <i class="bi bi-shield-lock me-1"></i>Quản trị
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- Menu bên phải (auth actions) -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">

                <?php if (!$isLoggedIn): ?>
                    <!-- Chưa đăng nhập: hiện Đăng nhập + Đăng ký -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $baseUrl ?>/login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-warning btn-sm ms-2 px-3"
                           href="<?= $baseUrl ?>/register.php">
                            <i class="bi bi-person-plus me-1"></i>Đăng ký
                        </a>
                    </li>

                <?php else: ?>
                    <!-- Đã đăng nhập: hiện dropdown tên User -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                           href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle fs-5"></i>
                            <span>Xin chào, <strong><?= $fullname ?></strong></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li>
                                <a class="dropdown-item" href="<?= $baseUrl ?>/profile.php">
                                    <i class="bi bi-pencil-square me-2"></i>Hồ sơ cá nhân
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <!-- Đăng xuất — dùng thẻ a trỏ tới logout.php để xóa session -->
                                <a class="dropdown-item text-danger"
                                   href="<?= $baseUrl ?>/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>

            </ul>
        </div><!-- /.collapse -->
    </div><!-- /.container -->
</nav>
<!-- ========== /NAVBAR ========== -->