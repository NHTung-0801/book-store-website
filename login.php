<?php
// login.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// Nếu đã đăng nhập rồi thì redirect luôn, không cho vào trang login
if ($isLoggedIn) {
    header('Location: /bookstore/index.php');
    exit;
}

$error = '';  // Thông báo lỗi đăng nhập
$old   = [];  // Giữ lại username khi form bị lỗi

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. LẤY VÀ LÀM SẠCH DỮ LIỆU ──────────────────────────────────────
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $old      = ['username' => $username];

    // ── 2. VALIDATE SƠ BỘ (tránh query DB khi input rõ ràng sai) ─────────
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {

        // ── 3. TRUY VẤN USER THEO USERNAME ──────────────────────────────
        // Dùng prepared statement — an toàn tuyệt đối với SQL Injection
        $stmt = $pdo->prepare("
            SELECT id, username, password, fullname, role
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(); // Trả về mảng kết hợp hoặc false

        // ── 4. XÁC THỰC MẬT KHẨU BẰNG password_verify() ────────────────
        // Không tiết lộ lý do cụ thể (sai username hay sai password)
        // để tránh kẻ tấn công dò tài khoản tồn tại
        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        } else {

            // ── 5. ĐĂNG NHẬP THÀNH CÔNG — GHI SESSION ───────────────────
            // Tái tạo session ID mới để chống Session Fixation Attack
            session_regenerate_id(true);

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['username'] = $user['username'];

            // ── 6. ĐIỀU HƯỚNG THEO PHÂN QUYỀN ───────────────────────────
            if ($user['role'] == 1) {
                // Admin → trang quản trị
                header('Location: /bookstore/admin/index.php');
            } else {
                // User thường → trang chủ
                header('Location: /bookstore/index.php');
            }
            exit;
        }
    }
}
?>

<!-- ========== NỘI DUNG TRANG ĐĂNG NHẬP ========== -->
<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">

            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">

                    <!-- Tiêu đề -->
                    <div class="text-center mb-4">
                        <i class="bi bi-box-arrow-in-right fs-1 text-warning"></i>
                        <h3 class="fw-bold mt-2">Đăng nhập</h3>
                        <p class="text-muted small">Chào mừng bạn quay trở lại!</p>
                    </div>

                    <!-- Thông báo lỗi đăng nhập -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-shield-exclamation me-2 flex-shrink-0"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Form đăng nhập -->
                    <form method="POST" action="" novalidate>

                        <!-- Username -->
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">
                                Tên đăng nhập <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-person text-muted"></i>
                                </span>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    class="form-control <?= $error ? 'is-invalid' : '' ?>"
                                    placeholder="Nhập tên đăng nhập"
                                    value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                                    autocomplete="username"
                                    autofocus
                                >
                            </div>
                        </div>

                        <!-- Mật khẩu -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="password" class="form-label fw-semibold mb-0">
                                    Mật khẩu <span class="text-danger">*</span>
                                </label>
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-lock text-muted"></i>
                                </span>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control <?= $error ? 'is-invalid' : '' ?>"
                                    placeholder="Nhập mật khẩu"
                                    autocomplete="current-password"
                                >
                                <!-- Nút toggle hiện/ẩn mật khẩu (xử lý bởi main.js) -->
                                <button class="btn btn-outline-secondary toggle-password"
                                        type="button" data-target="password"
                                        title="Hiện/ẩn mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Nút submit -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning fw-bold py-2">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                            </button>
                        </div>

                    </form>

                    <!-- Link chuyển sang đăng ký -->
                    <hr class="my-4">
                    <p class="text-center text-muted small mb-0">
                        Chưa có tài khoản?
                        <a href="/bookstore/register.php"
                           class="text-warning fw-semibold text-decoration-none">
                            Đăng ký ngay
                        </a>
                    </p>

                </div><!-- /.card-body -->
            </div><!-- /.card -->

        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>