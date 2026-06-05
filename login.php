<?php
// login.php

// 1. Khởi tạo session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Nếu đã đăng nhập, điều hướng ngay lập tức
if (isset($_SESSION['user_id'])) {
    header('Location: /bookstore/index.php');
    exit;
}

// 3. Kết nối Database
require_once __DIR__ . '/config/db.php';

$error = '';  // Thông báo lỗi đăng nhập
$old   = [];  // Giữ lại username khi form bị lỗi

// 4. XỬ LÝ LOGIC ĐĂNG NHẬP (Phải nằm trước bất kỳ mã HTML nào)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $old      = ['username' => $username];

    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, fullname, role FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        } else {
            // Đăng nhập thành công
            session_regenerate_id(true);

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['username'] = $user['username'];

            // Điều hướng theo phân quyền
            if ($user['role'] == 1) {
                header('Location: /bookstore/admin/index.php');
            } else {
                header('Location: /bookstore/index.php');
            }
            exit; // Kết thúc script ngay sau khi điều hướng
        }
    }
}

// =========================================================================
// 5. SAU KHI XỬ LÝ XONG LOGIC NGẦM, MỚI BẮT ĐẦU HIỂN THỊ GIAO DIỆN
// =========================================================================
require_once __DIR__ . '/includes/header.php';
?>

<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">

            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">

                    <div class="text-center mb-4">
                        <i class="bi bi-box-arrow-in-right fs-1 text-warning"></i>
                        <h3 class="fw-bold mt-2">Đăng nhập</h3>
                        <p class="text-muted small">Chào mừng bạn quay trở lại!</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-shield-exclamation me-2 flex-shrink-0"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">Tên đăng nhập <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person text-muted"></i></span>
                                <input type="text" id="username" name="username" class="form-control <?= $error ? 'is-invalid' : '' ?>" placeholder="Nhập tên đăng nhập" value="<?= htmlspecialchars($old['username'] ?? '') ?>" autocomplete="username" autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold mb-1">Mật khẩu <span class="text-danger">*</span></label>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-light"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" id="password" name="password" class="form-control <?= $error ? 'is-invalid' : '' ?>" placeholder="Nhập mật khẩu" autocomplete="current-password">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="password" title="Hiện/ẩn mật khẩu"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning fw-bold py-2">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">
                    <p class="text-center text-muted small mb-0">
                        Chưa có tài khoản? <a href="/bookstore/register.php" class="text-warning fw-semibold text-decoration-none">Đăng ký ngay</a>
                    </p>

                </div>
            </div>

        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>