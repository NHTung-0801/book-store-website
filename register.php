<?php
// register.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

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

<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">

            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">

                    <div class="text-center mb-4">
                        <i class="bi bi-person-plus-fill fs-1 text-warning"></i>
                        <h3 class="fw-bold mt-2">Tạo tài khoản</h3>
                        <p class="text-muted small">Tham gia Book Store ngay hôm nay</p>
                    </div>

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
                                    // Tự động chuyển hướng về trang đăng nhập sau khi đóng popup
                                    window.location.href = '/bookstore/login.php';
                                });
                            });
                        </script>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                    <form method="POST" action="" novalidate>

                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">
                                Tên đăng nhập <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                                placeholder="Ví dụ: nguyenvana"
                                value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                                autocomplete="username"
                            >
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback">
                                    <i class="bi bi-exclamation-circle me-1"></i>
                                    <?= htmlspecialchars($errors['username']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="fullname" class="form-label fw-semibold">
                                Họ và tên <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                id="fullname"
                                name="fullname"
                                class="form-control <?= isset($errors['fullname']) ? 'is-invalid' : '' ?>"
                                placeholder="Ví dụ: Nguyễn Văn A"
                                value="<?= htmlspecialchars($old['fullname'] ?? '') ?>"
                                autocomplete="name"
                            >
                            <?php if (isset($errors['fullname'])): ?>
                                <div class="invalid-feedback">
                                    <i class="bi bi-exclamation-circle me-1"></i>
                                    <?= htmlspecialchars($errors['fullname']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                placeholder="Ví dụ: example@email.com"
                                value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                                autocomplete="email"
                            >
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback">
                                    <i class="bi bi-exclamation-circle me-1"></i>
                                    <?= htmlspecialchars($errors['email']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">
                                Mật khẩu <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                    placeholder="Tối thiểu 6 ký tự"
                                    autocomplete="new-password"
                                >
                                <button class="btn btn-outline-secondary toggle-password"
                                        type="button" data-target="password"
                                        title="Hiện/ẩn mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        <?= htmlspecialchars($errors['password']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">
                                Xác nhận mật khẩu <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    class="form-control <?= isset($errors['confirm']) ? 'is-invalid' : '' ?>"
                                    placeholder="Nhập lại mật khẩu"
                                    autocomplete="new-password"
                                >
                                <button class="btn btn-outline-secondary toggle-password"
                                        type="button" data-target="confirm_password"
                                        title="Hiện/ẩn mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (isset($errors['confirm'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        <?= htmlspecialchars($errors['confirm']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning fw-bold py-2">
                                <i class="bi bi-person-check me-2"></i>Tạo tài khoản
                            </button>
                        </div>

                    </form>
                    <?php endif; ?>

                    <hr class="my-4">
                    <p class="text-center text-muted small mb-0">
                        Đã có tài khoản?
                        <a href="/bookstore/login.php" class="text-warning fw-semibold text-decoration-none">
                            Đăng nhập ngay
                        </a>
                    </p>

                </div></div></div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>