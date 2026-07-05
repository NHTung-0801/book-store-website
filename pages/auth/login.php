<?php
// pages/auth/login.php

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

// Nếu đã đăng nhập rồi thì redirect luôn, không cho vào trang login
if ($isLoggedIn) {
    header('Location: /bookstore/index.php');
    exit;
}

$error = '';  // Thông báo lỗi đăng nhập
$old   = [];  // Giữ lại username khi form bị lỗi

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $old      = ['username' => $username];

    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id, username, password, fullname, role
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['username'] = $user['username'];

            $_SESSION['sweet_alert'] = [
                'icon'  => 'success',
                'title' => 'Thành công!',
                'text'  => 'Chào mừng bạn quay trở lại.'
            ];

            if ($user['role'] == 1) {
                header('Location: /bookstore/admin/index.php');
            } else {
                header('Location: /bookstore/index.php');
            }
            exit;
        }
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Syne:wght@700;800&display=swap');

    /* Khóa Navbar chung & Override body padding */
    body {
        padding-top: 0 !important;
    }
    .smart-dock {
        display: none !important;
    }

    /* Full screen wrapper */
    .immersive-auth-layout {
        min-height: 100vh;
        display: flex;
        flex-wrap: wrap;
        width: 100%;
        background-color: #FDFCF7;
    }
    @keyframes kenBurns {
        0% { transform: scale(1); }
        100% { transform: scale(1.08); }
    }
    
    .immersive-auth-image {
        flex: 1 1 100%;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        min-height: 40vh;
        overflow: hidden;
    }
    .immersive-auth-bg {
        position: absolute;
        inset: -2rem;
        background: url('https://images.unsplash.com/photo-1457369804613-52c61a468e7d?q=80&w=2070&auto=format&fit=crop') center/cover no-repeat;
        animation: kenBurns 20s ease-in-out infinite alternate;
        z-index: 0;
    }
    .immersive-auth-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.8));
        z-index: 1;
    }
    .immersive-auth-light-leak {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top right, rgba(245,158,11,0.2), transparent, rgba(249,115,22,0.1));
        mix-blend-mode: overlay;
        z-index: 2;
    }
    .immersive-auth-content {
        position: relative;
        z-index: 10;
        text-align: center;
        background-color: rgba(0,0,0,0.2);
        backdrop-filter: blur(8px);
        padding: 2.5rem 3rem;
        border-radius: 1.5rem;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.5s ease;
    }
    
    .immersive-auth-image:hover .immersive-auth-content h1 {
        color: #fcd34d !important;
        text-shadow: 0 10px 40px rgba(252,211,77,0.4) !important;
    }
    .immersive-auth-image:hover .immersive-auth-content {
        border-color: rgba(252,211,77,0.3) !important;
    }
    
    /* Form Micro-interactions */
    .auth-input-group {
        margin-bottom: 1.5rem;
        position: relative;
    }
    .auth-input-group i.auth-icon {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.125rem;
        color: #9ca3af;
        transition: color 0.3s ease;
        pointer-events: none;
    }
    .auth-input-group input {
        width: 100%;
        background-color: #F8F6F0;
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: 1rem;
        padding: 1.125rem 3rem 1.125rem 3rem; /* padding right for eye icon if present */
        font-size: 0.95rem;
        color: #111111;
        transition: all 0.3s ease;
        outline: none;
    }
    .auth-input-group input:focus {
        background-color: #ffffff;
        border-color: #111111;
        box-shadow: 0 0 0 4px rgba(0,0,0,0.05);
    }
    .auth-input-group:focus-within i.auth-icon {
        color: #111111;
    }
    
    .home-return-btn {
        position: absolute;
        top: 2rem;
        left: 2rem;
        z-index: 20;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #4b5563; /* text-gray-600 */
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    .home-return-btn:hover {
        color: #111111;
        background-color: rgba(0,0,0,0.05);
    }
    .home-return-btn i {
        transition: transform 0.3s ease;
    }
    .home-return-btn:hover i {
        transform: translateX(-4px);
    }
    
    .auth-title {
        font-size: 2.25rem;
        font-weight: 800;
        letter-spacing: -0.025em;
        color: #111111;
        margin-bottom: 0.5rem;
        text-align: center;
        position: relative;
    }
    .auth-title::after {
        content: '';
        display: block;
        width: 4rem; /* 16 * 0.25rem */
        height: 4px;
        background: linear-gradient(to right, #f59e0b, #f97316); /* from-amber-500 to-orange-500 */
        margin: 0.75rem auto 0;
        border-radius: 9999px;
    }
    
    .immersive-auth-form-wrap {
        flex: 1 1 100%;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        min-height: 60vh;
    }
    
    @media (min-width: 768px) {
        .immersive-auth-layout {
            flex-wrap: nowrap;
            height: 100vh;
            overflow: hidden; /* Hide scrollbars on desktop */
        }
        .immersive-auth-image,
        .immersive-auth-form-wrap {
            flex: 1;
            min-height: 100vh;
        }
    }
</style>

<main class="immersive-auth-layout">
    <!-- LEFT SIDE: IMAGE & TYPOGRAPHY -->
    <div class="immersive-auth-image">
        <div class="immersive-auth-bg"></div>
        <div class="immersive-auth-overlay"></div>
        <div class="immersive-auth-light-leak"></div>

        <!-- Minimal Auth Header (Left) -->
        <a href="/bookstore/index.php" style="position: absolute; top: 2rem; left: 2rem; z-index: 20; color: #ffffff; font-weight: 700; font-size: 1.25rem; letter-spacing: 0.1em; text-decoration: none; border-bottom: 1px solid transparent; transition: border-color 0.3s;" onmouseover="this.style.borderColor='rgba(255,255,255,0.5)'" onmouseout="this.style.borderColor='transparent'">
            NOVELTY
        </a>

        <!-- Typography -->
        <div class="immersive-auth-content">
            <h1 style="font-family: 'Syne', sans-serif; font-size: clamp(3rem, 6vw, 5rem); font-weight: 800; color: #ffffff; line-height: 1.1; letter-spacing: -0.04em; text-shadow: 0 10px 30px rgba(0,0,0,0.5); transition: all 0.8s ease;">
                Viết lên<br>hành trình<br>của riêng bạn.
            </h1>
        </div>
    </div>

    <!-- RIGHT SIDE: FORM -->
    <div class="immersive-auth-form-wrap">
        <!-- Minimal Auth Header (Right) -->
        <a href="/bookstore/index.php" class="home-return-btn">
            <i class="bi bi-arrow-left"></i> Trang chủ
        </a>
        <a href="/bookstore/pages/auth/register.php" style="position: absolute; top: 2rem; right: 2rem; z-index: 20; font-size: 0.875rem; color: #111111; font-weight: 600; text-decoration: none; padding: 0.5rem 1rem; border-radius: 9999px; background-color: rgba(0,0,0,0.05); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(0,0,0,0.1)'" onmouseout="this.style.backgroundColor='rgba(0,0,0,0.05)'">
            Đăng ký ngay <i class="bi bi-arrow-right ms-1"></i>
        </a>

        <div style="width: 100%; max-width: 26rem; margin: 0 auto;">
            
            <!-- Typography & Tiêu đề -->
            <div style="text-align: center; max-width: 24rem; margin: 0 auto;">
                <h2 class="auth-title">
                    ĐĂNG NHẬP
                </h2>
                <p style="font-size: 0.95rem; color: #6b7280; margin-bottom: 3rem; font-weight: 400; line-height: 1.6;">
                    Khám phá kho tàng tri thức bất tận và trải nghiệm mua sắm tuyệt vời cùng chúng tôi.
                </p>
            </div>

            <?php if ($error): ?>
                <div style="display: flex; align-items: center; background-color: rgba(220, 53, 69, 0.1); color: #dc3545; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; font-weight: 500;">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <!-- Username -->
                <div class="auth-input-group">
                    <i class="bi bi-person auth-icon"></i>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        style="padding-right: 1rem;" 
                        placeholder="Tên đăng nhập"
                        value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                        autocomplete="username"
                        autofocus
                    >
                </div>

                <!-- Password -->
                <div class="auth-input-group" style="margin-bottom: 2rem;">
                    <i class="bi bi-lock auth-icon"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Mật khẩu"
                        autocomplete="current-password"
                    >
                    <button type="button" class="toggle-password" data-target="password" title="Hiện/ẩn mật khẩu" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; padding: 0.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 5;">
                        <i class="bi bi-eye text-secondary transition-colors duration-300 hover:text-black"></i>
                    </button>
                </div>

                <!-- Forgot Password -->
                <div style="text-align: right; margin-bottom: 2rem;">
                    <a href="/bookstore/pages/auth/forgot_password.php" style="font-size: 0.875rem; color: #6b7280; text-decoration: none; font-weight: 500; transition: color 0.2s;" onmouseover="this.style.color='#111111'" onmouseout="this.style.color='#6b7280'">
                        Quên mật khẩu?
                    </a>
                </div>

                <!-- Submit Button -->
                <style>
                    .btn-shimmer-auth {
                        width: 100%; position: relative; overflow: hidden; background-color: #111111; color: #ffffff; font-weight: 600; padding: 1.25rem; border-radius: 9999px; font-size: 0.95rem; letter-spacing: 0.05em; text-transform: uppercase; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s ease;
                    }
                    .btn-shimmer-auth:hover {
                        background-color: #000000;
                        box-shadow: 0 15px 30px rgba(0,0,0,0.2);
                    }
                    .btn-shimmer-auth:active {
                        transform: scale(0.98);
                    }
                    .btn-shimmer-auth::after {
                        content: "";
                        position: absolute;
                        top: 0; left: 0;
                        width: 50%; height: 100%;
                        background-color: rgba(255, 255, 255, 0.15);
                        transform: skewX(-25deg) translateX(-150%);
                        transition: transform 1s cubic-bezier(0.4, 0, 0.2, 1);
                    }
                    .btn-shimmer-auth:hover::after {
                        transform: skewX(-25deg) translateX(300%);
                    }
                </style>
                <button type="submit" class="btn-shimmer-auth">
                    ĐĂNG NHẬP
                </button>
            </form>

            <div style="text-align: center; margin-top: 3rem; display: none;">
                <!-- Hidden since we moved the link to the top right -->
            </div>

        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>