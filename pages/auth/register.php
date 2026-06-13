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

<main class="container auth-wrapper my-5">
    <div class="card auth-card">
        <!-- Đã gỡ bỏ class flex-md-row-reverse để sidebar nằm bên trái giống trang đăng nhập -->
        <div class="row g-0 h-100">
            
            <div class="col-md-5 auth-sidebar d-none d-md-flex flex-column justify-content-center align-items-center text-center">
                <div class="auth-sidebar-content">
                    <img src="https://img.icons8.com/3d-fluency/180/reading.png" alt="Join us" class="mb-4" style="filter: drop-shadow(0 10px 15px rgba(0,0,0,0.3));">
                    <h2 class="fw-bold text-white mb-3">Tham gia cùng chúng tôi!</h2>
                    <p class="text-white-50 px-4" style="font-size: 0.95rem; line-height: 1.6;">
                        Tạo tài khoản ngay hôm nay để nhận nhiều ưu đãi, lưu lại danh sách yêu thích và theo dõi đơn hàng của bạn dễ dàng hơn.
                    </p>
                </div>
            </div>

            <div class="col-md-7 bg-white">
                <div class="auth-form-wrap">
                    
                    <div class="text-center d-md-none mb-4">
                        <img src="https://img.icons8.com/3d-fluency/80/reading.png" alt="Join us" class="mb-2">
                        <h3 class="fw-bolder" style="color: #0f3460;">Tạo tài khoản</h3>
                    </div>

                    <h3 class="fw-bolder d-none d-md-block mb-1" style="color: #0f3460;">Tạo tài khoản</h3>
                    <p class="text-muted small mb-4 d-none d-md-block">Vui lòng điền đầy đủ thông tin bên dưới để đăng ký.</p>

                    <?php if ($success): ?>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Tuyệt vời!',
                                    text: 'Tài khoản của bạn đã được tạo thành công.',
                                    confirmButtonColor: '#ffc107',
                                    confirmButtonText: 'Đăng nhập ngay'
                                }).then((result) => {
                                    window.location.href = '/bookstore/pages/auth/login.php';
                                });
                            });
                        </script>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                    <form method="POST" action="" novalidate>

                        <div class="mb-3">
                            <label for="fullname" class="form-label fw-bold text-secondary small text-uppercase">
                                Họ và tên <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-lg auth-input-group shadow-sm">
                                <span class="input-group-text"><i class="bi bi-card-text text-muted"></i></span>
                                <input
                                    type="text"
                                    id="fullname"
                                    name="fullname"
                                    class="form-control fs-6 <?= isset($errors['fullname']) ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                    placeholder="Ví dụ: Nguyễn Văn A"
                                    value="<?= htmlspecialchars($old['fullname'] ?? '') ?>"
                                    autocomplete="name"
                                    autofocus
                                >
                            </div>
                            <?php if (isset($errors['fullname'])): ?>
                                <div class="text-danger small mt-1 fw-medium">
                                    <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['fullname']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold text-secondary small text-uppercase">
                                Tên đăng nhập <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-lg auth-input-group shadow-sm">
                                <span class="input-group-text"><i class="bi bi-person text-muted"></i></span>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    class="form-control fs-6 <?= isset($errors['username']) ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                    placeholder="Ví dụ: nguyenvana"
                                    value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                                    autocomplete="username"
                                >
                            </div>
                            <?php if (isset($errors['username'])): ?>
                                <div class="text-danger small mt-1 fw-medium">
                                    <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['username']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold text-secondary small text-uppercase">
                                Email <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-lg auth-input-group shadow-sm">
                                <span class="input-group-text"><i class="bi bi-envelope text-muted"></i></span>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control fs-6 <?= isset($errors['email']) ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                    placeholder="Ví dụ: example@email.com"
                                    value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                                    autocomplete="email"
                                >
                            </div>
                            <?php if (isset($errors['email'])): ?>
                                <div class="text-danger small mt-1 fw-medium">
                                    <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['email']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label fw-bold text-secondary small text-uppercase">
                                    Mật khẩu <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-lg auth-input-group shadow-sm">
                                    <span class="input-group-text"><i class="bi bi-lock text-muted"></i></span>
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control fs-6 <?= isset($errors['password']) ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                        placeholder="Tối thiểu 6 ký tự"
                                        autocomplete="new-password"
                                    >
                                    <button class="btn border border-secondary-subtle border-start-0 toggle-password"
                                            type="button" data-target="password"
                                            title="Hiện/ẩn mật khẩu">
                                        <i class="bi bi-eye text-secondary"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="text-danger small mt-1 fw-medium">
                                        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['password']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label fw-bold text-secondary small text-uppercase">
                                    Xác nhận <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-lg auth-input-group shadow-sm">
                                    <span class="input-group-text"><i class="bi bi-shield-check text-muted"></i></span>
                                    <input
                                        type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        class="form-control fs-6 <?= isset($errors['confirm']) ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                        placeholder="Nhập lại mật khẩu"
                                        autocomplete="new-password"
                                    >
                                    <button class="btn border border-secondary-subtle border-start-0 toggle-password"
                                            type="button" data-target="confirm_password"
                                            title="Hiện/ẩn mật khẩu">
                                        <i class="bi bi-eye text-secondary"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['confirm'])): ?>
                                    <div class="text-danger small mt-1 fw-medium">
                                        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['confirm']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-warning btn-auth text-dark fs-5 fw-bold">
                                <i class="bi bi-person-check me-2"></i>TẠO TÀI KHOẢN
                            </button>
                        </div>

                    </form>
                    <?php endif; ?>

                    <div class="text-center mt-4 pt-2">
                        <p class="text-muted small mb-0">
                            Đã có tài khoản? 
                            <a href="/bookstore/pages/auth/login.php" class="text-dark fw-bold text-decoration-none border-bottom border-warning border-2 pb-1 ms-1 transition-hover">
                                Đăng nhập ngay
                            </a>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>