<?php
// forgot_password.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Đã đăng nhập rồi thì không cần quên mật khẩu
if ($isLoggedIn) {
    header('Location: /bookstore/index.php');
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    // ── 1. VALIDATE EMAIL ─────────────────────────────────────────────────────
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Vui lòng nhập địa chỉ email hợp lệ.';
    } else {

        // ── 2. KIỂM TRA EMAIL CÓ TỒN TẠI TRONG HỆ THỐNG ─────────────────────
        $stmt = $pdo->prepare("SELECT id, fullname FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Dù email có tồn tại hay không, luôn hiện thông báo thành công
        // để tránh kẻ tấn công dò xem email nào đã đăng ký (security best practice)
        if ($user) {

            // ── 3. XÓA TOKEN CŨ CÒN TỒN TẠI CỦA EMAIL NÀY ──────────────────
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")
                ->execute([$email]);

            // ── 4. TẠO TOKEN NGẪU NHIÊN AN TOÀN (64 hex chars = 32 bytes) ────
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 30 * 60); // Hết hạn sau 30 phút

            $pdo->prepare("
                INSERT INTO password_resets (email, token, expires_at)
                VALUES (?, ?, ?)
            ")->execute([$email, $token, $expiresAt]);

            // ── 5. GỬI EMAIL QUA GMAIL SMTP ──────────────────────────────────
            $resetLink = APP_URL . '/reset_password.php?token=' . $token;

            $mail = new PHPMailer(true);
            try {
                // Cấu hình SMTP
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USERNAME;
                $mail->Password   = MAIL_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;
                $mail->CharSet    = 'UTF-8';

                // Người gửi & người nhận
                $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
                $mail->addAddress($email, $user['fullname']);

                // Nội dung email HTML
                $mail->isHTML(true);
                $mail->Subject = '[Book Store] Đặt lại mật khẩu của bạn';
                $mail->Body    = "
                <!DOCTYPE html>
                <html lang='vi'>
                <head><meta charset='UTF-8'></head>
                <body style='font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;'>
                    <div style='max-width:560px;margin:0 auto;background:#fff;
                                border-radius:12px;overflow:hidden;
                                box-shadow:0 4px 16px rgba(0,0,0,.08);'>

                        <!-- Header -->
                        <div style='background:#1a1a2e;padding:28px 32px;text-align:center;'>
                            <h1 style='color:#ffc107;margin:0;font-size:1.4rem;'>
                                📚 Book Store
                            </h1>
                        </div>

                        <!-- Body -->
                        <div style='padding:32px;'>
                            <h2 style='color:#1a1a2e;margin-top:0;'>
                                Xin chào, {$user['fullname']}!
                            </h2>
                            <p style='color:#555;line-height:1.7;'>
                                Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản
                                gắn với địa chỉ email này.
                            </p>
                            <p style='color:#555;line-height:1.7;'>
                                Nhấn vào nút bên dưới để tạo mật khẩu mới.
                                Link này sẽ <strong>hết hạn sau 30 phút</strong>.
                            </p>

                            <!-- CTA Button -->
                            <div style='text-align:center;margin:32px 0;'>
                                <a href='{$resetLink}'
                                   style='display:inline-block;background:#ffc107;color:#000;
                                          font-weight:700;font-size:1rem;padding:14px 36px;
                                          border-radius:8px;text-decoration:none;
                                          letter-spacing:.3px;'>
                                    🔑 Đặt lại mật khẩu
                                </a>
                            </div>

                            <p style='color:#888;font-size:.85rem;line-height:1.6;'>
                                Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.
                                Tài khoản của bạn vẫn an toàn.
                            </p>

                            <!-- Link dự phòng -->
                            <div style='background:#f8f9fa;border-radius:8px;
                                        padding:12px 16px;margin-top:20px;'>
                                <p style='color:#888;font-size:.78rem;margin:0 0 4px;'>
                                    Nếu nút không hoạt động, copy link này vào trình duyệt:
                                </p>
                                <p style='color:#0d6efd;font-size:.78rem;
                                           word-break:break-all;margin:0;'>
                                    {$resetLink}
                                </p>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div style='background:#f8f9fa;padding:16px 32px;
                                    text-align:center;border-top:1px solid #e9ecef;'>
                            <p style='color:#aaa;font-size:.78rem;margin:0;'>
                                © " . date('Y') . " Book Store · Email này được gửi tự động, vui lòng không reply.
                            </p>
                        </div>
                    </div>
                </body>
                </html>";

                // Fallback text cho client không hỗ trợ HTML
                $mail->AltBody = "Xin chào {$user['fullname']},\n\n"
                    . "Link đặt lại mật khẩu của bạn (hết hạn sau 30 phút):\n"
                    . $resetLink . "\n\n"
                    . "Nếu bạn không yêu cầu, hãy bỏ qua email này.\n\n"
                    . "Book Store";

                $mail->send();

            } catch (Exception $e) {
                // Ghi log lỗi SMTP nhưng không hiện ra user
                error_log('PHPMailer Error: ' . $mail->ErrorInfo);
            }
        }

        // Luôn hiện thông báo thành công dù email có tồn tại hay không
        $success = true;
    }
}
?>

<!-- ========== GIAO DIỆN QUÊN MẬT KHẨU ========== -->
<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">

                    <?php if ($success): ?>
                        <!-- Trạng thái đã gửi email -->
                        <div class="text-center">
                            <div class="mb-4">
                                <div class="rounded-circle bg-success bg-opacity-15 d-inline-flex
                                            align-items-center justify-content-center"
                                     style="width:80px;height:80px;">
                                    <i class="bi bi-envelope-check-fill text-success"
                                       style="font-size:2.2rem;"></i>
                                </div>
                            </div>
                            <h4 class="fw-bold mb-2">Kiểm tra hộp thư!</h4>
                            <p class="text-muted mb-1">
                                Nếu địa chỉ email của bạn đã được đăng ký,
                                chúng tôi đã gửi link đặt lại mật khẩu.
                            </p>
                            <p class="text-muted small mb-4">
                                <i class="bi bi-clock me-1"></i>
                                Link có hiệu lực trong <strong>30 phút</strong>.
                                Kiểm tra cả thư mục <strong>Spam</strong> nếu không thấy.
                            </p>
                            <a href="/bookstore/login.php"
                               class="btn btn-warning fw-bold px-4">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Về trang đăng nhập
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Form nhập email -->
                        <div class="text-center mb-4">
                            <div class="rounded-circle bg-warning bg-opacity-15 d-inline-flex
                                        align-items-center justify-content-center mb-3"
                                 style="width:64px;height:64px;">
                                <i class="bi bi-key-fill text-warning fs-2"></i>
                            </div>
                            <h4 class="fw-bold mb-1">Quên mật khẩu?</h4>
                            <p class="text-muted small">
                                Nhập email đã đăng ký — chúng tôi sẽ gửi link
                                để bạn tạo mật khẩu mới.
                            </p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2">
                                <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                                <span><?= htmlspecialchars($error) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" novalidate>
                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold">
                                    Địa chỉ Email <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-envelope text-muted"></i>
                                    </span>
                                    <input type="email"
                                           id="email"
                                           name="email"
                                           class="form-control"
                                           placeholder="Nhập email đã đăng ký"
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                           autofocus>
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-warning fw-bold py-2">
                                    <i class="bi bi-send me-2"></i>Gửi link đặt lại mật khẩu
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">
                        <p class="text-center text-muted small mb-0">
                            Nhớ mật khẩu rồi?
                            <a href="/bookstore/login.php"
                               class="text-warning fw-semibold text-decoration-none">
                                Đăng nhập ngay
                            </a>
                        </p>

                    <?php endif; ?>

                </div><!-- /.card-body -->
            </div><!-- /.card -->
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>