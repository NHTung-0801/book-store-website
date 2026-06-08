<?php
// reset_password.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

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
    header('Location: /bookstore/forgot_password.php?msg=invalid');
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
$stmtToken->execute([$token]);
$resetRecord = $stmtToken->fetch();

// Token không hợp lệ hoặc đã hết hạn
if (!$resetRecord) {
    header('Location: /bookstore/forgot_password.php?msg=expired');
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

<!-- ========== GIAO DIỆN ĐẶT LẠI MẬT KHẨU ========== -->
<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">

                    <?php if ($success): ?>
                        <!-- Đặt lại thành công -->
                        <div class="text-center">
                            <div class="mb-4">
                                <div class="rounded-circle bg-success bg-opacity-15 d-inline-flex
                                            align-items-center justify-content-center"
                                     style="width:80px;height:80px;">
                                    <i class="bi bi-shield-check text-success"
                                       style="font-size:2.2rem;"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold mb-2">Mật khẩu đã được đặt lại!</h4>
                            <p class="text-muted mb-4">
                                Tài khoản <strong><?= htmlspecialchars($resetRecord['fullname']) ?></strong>
                                đã có mật khẩu mới. Hãy đăng nhập để tiếp tục mua sắm.
                            </p>
                            <a href="/bookstore/login.php"
                               class="btn btn-warning fw-bold px-5 py-2">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập ngay
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Form đặt mật khẩu mới -->
                        <div class="text-center mb-4">
                            <div class="rounded-circle bg-warning bg-opacity-15 d-inline-flex
                                        align-items-center justify-content-center mb-3"
                                 style="width:64px;height:64px;">
                                <i class="bi bi-lock-fill text-warning fs-2"></i>
                            </div>
                            <h4 class="fw-bold mb-1">Tạo mật khẩu mới</h4>
                            <p class="text-muted small mb-0">
                                Cho tài khoản:
                                <strong class="text-dark">
                                    <?= htmlspecialchars($resetRecord['email']) ?>
                                </strong>
                            </p>
                            <!-- Hiển thị thời gian còn lại -->
                            <p class="text-muted small mt-1">
                                <i class="bi bi-clock me-1 text-warning"></i>
                                Link hết hạn lúc:
                                <strong>
                                    <?= date('H:i, d/m/Y', strtotime($resetRecord['expires_at'])) ?>
                                </strong>
                            </p>
                        </div>

                        <?php if (isset($errors['system'])): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($errors['system']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST"
                              action="/bookstore/reset_password.php?token=<?= htmlspecialchars($token) ?>"
                              novalidate>

                            <!-- Mật khẩu mới -->
                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-semibold">
                                    Mật khẩu mới <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-key text-muted"></i>
                                    </span>
                                    <input type="password"
                                           id="new_password"
                                           name="new_password"
                                           class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                                           placeholder="Tối thiểu 6 ký tự"
                                           autocomplete="new-password">
                                    <button type="button"
                                            class="btn btn-outline-secondary toggle-password"
                                            data-target="new_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (isset($errors['new_password'])): ?>
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-circle me-1"></i>
                                            <?= htmlspecialchars($errors['new_password']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Thanh độ mạnh -->
                                <div class="mt-2">
                                    <div class="progress" style="height:4px;">
                                        <div id="pwStrengthBar" class="progress-bar"
                                             style="width:0%;transition:width .3s,background .3s;">
                                        </div>
                                    </div>
                                    <p id="pwStrengthText" class="text-muted small mt-1 mb-0"></p>
                                </div>
                            </div>

                            <!-- Xác nhận mật khẩu -->
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-semibold">
                                    Xác nhận mật khẩu <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-key-fill text-muted"></i>
                                    </span>
                                    <input type="password"
                                           id="confirm_password"
                                           name="confirm_password"
                                           class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                           placeholder="Nhập lại mật khẩu mới"
                                           autocomplete="new-password">
                                    <button type="button"
                                            class="btn btn-outline-secondary toggle-password"
                                            data-target="confirm_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-circle me-1"></i>
                                            <?= htmlspecialchars($errors['confirm_password']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning fw-bold py-2">
                                    <i class="bi bi-shield-lock me-2"></i>Đặt lại mật khẩu
                                </button>
                            </div>

                        </form>

                        <hr class="my-4">
                        <p class="text-center text-muted small mb-0">
                            <a href="/bookstore/forgot_password.php"
                               class="text-warning fw-semibold text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i>Gửi lại email
                            </a>
                        </p>

                    <?php endif; ?>

                </div><!-- /.card-body -->
            </div><!-- /.card -->
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>