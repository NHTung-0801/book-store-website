<?php
// forgot_password.php

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../vendor/autoload.php';

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
            $token = bin2hex(random_bytes(32));

            // Sử dụng DATE_ADD(NOW(), ...) của MySQL để tự động cộng 30 phút theo đúng múi giờ
            $pdo->prepare("
                INSERT INTO password_resets (email, token, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ")->execute([$email, $token]);

            // ── 5. GỬI EMAIL QUA GMAIL SMTP ──────────────────────────────────
            $resetLink = APP_URL . '/pages/auth/reset_password.php?token=' . $token;

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
                $mail->Subject = '[NOVELTY] Đặt lại mật khẩu của bạn';
                $mail->Body    = "
                <!DOCTYPE html>
                <html lang='vi'>
                <head><meta charset='UTF-8'></head>
                <body style='font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;'>
                    <div style='max-width:560px;margin:0 auto;background:#fff;
                                border-radius:12px;overflow:hidden;
                                box-shadow:0 4px 16px rgba(0,0,0,.08);'>

                        <div style='background:#1a1a2e;padding:28px 32px;text-align:center;'>
                            <h1 style='color:#ffc107;margin:0;font-size:1.4rem;'>
                                📚 NOVELTY
                            </h1>
                        </div>

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

                        <div style='background:#f8f9fa;padding:16px 32px;
                                    text-align:center;border-top:1px solid #e9ecef;'>
                            <p style='color:#aaa;font-size:.78rem;margin:0;'>
                                © " . date('Y') . " NOVELTY · Email này được gửi tự động, vui lòng không reply.
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
                    . "NOVELTY";

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

<main class="container auth-wrapper my-5">
    <div class="card auth-card">
        <div class="row g-0 h-100">
            
            <div class="col-md-5 auth-sidebar d-none d-md-flex flex-column justify-content-center align-items-center text-center">
                <div class="auth-sidebar-content">
                    <img src="https://img.icons8.com/3d-fluency/180/key.png" alt="Khôi phục mật khẩu" class="mb-4" style="filter: drop-shadow(0 10px 15px rgba(0,0,0,0.3));">
                    <h2 class="fw-bold text-white mb-3">Quên mật khẩu?</h2>
                    <p class="text-white-50 px-4" style="font-size: 0.95rem; line-height: 1.6;">
                        Đừng lo lắng! Hãy nhập email của bạn, chúng tôi sẽ gửi liên kết an toàn để bạn đặt lại mật khẩu mới một cách nhanh chóng.
                    </p>
                </div>
            </div>

            <div class="col-md-7 bg-white">
                <div class="auth-form-wrap">
                    
                    <?php if ($success): ?>
                        <div class="text-center py-4">
                            <div class="mb-4">
                                <div class="rounded-circle bg-success bg-opacity-15 d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                    <i class="bi bi-envelope-check-fill text-success" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                            <h3 class="fw-bolder mb-3" style="color: #0f3460;">Kiểm tra hộp thư!</h3>
                            <p class="text-muted mb-4 fs-6 px-md-4">
                                Nếu địa chỉ email của bạn đã được đăng ký, chúng tôi đã gửi một liên kết an toàn để đặt lại mật khẩu.
                            </p>
                            
                            <div class="alert border-warning border-start border-4 bg-warning bg-opacity-10 text-start mx-auto mb-5 shadow-sm" style="max-width: 450px;">
                                <p class="mb-0 small text-dark d-flex align-items-center">
                                    <i class="bi bi-clock-history text-warning me-3 fs-3"></i>
                                    <span>Link có hiệu lực trong <strong>30 phút</strong>. Vui lòng kiểm tra cả thư mục <strong>Spam / Thư rác</strong> nếu không thấy ở hộp thư chính.</span>
                                </p>
                            </div>

                            <a href="/bookstore/pages/auth/login.php" class="btn btn-warning btn-auth text-dark fw-bold px-5 py-3 rounded-pill shadow-sm transition-hover">
                                <i class="bi bi-box-arrow-in-right me-2 fs-5 align-middle"></i> QUAY LẠI ĐĂNG NHẬP
                            </a>
                        </div>

                    <?php else: ?>
                        <div class="text-center d-md-none mb-4">
                            <img src="https://img.icons8.com/3d-fluency/80/key.png" alt="Khôi phục mật khẩu" class="mb-2">
                            <h3 class="fw-bolder" style="color: #0f3460;">Khôi phục mật khẩu</h3>
                        </div>

                        <h3 class="fw-bolder d-none d-md-block mb-1" style="color: #0f3460;">Khôi phục mật khẩu</h3>
                        <p class="text-muted small mb-4 d-none d-md-block">Nhập email đã đăng ký để nhận liên kết tạo mật khẩu mới.</p>

                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center p-3 rounded-3 mb-4 border-0 bg-danger-subtle text-danger shadow-sm" role="alert">
                                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                                <div class="small fw-medium"><?= htmlspecialchars($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" novalidate>
                            <div class="mb-4">
                                <label for="email" class="form-label fw-bold text-secondary small text-uppercase">
                                    Địa chỉ Email <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-lg auth-input-group shadow-sm">
                                    <span class="input-group-text"><i class="bi bi-envelope text-muted"></i></span>
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        class="form-control fs-6 <?= $error ? 'is-invalid border-danger' : 'border-secondary-subtle' ?>"
                                        placeholder="Ví dụ: example@email.com"
                                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                        autofocus
                                    >
                                </div>
                            </div>

                            <div class="d-grid mt-5">
                                <button type="submit" class="btn btn-warning btn-auth text-dark fs-5 fw-bold">
                                    <i class="bi bi-send me-2"></i>GỬI LIÊN KẾT
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-5 pt-3">
                            <p class="text-muted small mb-0">
                                Bạn đã nhớ mật khẩu? 
                                <a href="/bookstore/pages/auth/login.php" class="text-dark fw-bold text-decoration-none border-bottom border-warning border-2 pb-1 ms-1 transition-hover">
                                    Đăng nhập ngay
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