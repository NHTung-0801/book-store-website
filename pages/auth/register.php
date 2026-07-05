<?php
// register.php

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

// Nếu đã đăng nhập rồi thì không cho vào trang đăng ký nữa
if ($isLoggedIn) {
    header('Location: /bookstore/index.php');
    exit;
}

$errors  = [];   // Mảng chứa lỗi validate
$success = false; // Đổi thành boolean để dễ xử lý SweetAlert
$old     = [];   // Giữ lại dữ liệu cũ khi form bị lỗi (tránh người dùng phải nhập lại)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. LẤY VÀ LÀM SẠCH DỮ LIỆU ĐẦU VÀO ──────────────────────────────
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    // Lưu lại để điền vào form khi có lỗi (trừ password)
    $old = compact('username', 'fullname', 'email');

    // ── 2. VALIDATE DỮ LIỆU ───────────────────────────────────────────────

    // Username: bắt buộc, 4–30 ký tự, chỉ chứa chữ/số/gạch dưới
    if (empty($username)) {
        $errors['username'] = 'Tên đăng nhập không được để trống.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) {
        $errors['username'] = 'Tên đăng nhập 4–30 ký tự, chỉ gồm chữ, số và dấu gạch dưới.';
    }

    // Password: bắt buộc, tối thiểu 6 ký tự
    if (empty($password)) {
        $errors['password'] = 'Mật khẩu không được để trống.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }

    // Xác nhận mật khẩu phải khớp
    if (empty($confirm)) {
        $errors['confirm'] = 'Vui lòng xác nhận mật khẩu.';
    } elseif ($password !== $confirm) {
        $errors['confirm'] = 'Xác nhận mật khẩu không khớp.';
    }

    // Họ tên: bắt buộc, tối đa 100 ký tự
    if (empty($fullname)) {
        $errors['fullname'] = 'Họ và tên không được để trống.';
    } elseif (strlen($fullname) > 100) {
        $errors['fullname'] = 'Họ và tên không được vượt quá 100 ký tự.';
    }

    // Email: bắt buộc, đúng định dạng
    if (empty($email)) {
        $errors['email'] = 'Email không được để trống.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Địa chỉ email không hợp lệ.';
    }

    // ── 3. KIỂM TRA TRÙNG USERNAME / EMAIL TRONG DATABASE ─────────────────
    if (empty($errors)) {

        // Kiểm tra username đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Tên đăng nhập này đã được sử dụng.';
        }

        // Kiểm tra email đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Địa chỉ email này đã được đăng ký.';
        }
    }

    // ── 4. INSERT VÀO DATABASE NẾU KHÔNG CÓ LỖI ──────────────────────────
    if (empty($errors)) {

        // Mã hóa mật khẩu bằng bcrypt (PASSWORD_DEFAULT)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, fullname, email, role)
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$username, $hashedPassword, $fullname, $email]);

        // Đăng ký thành công — đổi trạng thái cờ success
        $success = true;
        $old = [];
    }
}
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Syne:wght@700;800&display=swap');

    body { padding-top: 0 !important; }
    .smart-dock { display: none !important; }

    .immersive-auth-layout { min-height: 100vh; display: flex; flex-wrap: wrap; width: 100%; background-color: #FDFCF7; }
    
    @keyframes kenBurns { 0% { transform: scale(1); } 100% { transform: scale(1.08); } }
    
    .immersive-auth-image { flex: 1 1 100%; position: relative; display: flex; align-items: center; justify-content: center; padding: 2rem; min-height: 40vh; overflow: hidden; }
    .immersive-auth-bg { position: absolute; inset: -2rem; background: url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?q=80&w=2070&auto=format&fit=crop') center/cover no-repeat; animation: kenBurns 20s ease-in-out infinite alternate; z-index: 0; }
    .immersive-auth-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.8)); z-index: 1; }
    .immersive-auth-light-leak { position: absolute; inset: 0; background: linear-gradient(to top right, rgba(245,158,11,0.2), transparent, rgba(249,115,22,0.1)); mix-blend-mode: overlay; z-index: 2; }
    
    .immersive-auth-content { position: relative; z-index: 10; text-align: center; background-color: rgba(0,0,0,0.2); backdrop-filter: blur(8px); padding: 2.5rem 3rem; border-radius: 1.5rem; border: 1px solid rgba(255,255,255,0.1); transition: all 0.5s ease; max-width: 28rem; }
    
    .immersive-auth-image:hover .immersive-auth-content h1 { color: #fcd34d !important; text-shadow: 0 10px 40px rgba(252,211,77,0.4) !important; }
    .immersive-auth-image:hover .immersive-auth-content { border-color: rgba(252,211,77,0.3) !important; }
    
    .auth-input-group { margin-bottom: 1rem; position: relative; width: 100%; }
    .auth-input-group i.auth-icon { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.125rem; color: #9ca3af; transition: color 0.3s ease; pointer-events: none; }
    .auth-input-group input { width: 100%; background-color: #F8F6F0; border: 1px solid rgba(0,0,0,0.1); border-radius: 1rem; padding: 0.75rem 3rem 0.75rem 3.5rem; font-size: 0.875rem; color: #111111; transition: all 0.3s ease; outline: none; height: 3.25rem; }
    .auth-input-group input:focus { background-color: #ffffff; border-color: #111111; box-shadow: 0 0 0 2px rgba(0,0,0,0.05); }
    .auth-input-group:focus-within i.auth-icon { color: #111111; }
    .auth-error-text { color: #dc3545; font-size: 0.8rem; margin-top: 0.25rem; margin-left: 1rem; font-weight: 500; }
    
    .immersive-auth-form-wrap { flex: 1 1 100%; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; min-height: 60vh; overflow-y: auto; background-color: #FDFCF7; }
    .immersive-auth-form-inner { width: 100%; max-width: 28rem; margin: 0 auto; display: flex; flex-direction: column; justify-content: center; }
    
    .home-return-btn { position: absolute; top: 2rem; left: 2rem; z-index: 20; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #4b5563; padding: 0.5rem 1rem; border-radius: 9999px; transition: all 0.3s ease; text-decoration: none; }
    .home-return-btn:hover { color: #111111; background-color: rgba(0,0,0,0.05); }
    .home-return-btn i { transition: transform 0.3s ease; }
    .home-return-btn:hover i { transform: translateX(-4px); }
    
    .auth-title-gradient { font-size: 1.875rem; font-weight: 800; letter-spacing: -0.025em; text-align: center; margin-bottom: 0.25rem; background: linear-gradient(to right, #111111, #555555, #111111); -webkit-background-clip: text; color: transparent; background-clip: text; }
    
    .password-grid { display: grid; grid-template-columns: 1fr; gap: 0.75rem; width: 100%; }
    @media (min-width: 640px) { .password-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; } }
    
    .btn-shimmer-auth { width: 100%; position: relative; overflow: hidden; background-color: #111111; color: #ffffff; font-weight: 600; padding: 1.25rem; border-radius: 9999px; font-size: 0.95rem; letter-spacing: 0.05em; text-transform: uppercase; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s ease; margin-top: 2rem; }
    .btn-shimmer-auth:hover { background-color: #000000; box-shadow: 0 15px 30px rgba(0,0,0,0.2); }
    .btn-shimmer-auth:active { transform: scale(0.98); }
    .btn-shimmer-auth::after { content: ""; position: absolute; top: 0; left: 0; width: 50%; height: 100%; background-color: rgba(255, 255, 255, 0.15); transform: skewX(-25deg) translateX(-150%); transition: transform 1s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-shimmer-auth:hover::after { transform: skewX(-25deg) translateX(300%); }
    
    @media (min-width: 768px) {
        .immersive-auth-layout { flex-wrap: nowrap; height: 100vh; overflow: hidden; }
        .immersive-auth-image, .immersive-auth-form-wrap { flex: 1; min-height: 100vh; }
    }
</style>

<main class="immersive-auth-layout">
    <div class="immersive-auth-image">
        <div class="immersive-auth-bg"></div>
        <div class="immersive-auth-overlay"></div>
        <div class="immersive-auth-light-leak"></div>

        <a href="/bookstore/index.php" style="position: absolute; top: 2rem; left: 2rem; z-index: 20; color: #ffffff; font-weight: 700; font-size: 1.25rem; letter-spacing: 0.1em; text-decoration: none; border-bottom: 1px solid transparent; transition: border-color 0.3s;" onmouseover="this.style.borderColor='rgba(255,255,255,0.5)'" onmouseout="this.style.borderColor='transparent'">
            NOVELTY
        </a>

        <div class="immersive-auth-content">
            <h1 style="font-family: 'Syne', sans-serif; font-size: clamp(2rem, 4vw, 3.5rem); font-weight: 800; color: #ffffff; line-height: 1.2; letter-spacing: -0.04em; text-shadow: 0 10px 30px rgba(0,0,0,0.5); transition: all 0.8s ease;">
                Bắt đầu chương mới<br>trong hành trình<br>tri thức của bạn.
            </h1>
        </div>
    </div>

    <div class="immersive-auth-form-wrap">
        <a href="/bookstore/index.php" class="home-return-btn">
            <i class="bi bi-arrow-left"></i> Trang chủ
        </a>
        <a href="/bookstore/pages/auth/login.php" style="position: absolute; top: 2rem; right: 2rem; z-index: 20; font-size: 0.875rem; color: #111111; font-weight: 600; text-decoration: none; padding: 0.5rem 1rem; border-radius: 9999px; background-color: rgba(0,0,0,0.05); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(0,0,0,0.1)'" onmouseout="this.style.backgroundColor='rgba(0,0,0,0.05)'">
            Đăng nhập <i class="bi bi-arrow-right ms-1"></i>
        </a>

        <div class="immersive-auth-form-inner" style="padding-top: 4rem; padding-bottom: 2rem;">
            
            <div style="text-align: center; width: 100%; margin: 0 auto;">
                <h2 class="auth-title-gradient">TẠO TÀI KHOẢN</h2>
                <p style="font-size: 0.9rem; color: #6b7280; margin-bottom: 2rem; font-weight: 400; line-height: 1.5;">
                    Đăng ký để nhận ưu đãi và lưu lại danh sách yêu thích.
                </p>
            </div>

            <?php if ($success): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Tuyệt vời!',
                            text: 'Tài khoản của bạn đã được tạo thành công.',
                            confirmButtonColor: '#111111',
                            confirmButtonText: 'Đăng nhập ngay'
                        }).then((result) => {
                            window.location.href = '/bookstore/pages/auth/login.php';
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="" novalidate>
                <!-- Fullname -->
                <div class="auth-input-group">
                    <i class="bi bi-card-text auth-icon"></i>
                    <input
                        type="text"
                        id="fullname"
                        name="fullname"
                        style="<?= isset($errors['fullname']) ? 'border-color: #dc3545;' : '' ?>"
                        placeholder="Họ và tên"
                        value="<?= htmlspecialchars($old['fullname'] ?? '') ?>"
                        autocomplete="name"
                        autofocus
                    >
                    <?php if (isset($errors['fullname'])): ?>
                        <div class="auth-error-text">
                            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['fullname']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Username -->
                <div class="auth-input-group">
                    <i class="bi bi-person auth-icon"></i>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        style="<?= isset($errors['username']) ? 'border-color: #dc3545;' : '' ?>"
                        placeholder="Tên đăng nhập"
                        value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                        autocomplete="username"
                    >
                    <?php if (isset($errors['username'])): ?>
                        <div class="auth-error-text">
                            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['username']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Email -->
                <div class="auth-input-group">
                    <i class="bi bi-envelope auth-icon"></i>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        style="<?= isset($errors['email']) ? 'border-color: #dc3545;' : '' ?>"
                        placeholder="Địa chỉ email"
                        value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                        autocomplete="email"
                    >
                    <?php if (isset($errors['email'])): ?>
                        <div class="auth-error-text">
                            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['email']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="password-grid">
                    <!-- Password -->
                    <div class="auth-input-group" style="margin-bottom: 0;">
                        <i class="bi bi-lock auth-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            style="<?= isset($errors['password']) ? 'border-color: #dc3545;' : '' ?> padding-right: 2.5rem;"
                            placeholder="Mật khẩu (>6 ký tự)"
                            autocomplete="new-password"
                        >
                        <button type="button" class="toggle-password" data-target="password" style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; padding: 0.5rem; cursor: pointer; z-index: 5;">
                            <i class="bi bi-eye text-secondary hover:text-black"></i>
                        </button>
                        <?php if (isset($errors['password'])): ?>
                            <div class="auth-error-text">
                                <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['password']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Confirm Password -->
                    <div class="auth-input-group" style="margin-bottom: 0;">
                        <i class="bi bi-shield-check auth-icon"></i>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            style="<?= isset($errors['confirm']) ? 'border-color: #dc3545;' : '' ?> padding-right: 2.5rem;"
                            placeholder="Xác nhận mật khẩu"
                            autocomplete="new-password"
                        >
                        <button type="button" class="toggle-password" data-target="confirm_password" style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; padding: 0.5rem; cursor: pointer; z-index: 5;">
                            <i class="bi bi-eye text-secondary hover:text-black"></i>
                        </button>
                        <?php if (isset($errors['confirm'])): ?>
                            <div class="auth-error-text">
                                <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['confirm']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn-shimmer-auth">
                    TẠO TÀI KHOẢN
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>