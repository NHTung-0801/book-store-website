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

// Rút gọn tên hiển thị để tránh vỡ layout (rớt dòng) trên menu
if ($isAdmin && $fullname === 'Quản trị viên') {
    $fullname = 'Admin';
}

// Xác định đường dẫn gốc để dùng cho href (tránh lỗi đường dẫn tương đối)
$baseUrl = '/bookstore';

require_once __DIR__ . '/../config/db.php';

$cartCount = 0;
if ($isLoggedIn) {
    try {
        $stmtCartCount = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
        $stmtCartCount->execute([$_SESSION['user_id']]);
        $cartCount = (int) $stmtCartCount->fetchColumn();
    } catch (Exception $e) {
        $cartCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'NOVELTY - Thế giới Sách & Tri thức' ?></title>

    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <!-- Google Fonts: Syne & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Syne:wght@400..800&display=swap" rel="stylesheet">

    <!-- CSS tùy chỉnh riêng của dự án -->
    <link href="<?= $baseUrl ?>/assets/css/style.css?v=<?= time() ?>" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<!-- ========== SMART MORPHING DOCK ========== -->
<nav class="smart-dock">
    <div class="dock-container">
        
        <!-- Logo -->
        <a href="<?= $baseUrl ?>/index.php" class="dock-logo">
            <span class="logo-text">NOVELTY</span>
        </a>

        <!-- Links -->
        <div class="dock-links-wrapper d-none d-lg-flex">
            <div class="dock-indicator"></div>
            <a class="dock-link" href="<?= $baseUrl ?>/index.php">Trang chủ</a>
            <?php if ($isLoggedIn): ?>
                <a class="dock-link" href="<?= $baseUrl ?>/pages/user/my_orders.php">Đơn hàng</a>
                <?php if ($isAdmin): ?>
                    <a class="dock-link admin-link text-danger" href="<?= $baseUrl ?>/admin/index.php">Quản trị</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Auth & Cart Actions -->
        <div class="dock-actions">
            <?php if (!$isLoggedIn): ?>
                <a class="dock-action-link d-none d-sm-block" href="<?= $baseUrl ?>/pages/auth/login.php">Đăng nhập</a>
                <a class="dock-btn" href="<?= $baseUrl ?>/pages/auth/register.php">Đăng ký</a>
            <?php else: ?>
                <div class="dropdown">
                    <button class="dock-action-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-6 me-1"></i> <?= $fullname ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 12px; padding: 0.5rem;">
                        <li><a class="dropdown-item rounded" href="<?= $baseUrl ?>/pages/user/profile.php">Hồ sơ cá nhân</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded text-danger logout-link" href="<?= $baseUrl ?>/pages/auth/logout.php">Đăng xuất</a></li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($isLoggedIn): ?>
                <a href="<?= $baseUrl ?>/pages/shop/cart.php" class="dock-cart relative inline-flex items-center justify-center">
                    <i class="bi bi-bag"></i>
                    <?php if ($cartCount > 0): ?>
                        <span class="absolute top-0 right-0 -mt-1 -mr-1 bg-[#FF4500] text-white text-[10px] font-bold px-[5px] py-[1px] rounded-full border-2 border-white min-w-[20px] flex items-center justify-center shadow-sm z-10">
                            <?= $cartCount ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            
            <!-- Mobile Menu Toggle -->
            <button class="dock-cart d-lg-none border-0 bg-transparent ms-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
                <i class="bi bi-list"></i>
            </button>
        </div>

    </div>
</nav>

<!-- Mobile Menu Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileMenu" style="background-color: var(--bg-paper);">
  <div class="offcanvas-header border-bottom border-dark border-opacity-10">
    <h5 class="offcanvas-title fw-bold" style="font-family: var(--font-heading);">NOVELTY MENU</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column gap-3 mt-3">
    <a href="<?= $baseUrl ?>/index.php" class="text-dark fs-4 text-decoration-none fw-bold" style="font-family: var(--font-heading);">Trang chủ</a>
    <?php if ($isLoggedIn): ?>
        <a href="<?= $baseUrl ?>/pages/user/my_orders.php" class="text-dark fs-4 text-decoration-none fw-bold" style="font-family: var(--font-heading);">Đơn hàng</a>
        <?php if ($isAdmin): ?>
            <a href="<?= $baseUrl ?>/admin/index.php" class="text-danger fs-4 text-decoration-none fw-bold" style="font-family: var(--font-heading);">Quản trị</a>
        <?php endif; ?>
    <?php else: ?>
        <a href="<?= $baseUrl ?>/pages/auth/login.php" class="text-dark fs-4 text-decoration-none fw-bold" style="font-family: var(--font-heading);">Đăng nhập</a>
    <?php endif; ?>
  </div>
</div>
<!-- ========== /SMART MORPHING DOCK ========== -->

<script>
// Logic for Smart Morphing Dock Slider
document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.querySelector('.dock-links-wrapper');
    const indicator = document.querySelector('.dock-indicator');
    const links = document.querySelectorAll('.dock-link');

    if(wrapper && indicator && links.length > 0) {
        links.forEach(link => {
            link.addEventListener('mouseenter', (e) => {
                const rect = e.target.getBoundingClientRect();
                const wrapperRect = wrapper.getBoundingClientRect();
                
                indicator.style.opacity = '1';
                indicator.style.width = `${rect.width}px`;
                indicator.style.left = `${rect.left - wrapperRect.left}px`;
                
                links.forEach(l => l.classList.remove('active-hover'));
                e.target.classList.add('active-hover');
            });
        });

        wrapper.addEventListener('mouseleave', () => {
            indicator.style.opacity = '0';
            links.forEach(l => l.classList.remove('active-hover'));
        });
    }
});
</script>