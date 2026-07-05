<?php
// reset_password.php

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

// Đã đăng nhập thì không cần reset
if ($isLoggedIn) {
    header('Location: /bookstore/index.php');
    exit;
}

$errors  = [];
$success = false;

// ── 1. LẤY VÀ XÁC MINH TOKEN TỪ URL ─────────────────────────────────────────
$token = trim($_GET['token'] ?? '');

if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    // Token không đúng định dạng → chuyển về forgot
    header('Location: /bookstore/pages/auth/forgot_password.php?msg=invalid');
    exit;
}

// Truy vấn token trong DB — kiểm tra còn hạn và chưa dùng
$stmtToken = $pdo->prepare("
    SELECT pr.id, pr.email, pr.expires_at, u.id AS user_id, u.fullname
    FROM   password_resets pr
    JOIN   users u ON u.email = pr.email
    WHERE  pr.token = ?
      AND  pr.used  = 0
      AND  pr.expires_at > NOW()
    LIMIT  1
");
// Truyền trực tiếp biến $token (chuỗi thô) vào để tìm kiếm cho khớp với DB
$stmtToken->execute([$token]); 
$resetRecord = $stmtToken->fetch();

// Token không hợp lệ hoặc đã hết hạn
if (!$resetRecord) {
    header('Location: /bookstore/pages/auth/forgot_password.php?msg=expired');
    exit;
}

// ── 2. XỬ LÝ POST: ĐẶT MẬT KHẨU MỚI ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newPw     = $_POST['new_password']     ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    // Validate
    if (empty($newPw)) {
        $errors['new_password'] = 'Vui lòng nhập mật khẩu mới.';
    } elseif (strlen($newPw) < 6) {
        $errors['new_password'] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if (empty($confirmPw)) {
        $errors['confirm_password'] = 'Vui lòng xác nhận mật khẩu.';
    } elseif ($newPw !== $confirmPw) {
        $errors['confirm_password'] = 'Xác nhận mật khẩu không khớp.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Cập nhật mật khẩu mới cho user
            $pdo->prepare("
                UPDATE users SET password = ? WHERE id = ?
            ")->execute([
                password_hash($newPw, PASSWORD_DEFAULT),
                $resetRecord['user_id']
            ]);

            // Đánh dấu token đã dùng — không cho dùng lại
            $pdo->prepare("
                UPDATE password_resets SET used = 1 WHERE token = ?
            ")->execute([$token]);

            $pdo->commit();
            $success = true;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['system'] = 'Có lỗi xảy ra. Vui lòng thử lại.';
        }
    }
}
?>

<main class="container auth-wrapper my-5">
    <div class="card auth-card">
        <div class="row g-0 h-100">
            
            <div class="col-md-5 auth-sidebar d-none d-md-flex flex-column justify-content-center align-items-center text-center">
                <div class="auth-sidebar-content">
                    <img src="https://img.icons8.com/3d-fluency/180/shield.png" alt="Bảo mật tài khoản" class="mb-4" style="filter: drop-shadow(0 10px 15px rgba(0,0,0,0.3));">
                    <h2 class="fw-bold text-white mb-3">Bảo mật tài khoản</h2>
                    <p class="text-white-50 px-4" style="font-size: 0.95rem; line-height: 1.6;">
                        Tạo một mật khẩu mới mạnh mẽ để bảo vệ thông tin cá nhân và lịch sử mua sắm của bạn tại NOVELTY.
                    </p>
                </div>
            </div>

            <div class="col-md-7 bg-white">
                <div class="auth-form-wrap">
                    
                    <?php if ($success): ?>
                        <div class="text-center py-4">
                            <div class="mb-4">
                                <div class="rounded-circle bg-success bg-opacity-15 d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                    <i class="bi bi-shield-check text-success" style="font-size: 3.5rem;"></i>
                                </div>
                            </div>
                            <h3 class="fw-bolder mb-3" style="color: #0f3460;">Hoàn tất!</h3>
                            <p class="text-muted mb-4 fs-6 px-md-4">
                                Tài khoản <strong><?= htmlspecialchars($resetRecord['fullname']) ?></strong> đã được cập nhật mật khẩu mới an toàn.
                            </p>
                            
                            <a href="/bookstore/pages/auth/login.php" class="btn btn-warning btn-auth text-dark fw-bold px-5 py-3 rounded-pill shadow-sm transition-hover">
                                <i class="bi bi-box-arrow-in-right me-2 fs-5 align-middle"></i> ĐĂNG NHẬP NGAY
                            </a>
                        </div>

                    <?php else: ?>
                        <div class="text-center d-md-none mb-4">
                            <img src="https://img.icons8.com/3d-fluency/80/shield.png" alt="Bảo mật" class="mb-2">
                            <h3 class="fw-bolder" style="color: #0f3460;">Mật khẩu mới</h3>
                        </div>

                        <h3 class="fw-bolder d-none d-md-block mb-1" style="color: #0f3460;">Tạo mật khẩu mới</h3>
                        <div class="mb-4 d-none d-md-block">
                            <p class="text-muted small mb-1">
                                Đặt lại mật khẩu cho: <strong class="text-dark"><?= htmlspecialchars($resetRecord['email']) ?></strong>
                            </p>
                            <p class="text-warning small fw-medium mb-0">
                                <i class="bi bi-clock-history me-1"></i>Link hết hạn lúc: <?= date('H:i, d/m/Y', strtotime($resetRecord['expires_at'])) ?>
                            </p>
                        </div>

                        <?php if (isset($errors['system'])): ?>
                            <div class="alert alert-danger d-flex align-items-center p-3 rounded-3 mb-4 border-0 bg-danger-subtle text-danger shadow-sm">
                                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                                <div class="small fw-medium"><?= htmlspecialchars($errors['system']) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="/bookstore/pages/auth/reset_password.php?token=<?= htmlspecialchars($token) ?>" novalidate>

                            <div class="mb-4">
                                <label for="new_password" class="form-label fw-bold text-secondary small text-uppercase">
                                    Mật khẩu mới <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-lg auth-input-group shadow-sm">
                                    <span class="input-group-text"><i class="bi bi-key text-muted"></i></span>
                                    <input
                                        type="password"
                                        id="new_password"
                                        name="new_password"
                                        class="form-control fs-6 <?= isset($errors['new_password']) ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                        placeholder="Tối thiểu 6 ký tự"
                                        autocomplete="new-password"
                                        autofocus
                                    >
                                    <button class="btn border border-secondary-subtle border-start-0 toggle-password"
                                            type="button" data-target="new_password"
                                            title="Hiện/ẩn mật khẩu">
                                        <i class="bi bi-eye text-secondary"></i>
                                    </button>
                                </div>
                                
                                <div class="mt-2 px-1">
                                    <div class="progress" style="height: 5px; border-radius: 10px;">
                                        <div id="pwStrengthBar" class="progress-bar" style="width: 0%; transition: width .3s, background .3s;"></div>
                                    </div>
                                    <p id="pwStrengthText" class="text-muted small mt-1 mb-0 fw-medium"></p>
                                </div>

                                <?php if (isset($errors['new_password'])): ?>
                                    <div class="text-danger small mt-1 fw-medium">
                                        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['new_password']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-bold text-secondary small text-uppercase">
                                    Xác nhận mật khẩu <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-lg auth-input-group shadow-sm">
                                    <span class="input-group-text"><i class="bi bi-shield-lock text-muted"></i></span>
                                    <input
                                        type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        class="form-control fs-6 <?= isset($errors['confirm_password']) ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                        placeholder="Nhập lại mật khẩu mới"
                                        autocomplete="new-password"
                                    >
                                    <button class="btn border border-secondary-subtle border-start-0 toggle-password"
                                            type="button" data-target="confirm_password"
                                            title="Hiện/ẩn mật khẩu">
                                        <i class="bi bi-eye text-secondary"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="text-danger small mt-1 fw-medium">
                                        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['confirm_password']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-grid mt-5">
                                <button type="submit" class="btn btn-warning btn-auth text-dark fs-5 fw-bold">
                                    <i class="bi bi-check-circle me-2"></i>CẬP NHẬT MẬT KHẨU
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4 pt-2">
                            <p class="text-muted small mb-0">
                                Link hết hạn hoặc gặp lỗi? 
                                <a href="/bookstore/pages/auth/forgot_password.php" class="text-dark fw-bold text-decoration-none border-bottom border-warning border-2 pb-1 ms-1 transition-hover">
                                    Gửi lại email
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
            
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>