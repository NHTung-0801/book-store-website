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

<main class="container auth-wrapper my-5">
    <div class="card auth-card">
        <div class="row g-0 h-100">
            
            <div class="col-md-5 auth-sidebar d-none d-md-flex flex-column justify-content-center align-items-center text-center">
                <div class="auth-sidebar-content">
                    <img src="https://img.icons8.com/3d-fluency/180/books.png" alt="Books" class="mb-4" style="filter: drop-shadow(0 10px 15px rgba(0,0,0,0.3));">
                    <h2 class="fw-bold text-white mb-3">Chào mừng trở lại!</h2>
                    <p class="text-white-50 px-4" style="font-size: 0.95rem; line-height: 1.6;">
                        Tiếp tục khám phá kho tàng tri thức bất tận và trải nghiệm mua sắm tuyệt vời cùng Book Store.
                    </p>
                </div>
            </div>

            <div class="col-md-7 bg-white">
                <div class="auth-form-wrap">
                    
                    <div class="text-center d-md-none mb-4">
                        <img src="https://img.icons8.com/3d-fluency/80/books.png" alt="Books" class="mb-2">
                        <h3 class="fw-bolder" style="color: #0f3460;">Đăng nhập</h3>
                    </div>

                    <h3 class="fw-bolder d-none d-md-block mb-1" style="color: #0f3460;">Đăng nhập</h3>
                    <p class="text-muted small mb-4 d-none d-md-block">Vui lòng nhập thông tin tài khoản của bạn để tiếp tục.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center p-3 rounded-3 mb-4 border-0 bg-danger-subtle text-danger shadow-sm" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                            <div class="small fw-medium"><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>

                        <div class="mb-4">
                            <label for="username" class="form-label fw-bold text-secondary small text-uppercase">
                                Tên đăng nhập <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-lg auth-input-group shadow-sm">
                                <span class="input-group-text"><i class="bi bi-person text-muted"></i></span>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    class="form-control fs-6 <?= $error ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                    placeholder="Nhập tên đăng nhập..."
                                    value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                                    autocomplete="username"
                                    autofocus
                                >
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="password" class="form-label fw-bold text-secondary small text-uppercase mb-0">
                                    Mật khẩu <span class="text-danger">*</span>
                                </label>
                                <a href="/bookstore/pages/auth/forgot_password.php" class="text-warning fw-semibold small text-decoration-none transition-hover">
                                    Quên mật khẩu?
                                </a>
                            </div>
                            <div class="input-group input-group-lg auth-input-group shadow-sm">
                                <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control fs-6 <?= $error ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                    placeholder="Nhập mật khẩu..."
                                    autocomplete="current-password"
                                >
                                <button class="btn border border-secondary-subtle border-start-0 toggle-password"
                                        type="button" data-target="password"
                                        title="Hiện/ẩn mật khẩu">
                                    <i class="bi bi-eye text-secondary"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-warning btn-auth text-dark fs-5 fw-bold">
                                <i class="bi bi-box-arrow-in-right me-2"></i>ĐĂNG NHẬP
                            </button>
                        </div>

                    </form>

                    <div class="text-center mt-4 pt-2">
                        <p class="text-muted small mb-0">
                            Bạn chưa có tài khoản? 
                            <a href="/bookstore/pages/auth/register.php" class="text-dark fw-bold text-decoration-none border-bottom border-warning border-2 pb-1 ms-1 transition-hover">
                                Đăng ký ngay
                            </a>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>