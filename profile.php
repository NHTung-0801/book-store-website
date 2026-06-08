<?php
// profile.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// ── KIỂM TRA ĐĂNG NHẬP ───────────────────────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: /bookstore/login.php');
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
    header('Location: /bookstore/logout.php');
    exit;
}

// ── THỐNG KÊ CÁ NHÂN ─────────────────────────────────────────────────────────
// Tổng đơn hàng
$totalOrders = (int) $pdo->prepare("
    SELECT COUNT(*) FROM orders WHERE user_id = ?
")->execute([$userId]) ? $pdo->prepare("
    SELECT COUNT(*) FROM orders WHERE user_id = ?
") : 0;

$stmtStats = $pdo->prepare("
    SELECT
        COUNT(*)                                            AS total_orders,
        COALESCE(SUM(total_price), 0)                      AS total_spent,
        SUM(CASE WHEN status = 'delivered'  THEN 1 ELSE 0 END) AS delivered,
        SUM(CASE WHEN status = 'pending'    THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'cancelled'  THEN 1 ELSE 0 END) AS cancelled
    FROM orders
    WHERE user_id = ?
");
$stmtStats->execute([$userId]);
$stats = $stmtStats->fetch();

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
// XỬ LÝ POST
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tab = $_POST['tab'] ?? 'info'; // 'info' | 'password'

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
            $stmtDup = $pdo->prepare("
                SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1
            ");
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

<!-- ========== NỘI DUNG TRANG HỒ SƠ ========== -->
<main class="container my-5">

    <!-- Tiêu đề trang -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <h3 class="fw-bold mb-0">
            <i class="bi bi-person-circle me-2 text-warning"></i>Hồ sơ cá nhân
        </h3>
    </div>

    <div class="row g-4">

        <!-- ══ CỘT TRÁI: AVATAR + THỐNG KÊ ══ -->
        <div class="col-lg-3">

            <!-- Card Avatar -->
            <div class="card border-0 shadow-sm text-center mb-3">
                <div class="card-body p-4">

                    <!-- Avatar chữ cái -->
                    <div class="profile-avatar mx-auto mb-3">
                        <?= mb_strtoupper(mb_substr($user['fullname'] ?: $user['username'], 0, 1)) ?>
                    </div>

                    <h5 class="fw-bold mb-1">
                        <?= htmlspecialchars($user['fullname'] ?: $user['username']) ?>
                    </h5>
                    <p class="text-muted small mb-2">
                        @<?= htmlspecialchars($user['username']) ?>
                    </p>

                    <!-- Badge vai trò -->
                    <?php if ($user['role'] == 1): ?>
                        <span class="badge bg-danger px-3 py-2 rounded-pill">
                            <i class="bi bi-shield-fill-check me-1"></i>Quản trị viên
                        </span>
                    <?php else: ?>
                        <span class="badge bg-primary px-3 py-2 rounded-pill">
                            <i class="bi bi-person-fill me-1"></i>Thành viên
                        </span>
                    <?php endif; ?>

                    <!-- Ngày tham gia -->
                    <?php if (!empty($user['created_at'])): ?>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-calendar3 me-1"></i>
                        Tham gia: <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                    </p>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Card thống kê đơn hàng -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-dark text-white fw-bold py-2 px-3 small">
                    <i class="bi bi-bar-chart me-1 text-warning"></i>Thống kê mua hàng
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Tổng đơn hàng</span>
                        <span class="fw-bold"><?= $stats['total_orders'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Đã giao thành công</span>
                        <span class="fw-bold text-success"><?= $stats['delivered'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Đang chờ xử lý</span>
                        <span class="fw-bold text-warning"><?= $stats['pending'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted small">Đã hủy</span>
                        <span class="fw-bold text-danger"><?= $stats['cancelled'] ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Tổng chi tiêu</span>
                        <span class="fw-bold text-danger small">
                            <?= number_format($stats['total_spent'], 0, ',', '.') ?>₫
                        </span>
                    </div>
                </div>
            </div>

            <!-- Liên kết nhanh -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 d-grid gap-2">
                    <a href="/bookstore/my_orders.php"
                       class="btn btn-outline-warning btn-sm fw-semibold">
                        <i class="bi bi-bag-check me-2"></i>Đơn hàng của tôi
                    </a>
                    <a href="/bookstore/cart.php"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-cart3 me-2"></i>Giỏ hàng
                    </a>
                    <a href="/bookstore/logout.php"
                       class="btn btn-outline-danger btn-sm btn-logout"
                       data-confirm="Bạn có chắc muốn đăng xuất?">
                        <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                    </a>
                </div>
            </div>

        </div><!-- /.col-lg-3 -->

        <!-- ══ CỘT PHẢI: TABS NỘI DUNG ══ -->
        <div class="col-lg-9">

            <!-- Tab Navigation -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom px-4 pt-3 pb-0">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs">
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'info' ? 'active fw-semibold' : '' ?>"
                               href="#tab-info" data-bs-toggle="tab">
                                <i class="bi bi-person me-2"></i>Thông tin cá nhân
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeTab === 'password' ? 'active fw-semibold' : '' ?>"
                               href="#tab-password" data-bs-toggle="tab">
                                <i class="bi bi-lock me-2"></i>Đổi mật khẩu
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#tab-orders" data-bs-toggle="tab">
                                <i class="bi bi-clock-history me-2"></i>Đơn hàng gần đây
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-4">
                    <div class="tab-content">

                        <!-- ── TAB 1: THÔNG TIN CÁ NHÂN ── -->
                        <div class="tab-pane fade <?= $activeTab === 'info' ? 'show active' : '' ?>"
                             id="tab-info">

                            <!-- Thông báo thành công -->
                            <?php if ($success === 'info'): ?>
                                <div class="alert alert-success d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                                    <span>Cập nhật thông tin cá nhân thành công!</span>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" novalidate>
                                <input type="hidden" name="tab" value="info">

                                <div class="row g-3">

                                    <!-- Username (chỉ đọc) -->
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">
                                            Tên đăng nhập
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="bi bi-at text-muted"></i>
                                            </span>
                                            <input type="text"
                                                   class="form-control bg-light"
                                                   value="<?= htmlspecialchars($user['username']) ?>"
                                                   readonly>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            Tên đăng nhập không thể thay đổi.
                                        </div>
                                    </div>

                                    <!-- Họ và tên -->
                                    <div class="col-md-6">
                                        <label for="fullname" class="form-label fw-semibold small">
                                            Họ và tên <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="bi bi-person text-muted"></i>
                                            </span>
                                            <input type="text"
                                                   id="fullname" name="fullname"
                                                   class="form-control <?= isset($errors['fullname']) ? 'is-invalid' : '' ?>"
                                                   value="<?= htmlspecialchars($_POST['fullname'] ?? $user['fullname'] ?? '') ?>"
                                                   placeholder="Nguyễn Văn A">
                                            <?php if (isset($errors['fullname'])): ?>
                                                <div class="invalid-feedback">
                                                    <?= htmlspecialchars($errors['fullname']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Email -->
                                    <div class="col-md-6">
                                        <label for="email" class="form-label fw-semibold small">
                                            Email <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="bi bi-envelope text-muted"></i>
                                            </span>
                                            <input type="email"
                                                   id="email" name="email"
                                                   class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                                   value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '') ?>"
                                                   placeholder="example@email.com">
                                            <?php if (isset($errors['email'])): ?>
                                                <div class="invalid-feedback">
                                                    <?= htmlspecialchars($errors['email']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Số điện thoại -->
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label fw-semibold small">
                                            Số điện thoại
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="bi bi-telephone text-muted"></i>
                                            </span>
                                            <input type="tel"
                                                   id="phone" name="phone"
                                                   class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                                   value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>"
                                                   placeholder="0901 234 567">
                                            <?php if (isset($errors['phone'])): ?>
                                                <div class="invalid-feedback">
                                                    <?= htmlspecialchars($errors['phone']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Địa chỉ -->
                                    <div class="col-12">
                                        <label for="address" class="form-label fw-semibold small">
                                            Địa chỉ giao hàng mặc định
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light align-items-start pt-2">
                                                <i class="bi bi-geo-alt text-muted"></i>
                                            </span>
                                            <textarea id="address" name="address"
                                                      rows="3"
                                                      class="form-control"
                                                      placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố"
                                            ><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Địa chỉ này sẽ được tự động điền khi thanh toán.
                                        </div>
                                    </div>

                                </div><!-- /.row -->

                                <hr class="my-4">

                                <div class="d-flex justify-content-end gap-2">
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Đặt lại
                                    </button>
                                    <button type="submit" class="btn btn-warning fw-bold px-4">
                                        <i class="bi bi-check-circle me-2"></i>Lưu thay đổi
                                    </button>
                                </div>

                            </form>
                        </div>

                        <!-- ── TAB 2: ĐỔI MẬT KHẨU ── -->
                        <div class="tab-pane fade <?= $activeTab === 'password' ? 'show active' : '' ?>"
                             id="tab-password">

                            <?php if ($success === 'password'): ?>
                                <div class="alert alert-success d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                                    <span>Đổi mật khẩu thành công! Vui lòng dùng mật khẩu mới cho lần đăng nhập tiếp theo.</span>
                                </div>
                            <?php endif; ?>

                            <div class="row justify-content-center">
                                <div class="col-md-8">

                                    <div class="alert alert-info d-flex gap-2 mb-4">
                                        <i class="bi bi-shield-lock-fill flex-shrink-0 mt-1"></i>
                                        <div class="small">
                                            Mật khẩu mạnh nên có ít nhất <strong>6 ký tự</strong>,
                                            kết hợp chữ hoa, chữ thường và số.
                                        </div>
                                    </div>

                                    <form method="POST" action="" novalidate>
                                        <input type="hidden" name="tab" value="password">

                                        <!-- Mật khẩu hiện tại -->
                                        <div class="mb-3">
                                            <label for="current_password"
                                                   class="form-label fw-semibold small">
                                                Mật khẩu hiện tại <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light">
                                                    <i class="bi bi-lock text-muted"></i>
                                                </span>
                                                <input type="password"
                                                       id="current_password"
                                                       name="current_password"
                                                       class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>"
                                                       placeholder="Nhập mật khẩu hiện tại"
                                                       autocomplete="current-password">
                                                <button type="button"
                                                        class="btn btn-outline-secondary toggle-password"
                                                        data-target="current_password">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if (isset($errors['current_password'])): ?>
                                                    <div class="invalid-feedback">
                                                        <i class="bi bi-exclamation-circle me-1"></i>
                                                        <?= htmlspecialchars($errors['current_password']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Mật khẩu mới -->
                                        <div class="mb-3">
                                            <label for="new_password"
                                                   class="form-label fw-semibold small">
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
                                            <!-- Thanh độ mạnh mật khẩu -->
                                            <div class="mt-2">
                                                <div class="progress" style="height:4px;">
                                                    <div id="pwStrengthBar"
                                                         class="progress-bar"
                                                         style="width:0%;transition:width .3s,background .3s;">
                                                    </div>
                                                </div>
                                                <p id="pwStrengthText"
                                                   class="text-muted small mt-1 mb-0"></p>
                                            </div>
                                        </div>

                                        <!-- Xác nhận mật khẩu mới -->
                                        <div class="mb-4">
                                            <label for="confirm_password"
                                                   class="form-label fw-semibold small">
                                                Xác nhận mật khẩu mới <span class="text-danger">*</span>
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
                                            <button type="submit"
                                                    class="btn btn-warning fw-bold py-2">
                                                <i class="bi bi-shield-lock me-2"></i>Đổi mật khẩu
                                            </button>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ── TAB 3: ĐƠN HÀNG GẦN ĐÂY ── -->
                        <div class="tab-pane fade" id="tab-orders">

                            <?php if (empty($recentOrders)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-bag-x text-muted" style="font-size:3.5rem;"></i>
                                    <p class="text-muted mt-3">Bạn chưa có đơn hàng nào.</p>
                                    <a href="/bookstore/index.php"
                                       class="btn btn-warning fw-bold px-4">
                                        <i class="bi bi-book me-2"></i>Mua sách ngay
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($recentOrders as $order):
                                        $badge = getStatusBadge($order['status']);
                                    ?>
                                    <div class="d-flex align-items-center justify-content-between
                                                p-3 rounded-3 border bg-light">
                                        <div>
                                            <p class="fw-bold mb-1">
                                                Đơn hàng
                                                #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                            </p>
                                            <p class="text-muted small mb-0">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= !empty($order['created_at'])
                                                    ? date('d/m/Y H:i', strtotime($order['created_at']))
                                                    : '—' ?>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <p class="fw-bold text-danger mb-1">
                                                <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                            </p>
                                            <span class="badge <?= $badge['class'] ?> rounded-pill">
                                                <i class="bi <?= $badge['icon'] ?> me-1"></i>
                                                <?= $badge['label'] ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="text-center mt-4">
                                    <a href="/bookstore/my_orders.php"
                                       class="btn btn-outline-warning fw-semibold">
                                        <i class="bi bi-bag-check me-2"></i>
                                        Xem tất cả đơn hàng
                                    </a>
                                </div>
                            <?php endif; ?>

                        </div>

                    </div><!-- /.tab-content -->
                </div><!-- /.card-body -->
            </div><!-- /.card tabs -->

        </div><!-- /.col-lg-9 -->
    </div><!-- /.row -->
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-logout').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var msg = btn.getAttribute('data-confirm') || 'Bạn có chắc muốn tiếp tục?';
            var href = btn.href;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Xác nhận',
                    text: msg,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Đăng xuất',
                    cancelButtonText: 'Hủy'
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