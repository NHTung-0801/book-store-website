<?php
// profile.php

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

// ── KIỂM TRA ĐĂNG NHẬP ───────────────────────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: /bookstore/pages/auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$errors  = [];
$success = '';

// ── QUERY THÔNG TIN USER HIỆN TẠI ────────────────────────────────────────────
$stmtUser = $pdo->prepare("
    SELECT id, username, fullname, email, phone, address, role, created_at
    FROM   users
    WHERE  id = ?
    LIMIT  1
");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

if (!$user) {
    // Tài khoản không tồn tại → đăng xuất
    header('Location: /bookstore/pages/auth/logout.php');
    exit;
}

// ── THỐNG KÊ CÁ NHÂN ─────────────────────────────────────────────────────────
$stmtStats = $pdo->prepare("
    SELECT
        COUNT(*)                                           AS total_orders,
        COALESCE(SUM(total_price), 0)                      AS total_spent,
        SUM(CASE WHEN status = 'delivered'  THEN 1 ELSE 0 END) AS delivered,
        SUM(CASE WHEN status = 'pending'    THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'cancelled'  THEN 1 ELSE 0 END) AS cancelled
    FROM orders
    WHERE user_id = ?
");
$stmtStats->execute([$userId]);
$stats = $stmtStats->fetch();

$totalOrders = (int) $stats['total_orders'];

// ── 3 ĐƠN HÀNG GẦN NHẤT ──────────────────────────────────────────────────────
$stmtRecent = $pdo->prepare("
    SELECT id, total_price, status, created_at
    FROM   orders
    WHERE  user_id = ?
    ORDER BY id DESC
    LIMIT 3
");
$stmtRecent->execute([$userId]);
$recentOrders = $stmtRecent->fetchAll();

// ════════════════════════════════════════════════════════════
// XỬ LÝ POST (CẬP NHẬT THÔNG TIN & ĐỔI MẬT KHẨU)
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tab = $_POST['tab'] ?? 'info';

    // ────────────────────────────────────────────────────────
    // TAB 1: CẬP NHẬT THÔNG TIN CÁ NHÂN
    // ────────────────────────────────────────────────────────
    if ($tab === 'info') {

        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $phone    = trim($_POST['phone']    ?? '');
        $address  = trim($_POST['address']  ?? '');

        // Validate
        if (empty($fullname)) {
            $errors['fullname'] = 'Họ và tên không được để trống.';
        } elseif (mb_strlen($fullname) > 100) {
            $errors['fullname'] = 'Họ và tên tối đa 100 ký tự.';
        }
        if (empty($email)) {
            $errors['email'] = 'Email không được để trống.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không hợp lệ.';
        }
        if (!empty($phone) && !preg_match('/^(0|\+84)[0-9]{8,10}$/', $phone)) {
            $errors['phone'] = 'Số điện thoại không hợp lệ.';
        }

        // Kiểm tra email trùng với người khác
        if (empty($errors['email'])) {
            $stmtDup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmtDup->execute([$email, $userId]);
            if ($stmtDup->fetch()) {
                $errors['email'] = 'Email này đã được sử dụng bởi tài khoản khác.';
            }
        }

        if (empty($errors)) {
            $pdo->prepare("
                UPDATE users
                SET fullname = ?, email = ?, phone = ?, address = ?
                WHERE id = ?
            ")->execute([$fullname, $email, $phone, $address, $userId]);

            // Cập nhật fullname trong session để Navbar hiển thị đúng
            $_SESSION['fullname'] = $fullname;

            // Reload lại $user để form hiển thị giá trị mới
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch();

            $success = 'info';
        }
    }

    // ────────────────────────────────────────────────────────
    // TAB 2: ĐỔI MẬT KHẨU
    // ────────────────────────────────────────────────────────
    if ($tab === 'password') {

        $currentPw  = $_POST['current_password']  ?? '';
        $newPw      = $_POST['new_password']       ?? '';
        $confirmPw  = $_POST['confirm_password']   ?? '';

        // Validate
        if (empty($currentPw)) {
            $errors['current_password'] = 'Vui lòng nhập mật khẩu hiện tại.';
        }
        if (empty($newPw)) {
            $errors['new_password'] = 'Vui lòng nhập mật khẩu mới.';
        } elseif (strlen($newPw) < 6) {
            $errors['new_password'] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        }
        if (empty($confirmPw)) {
            $errors['confirm_password'] = 'Vui lòng xác nhận mật khẩu mới.';
        } elseif ($newPw !== $confirmPw) {
            $errors['confirm_password'] = 'Xác nhận mật khẩu không khớp.';
        }

        // Xác minh mật khẩu hiện tại
        if (empty($errors['current_password'])) {
            $stmtPw = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmtPw->execute([$userId]);
            $pwHash = $stmtPw->fetchColumn();

            if (!password_verify($currentPw, $pwHash)) {
                $errors['current_password'] = 'Mật khẩu hiện tại không đúng.';
            }
        }

        // Không cho đặt mật khẩu mới trùng mật khẩu cũ
        if (empty($errors) && password_verify($newPw, $pwHash ?? '')) {
            $errors['new_password'] = 'Mật khẩu mới không được trùng mật khẩu hiện tại.';
        }

        if (empty($errors)) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);
            $success = 'password';
        }
    }
}

// Helper badge trạng thái đơn hàng
function getStatusBadge(string $status): array {
    return match($status) {
        'pending'   => ['class' => 'bg-warning text-dark', 'icon' => 'bi-clock',       'label' => 'Chờ xác nhận'],
        'confirmed' => ['class' => 'bg-info text-dark',    'icon' => 'bi-check-circle', 'label' => 'Đã xác nhận'],
        'shipping'  => ['class' => 'bg-primary',           'icon' => 'bi-truck',        'label' => 'Đang giao'],
        'delivered' => ['class' => 'bg-success',           'icon' => 'bi-bag-check',    'label' => 'Đã giao'],
        'cancelled' => ['class' => 'bg-danger',            'icon' => 'bi-x-circle',     'label' => 'Đã hủy'],
        default     => ['class' => 'bg-secondary',         'icon' => 'bi-question-circle','label' => ucfirst($status)],
    };
}

// Xác định tab đang active sau khi submit
$activeTab = ($_POST['tab'] ?? '') === 'password' ? 'password' : 'info';
?>

<main class="container my-5">
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-3 d-flex align-items-center mb-4 border-0" style="background-color: #d1e7dd; color: #0f5132;" role="alert">
            <img src="https://img.icons8.com/3d-fluency/94/ok.png" width="28" class="me-2" alt="Success">
            <div class="fw-medium">
                <?= $success === 'info' ? 'Cập nhật thông tin cá nhân thành công!' : 'Đổi mật khẩu thành công! Vui lòng dùng mật khẩu mới cho lần đăng nhập tiếp theo.' ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3 mb-4 border-0" style="background-color: #f8d7da; color: #842029;" role="alert">
            <div class="d-flex align-items-center mb-2">
                <img src="https://img.icons8.com/3d-fluency/94/cancel.png" width="28" class="me-2" alt="Error">
                <strong>Vui lòng kiểm tra lại:</strong>
            </div>
            <ul class="mb-0 ps-4 small fw-medium">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="top: 10px;"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div style="height: 140px; background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%); position: relative;">
            <div style="position: absolute; right: 20px; top: -20px; opacity: 0.08;">
                <i class="bi bi-book-half" style="font-size: 8rem; color: #fff;"></i>
            </div>
        </div>
        
        <div class="card-body px-4 pb-4 position-relative">
            <div class="position-absolute" style="top: -50px; left: 30px;">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['fullname']) ?>&background=ffc107&color=0f3460&size=100&bold=true&rounded=true" 
                     alt="Avatar" class="border border-4 border-white shadow-sm bg-white" style="border-radius: 50%;">
            </div>
            
            <div class="mt-5 d-flex justify-content-between align-items-end flex-wrap gap-3 ms-2">
                <div>
                    <h4 class="fw-bolder mb-1" style="color: #0f3460; font-size: 1.6rem;">
                        <?= htmlspecialchars($user['fullname']) ?>
                    </h4>
                    <p class="text-muted mb-0 fw-medium">
                        <i class="bi bi-envelope-at me-2 text-warning"></i><?= htmlspecialchars($user['email']) ?>
                    </p>
                </div>
                <div>
                    <?php if ($user['role'] == 1): ?>
                        <span class="badge bg-danger px-3 py-2 rounded-pill shadow-sm fw-bold border border-danger" style="font-size: 0.85rem;">
                            <i class="bi bi-shield-fill-check me-1"></i> Quản trị viên
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm fw-bold border border-warning" style="font-size: 0.85rem;">
                            <i class="bi bi-star-fill me-1"></i> Thành viên hệ thống
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-4">
            
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm border border-light" style="width: 48px; height: 48px; background-color: #e0f7fa;">
                            <img src="https://img.icons8.com/3d-fluency/94/shopping-cart.png" width="28" alt="Cart">
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.75rem;">Đơn hàng đã đặt</div>
                            <div class="fw-bolder fs-5" style="color: #0f3460 !important;"><?= $totalOrders ?> đơn</div>
                        </div>
                    </div>
                    
                    <hr class="text-muted opacity-15 my-3">
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm border border-light" style="width: 48px; height: 48px; background-color: #fff3e0;">
                            <img src="https://img.icons8.com/3d-fluency/94/money-bag.png" width="28" alt="Money">
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.75rem;">Tổng chi tiêu</div>
                            <div class="fw-bolder fs-5 text-danger"><?= number_format($stats['total_spent'], 0, ',', '.') ?>₫</div>
                        </div>
                    </div>

                    <hr class="text-muted opacity-15 my-3">

                    <div class="d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm border border-light" style="width: 48px; height: 48px; background-color: #fce4ec;">
                            <img src="https://img.icons8.com/3d-fluency/94/calendar.png" width="28" alt="Date">
                        </div>
                        <div>
                            <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.75rem;">Ngày tham gia</div>
                            <div class="fw-bold text-dark"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-2">
                    <div class="nav flex-column nav-pills profile-nav" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        <button class="nav-link <?= $activeTab === 'info' ? 'active' : '' ?> text-start fw-semibold py-3 px-4 mb-1 rounded-3" id="v-pills-profile-tab" data-bs-toggle="pill" data-bs-target="#v-pills-profile" type="button" role="tab">
                            <i class="bi bi-person-lines-fill me-2 fs-5"></i> Thông tin cá nhân
                        </button>
                        <button class="nav-link <?= $activeTab === 'password' ? 'active' : '' ?> text-start fw-semibold py-3 px-4 mb-1 rounded-3" id="v-pills-password-tab" data-bs-toggle="pill" data-bs-target="#v-pills-password" type="button" role="tab">
                            <i class="bi bi-shield-lock-fill me-2 fs-5"></i> Đổi mật khẩu
                        </button>
                        <a href="/bookstore/pages/user/my_orders.php" class="nav-link text-start fw-semibold py-3 px-4 mb-1 rounded-3 text-dark">
                            <i class="bi bi-bag-check-fill me-2 fs-5 text-success"></i> Quản lý đơn hàng
                        </a>
                        <hr class="opacity-15 my-2">
                        <a href="/bookstore/pages/auth/logout.php" class="nav-link text-start fw-semibold py-3 px-4 rounded-3 text-danger btn-logout" data-confirm="Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?">
                            <i class="bi bi-box-arrow-right me-2 fs-5"></i> Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="border-top: 5px solid #ffc107 !important;">
                <div class="card-body p-4 p-md-5">
                    <div class="tab-content" id="v-pills-tabContent">
                        
                        <div class="tab-pane fade <?= $activeTab === 'info' ? 'show active' : '' ?>" id="v-pills-profile" role="tabpanel" tabindex="0">
                            <h5 class="fw-bolder mb-4" style="color: #0f3460;">
                                <img src="https://img.icons8.com/3d-fluency/94/resume.png" width="30" class="me-2 mb-1" alt="Profile">
                                Cập nhật Hồ sơ
                            </h5>
                            
                            <form action="" method="POST">
                                <input type="hidden" name="tab" value="info">
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-muted small fw-semibold text-uppercase">Tên đăng nhập</label>
                                        <div class="input-group bg-light rounded-3 px-3 py-2 border border-secondary-subtle shadow-sm">
                                            <i class="bi bi-person-badge text-secondary me-2 mt-1"></i>
                                            <span class="text-secondary fw-bold"><?= htmlspecialchars($user['username']) ?></span>
                                        </div>
                                        <div class="form-text" style="font-size: 0.75rem;">* Tên đăng nhập cố định, không thể thay đổi.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fullname" class="form-label text-muted small fw-semibold text-uppercase">Họ và tên <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-lg bg-light shadow-none border-secondary-subtle fs-6 shadow-sm <?= isset($errors['fullname']) ? 'is-invalid' : '' ?>" id="fullname" name="fullname" value="<?= htmlspecialchars($_POST['fullname'] ?? $user['fullname'] ?? '') ?>" required>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="email" class="form-label text-muted small fw-semibold text-uppercase">Email <span class="text-danger">*</span></label>
                                        <div class="input-group shadow-sm">
                                            <span class="input-group-text bg-light border-secondary-subtle text-muted"><i class="bi bi-envelope"></i></span>
                                            <input type="email" class="form-control bg-light shadow-none border-secondary-subtle <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label text-muted small fw-semibold text-uppercase">Số điện thoại</label>
                                        <div class="input-group shadow-sm">
                                            <span class="input-group-text bg-light border-secondary-subtle text-muted"><i class="bi bi-telephone"></i></span>
                                            <input type="tel" class="form-control bg-light shadow-none border-secondary-subtle <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="address" class="form-label text-muted small fw-semibold text-uppercase">Địa chỉ giao hàng mặc định</label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-light border-secondary-subtle text-muted align-items-start pt-3"><i class="bi bi-geo-alt"></i></span>
                                        <textarea class="form-control bg-light shadow-none border-secondary-subtle" id="address" name="address" rows="3"><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-warning btn-lg fw-bold rounded-pill px-5 shadow-sm btn-hover-effect text-dark">
                                        <i class="bi bi-floppy-fill me-2"></i> Lưu thay đổi
                                    </button>
                                </div>
                            </form>

                            <hr class="my-5 opacity-15">

                            <h6 class="fw-bold text-muted text-uppercase mb-3"><i class="bi bi-clock-history me-2"></i>Đơn hàng gần đây</h6>
                            <?php if (empty($recentOrders)): ?>
                                <div class="text-center py-4 bg-light rounded-4 border">
                                    <p class="text-muted mb-0">Bạn chưa có đơn hàng nào.</p>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($recentOrders as $order):
                                        $badge = getStatusBadge($order['status']);
                                    ?>
                                    <div class="d-flex align-items-center justify-content-between p-3 rounded-4 border bg-light shadow-sm">
                                        <div>
                                            <p class="fw-bold mb-1 text-dark">
                                                Đơn hàng #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                            </p>
                                            <p class="text-muted small mb-0">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= !empty($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : '—' ?>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <p class="fw-bold text-danger mb-1 fs-6">
                                                <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                            </p>
                                            <span class="badge <?= $badge['class'] ?> rounded-pill fw-medium">
                                                <i class="bi <?= $badge['icon'] ?> me-1"></i> <?= $badge['label'] ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="tab-pane fade <?= $activeTab === 'password' ? 'show active' : '' ?>" id="v-pills-password" role="tabpanel" tabindex="0">
                            <h5 class="fw-bolder mb-4" style="color: #0f3460;">
                                <img src="https://img.icons8.com/3d-fluency/94/key.png" width="30" class="me-2 mb-1" alt="Key">
                                Đổi Mật khẩu
                            </h5>

                            <form action="" method="POST">
                                <input type="hidden" name="tab" value="password">
                                
                                <div class="mb-4">
                                    <label for="current_password" class="form-label text-muted small fw-semibold text-uppercase">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-unlock text-muted"></i></span>
                                        <input type="password" class="form-control bg-light shadow-none border-secondary-subtle <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>" id="current_password" name="current_password" required placeholder="Nhập mật khẩu cũ">
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label text-muted small fw-semibold text-uppercase">Mật khẩu mới <span class="text-danger">*</span></label>
                                        <div class="input-group shadow-sm">
                                            <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-key text-info"></i></span>
                                            <input type="password" class="form-control bg-light shadow-none border-secondary-subtle <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>" id="new_password" name="new_password" required placeholder="Mật khẩu mới">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label text-muted small fw-semibold text-uppercase">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                        <div class="input-group shadow-sm">
                                            <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-check-circle text-success"></i></span>
                                            <input type="password" class="form-control bg-light shadow-none border-secondary-subtle <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" required placeholder="Nhập lại mật khẩu mới">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-dark btn-lg fw-bold rounded-pill px-5 shadow-sm btn-hover-effect">
                                        <i class="bi bi-shield-lock me-2"></i> Cập nhật Mật khẩu
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Script thông báo xác nhận Đăng xuất
    document.querySelectorAll('.btn-logout').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var msg = btn.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?';
            var href = btn.href;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xác nhận',
                    text: msg,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Đăng xuất',
                    cancelButtonText: 'Hủy',
                    border_radius: '15px'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        window.location.href = href;
                    }
                });
            } else {
                if (confirm(msg)) {
                    window.location.href = href;
                }
            }
        });
    });
});
</script>