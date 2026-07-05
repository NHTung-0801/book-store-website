<?php
// profile.php
$pageTitle = 'Hồ sơ cá nhân | NOVELTY';

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
        'pending'   => ['class' => 'bg-amber-100 text-amber-700 border-amber-200', 'icon' => 'bi-clock-fill',       'label' => 'Chờ xác nhận'],
        'confirmed' => ['class' => 'bg-blue-100 text-blue-700 border-blue-200',    'icon' => 'bi-check-circle-fill', 'label' => 'Đã xác nhận'],
        'shipping'  => ['class' => 'bg-indigo-100 text-indigo-700 border-indigo-200',           'icon' => 'bi-truck-front-fill',        'label' => 'Đang giao'],
        'delivered' => ['class' => 'bg-emerald-100 text-emerald-700 border-emerald-200',           'icon' => 'bi-box-seam-fill',    'label' => 'Đã giao'],
        'cancelled' => ['class' => 'bg-rose-100 text-rose-700 border-rose-200',            'icon' => 'bi-x-circle-fill',     'label' => 'Đã hủy'],
        default     => ['class' => 'bg-gray-100 text-gray-700 border-gray-200',         'icon' => 'bi-info-circle-fill','label' => ucfirst($status)],
    };
}

// Xác định tab đang active sau khi submit
$activeTab = ($_POST['tab'] ?? '') === 'password' ? 'password' : 'info';
?>

<main class="max-w-7xl mx-auto px-4 pt-[70px] pb-24 min-h-screen">
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-[0_8px_30px_rgb(0,0,0,0.08)] rounded-2xl d-flex align-items-center mb-6 border border-emerald-200" style="background-color: #ecfdf5; color: #065f46;" role="alert">
            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center me-3 shrink-0">
                <i class="bi bi-check-lg text-xl text-emerald-600"></i>
            </div>
            <div class="fw-medium font-body">
                <?= $success === 'info' ? 'Cập nhật thông tin cá nhân thành công!' : 'Đổi mật khẩu thành công! Vui lòng dùng mật khẩu mới cho lần đăng nhập tiếp theo.' ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-[0_8px_30px_rgb(0,0,0,0.08)] rounded-2xl mb-6 border border-rose-200" style="background-color: #fff1f2; color: #9f1239;" role="alert">
            <div class="d-flex align-items-center mb-2">
                <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center me-2 shrink-0">
                    <i class="bi bi-exclamation-lg text-lg text-rose-600"></i>
                </div>
                <strong class="font-body">Vui lòng kiểm tra lại:</strong>
            </div>
            <ul class="mb-0 ps-11 small fw-medium font-body list-disc">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="top: 15px;"></button>
        </div>
    <?php endif; ?>

    <!-- Hero Banner "Reader ID Card Header" -->
    <div class="w-full h-44 bg-gradient-to-r from-[#111111] via-[#333333] to-[#111111] rounded-3xl relative overflow-hidden shadow-lg mb-12 border border-black/10 group after:absolute after:inset-0 after:bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] after:[background-size:16px_16px] after:opacity-10">
    </div>

    <!-- Avatar & Info -->
    <div class="-mt-16 ml-8 sm:ml-12 flex flex-col sm:flex-row sm:items-end gap-6 relative z-10 mb-12">
        <?php
            $nameParts = explode(' ', trim($user['fullname']));
            if (count($nameParts) >= 2) {
                $initials = mb_strtoupper(mb_substr($nameParts[0], 0, 1)) . mb_strtoupper(mb_substr(end($nameParts), 0, 1));
            } else {
                $initials = mb_strtoupper(mb_substr($user['fullname'], 0, 2));
            }
        ?>
        <!-- Vòng tròn Avatar dạng "Thẻ" hơi nghiêng -->
        <div class="w-24 h-24 rounded-2xl bg-[#FDFCF7] text-[#111111] border-4 border-white flex items-center justify-center text-3xl font-extrabold shadow-xl rotate-[-3deg] hover:rotate-0 transition-transform duration-300 shrink-0">
            <?= htmlspecialchars($initials) ?>
        </div>
        
        <div class="pb-1">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#111111] tracking-tight mb-2" style="font-family: var(--font-body);"><?= htmlspecialchars($user['fullname']) ?></h1>
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm text-gray-500 font-medium flex items-center gap-1.5">
                    <i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($user['email']) ?>
                </span>
                
                <?php if ($user['role'] == 1): ?>
                    <span class="bg-[#111111] text-white px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider shadow-sm inline-flex items-center gap-1.5">
                        <i class="bi bi-shield-fill-check text-yellow-400"></i> QUẢN TRỊ VIÊN
                    </span>
                <?php else: ?>
                    <span class="bg-[#111111] text-white px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider shadow-sm inline-flex items-center gap-1.5">
                        <i class="bi bi-star-fill text-yellow-500"></i> THÀNH VIÊN HỆ THỐNG
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bố cục lưới -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Cột Trái (Sidebar & Thống kê) -->
        <div class="lg:col-span-4 flex flex-col gap-6">
            
            <!-- Thẻ Thống kê 3D Hover -->
            <div class="bg-white border border-black/10 rounded-3xl p-6 shadow-2xs hover:shadow-lg hover:-translate-y-1 transition-all duration-300 grid grid-cols-1 gap-4 divide-y divide-black/5">
                
                <div class="flex items-center justify-between pb-1">
                    <div>
                        <div class="text-xs uppercase font-bold tracking-wider text-gray-400 mb-1">Đơn hàng đã đặt</div>
                        <div class="text-2xl font-extrabold text-[#FF4500]"><?= $totalOrders ?> đơn</div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 border border-black/5">
                        <i class="bi bi-box-seam text-lg"></i>
                    </div>
                </div>
                
                <div class="flex items-center justify-between pt-4 pb-1">
                    <div>
                        <div class="text-xs uppercase font-bold tracking-wider text-gray-400 mb-1">Tổng chi tiêu</div>
                        <div class="text-2xl font-extrabold text-[#FF4500]"><?= number_format($stats['total_spent'], 0, ',', '.') ?>₫</div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 border border-black/5">
                        <i class="bi bi-wallet2 text-lg"></i>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4">
                    <div>
                        <div class="text-xs uppercase font-bold tracking-wider text-gray-400 mb-1">Ngày tham gia</div>
                        <div class="text-2xl font-extrabold text-[#FF4500]"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 border border-black/5">
                        <i class="bi bi-calendar-check text-lg"></i>
                    </div>
                </div>

            </div>

            <!-- Menu Điều hướng (Sidebar Navigation) -->
            <div class="flex flex-col gap-2">
                
                <label for="tab-info" onclick="document.getElementById('v-pills-profile-tab').click()" 
                       class="m-0 cursor-pointer <?= $activeTab === 'info' ? 'w-full flex items-center gap-3 px-5 py-3 rounded-2xl bg-[#111111] text-white font-bold text-sm shadow-md translate-x-2 transition-all' : 'w-full flex items-center gap-3 px-5 py-3 rounded-2xl text-gray-600 hover:bg-black/5 hover:text-black hover:translate-x-2 font-semibold text-sm transition-all duration-200' ?>">
                    <i class="bi bi-person-lines-fill text-lg"></i> Thông tin cá nhân
                </label>
                
                <label for="tab-password" onclick="document.getElementById('v-pills-password-tab').click()" 
                       class="m-0 cursor-pointer <?= $activeTab === 'password' ? 'w-full flex items-center gap-3 px-5 py-3 rounded-2xl bg-[#111111] text-white font-bold text-sm shadow-md translate-x-2 transition-all' : 'w-full flex items-center gap-3 px-5 py-3 rounded-2xl text-gray-600 hover:bg-black/5 hover:text-black hover:translate-x-2 font-semibold text-sm transition-all duration-200' ?>">
                    <i class="bi bi-shield-lock text-lg"></i> Đổi mật khẩu
                </label>
                
                <a href="/bookstore/pages/user/my_orders.php" 
                   class="w-full flex items-center gap-3 px-5 py-3 rounded-2xl text-gray-600 hover:bg-black/5 hover:text-black hover:translate-x-2 font-semibold text-sm transition-all duration-200 no-underline">
                    <i class="bi bi-bag-check-fill text-lg"></i> Quản lý đơn hàng
                </a>
                
                <div class="my-2 border-t border-black/10 mx-5"></div>
                
                <a href="/bookstore/pages/auth/logout.php" 
                   class="w-full flex items-center gap-3 px-5 py-3 rounded-2xl text-rose-500 hover:bg-rose-50 hover:text-rose-600 hover:translate-x-2 font-semibold text-sm transition-all duration-200 btn-logout no-underline" data-confirm="Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?">
                    <i class="bi bi-box-arrow-right text-lg"></i> Đăng xuất
                </a>
                
            </div>

            <!-- Dữ liệu tab ẩn dành cho Bootstrap Tab API -->
            <div class="d-none nav flex-column nav-pills profile-nav" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                <button class="nav-link <?= $activeTab === 'info' ? 'active' : '' ?>" id="v-pills-profile-tab" data-bs-toggle="pill" data-bs-target="#v-pills-profile" type="button" role="tab"></button>
                <button class="nav-link <?= $activeTab === 'password' ? 'active' : '' ?>" id="v-pills-password-tab" data-bs-toggle="pill" data-bs-target="#v-pills-password" type="button" role="tab"></button>
            </div>
        </div>

        <!-- Cột Phải (Form & Đơn hàng) -->
        <div class="lg:col-span-8">
            <div class="tab-content" id="v-pills-tabContent">
                
                <!-- TAB THÔNG TIN CÁ NHÂN -->
                <div class="tab-pane fade <?= $activeTab === 'info' ? 'show active' : '' ?>" id="v-pills-profile" role="tabpanel" tabindex="0">
                    
                    <!-- Khung Card Cập nhật Hồ sơ -->
                    <div class="bg-[#FDFCF7] border border-black/10 rounded-3xl p-6 sm:p-8 shadow-[0_10px_30px_rgba(0,0,0,0.03)] mb-8 transition-all duration-300 hover:shadow-[0_20px_40px_rgba(0,0,0,0.06)] hover:border-black/20">
                        
                        <h2 class="text-xl font-extrabold tracking-tight text-[#111111] mb-4 pb-3 border-b border-black/10 flex items-center justify-between">
                            <span class="flex items-center gap-2"><i class="bi bi-person-bounding-box text-xl"></i> CẬP NHẬT HỒ SƠ</span>
                            <div class="w-10 h-10 rounded-full bg-black/5 flex items-center justify-center text-gray-500 hidden sm:flex">
                                <i class="bi bi-sliders text-lg"></i>
                            </div>
                        </h2>
                        
                        <form action="" method="POST">
                            <input type="hidden" name="tab" value="info">
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <!-- Tên đăng nhập (Locked) -->
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2">Tên đăng nhập</label>
                                    <input type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly 
                                           class="w-full bg-black/5 border border-black/5 rounded-2xl px-4 py-2.5 text-sm font-semibold text-gray-400 cursor-not-allowed select-none">
                                    <div class="text-[11px] text-gray-400 mt-1.5 italic">* Tên đăng nhập cố định, không thể thay đổi.</div>
                                </div>
                                
                                <!-- Họ và tên (Editable) -->
                                <div>
                                    <label for="fullname" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2">Họ và tên <span class="text-red-500">*</span></label>
                                    <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($_POST['fullname'] ?? $user['fullname'] ?? '') ?>" required 
                                           class="w-full bg-white border border-black/15 rounded-2xl px-4 py-2.5 text-sm font-semibold text-[#111] transition-all duration-300 shadow-2xs hover:border-black hover:-translate-y-0.5 hover:shadow-md focus:bg-white focus:border-black focus:ring-4 focus:ring-black/5 focus:-translate-y-0.5 outline-none <?= isset($errors['fullname']) ? 'border-red-500' : '' ?>">
                                </div>
                                
                                <!-- Email (Editable) -->
                                <div>
                                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2">Email <span class="text-red-500">*</span></label>
                                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '') ?>" required 
                                           class="w-full bg-white border border-black/15 rounded-2xl px-4 py-2.5 text-sm font-semibold text-[#111] transition-all duration-300 shadow-2xs hover:border-black hover:-translate-y-0.5 hover:shadow-md focus:bg-white focus:border-black focus:ring-4 focus:ring-black/5 focus:-translate-y-0.5 outline-none <?= isset($errors['email']) ? 'border-red-500' : '' ?>">
                                </div>
                                
                                <!-- Số điện thoại (Editable) -->
                                <div>
                                    <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2">Số điện thoại</label>
                                    <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>" 
                                           class="w-full bg-white border border-black/15 rounded-2xl px-4 py-2.5 text-sm font-semibold text-[#111] transition-all duration-300 shadow-2xs hover:border-black hover:-translate-y-0.5 hover:shadow-md focus:bg-white focus:border-black focus:ring-4 focus:ring-black/5 focus:-translate-y-0.5 outline-none <?= isset($errors['phone']) ? 'border-red-500' : '' ?>">
                                </div>
                                
                                <!-- Địa chỉ (Editable) -->
                                <div class="sm:col-span-2">
                                    <label for="address" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2">Địa chỉ giao hàng mặc định</label>
                                    <textarea id="address" name="address" rows="2" 
                                              class="w-full bg-white border border-black/15 rounded-2xl px-4 py-2.5 text-sm font-semibold text-[#111] transition-all duration-300 shadow-2xs hover:border-black hover:-translate-y-0.5 hover:shadow-md focus:bg-white focus:border-black focus:ring-4 focus:ring-black/5 focus:-translate-y-0.5 outline-none resize-none"><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <button type="submit" class="w-full sm:w-auto px-8 bg-[#111111] text-white font-bold py-3 rounded-full text-sm tracking-wide uppercase hover:bg-black/80 hover:shadow-[0_15px_30px_rgba(0,0,0,0.2)] hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 ml-auto mt-4 cursor-pointer relative overflow-hidden group border-none">
                                <span class="relative z-10 flex items-center gap-2"><i class="bi bi-floppy-fill"></i> LƯU THAY ĐỔI</span>
                                <div class="absolute inset-0 w-1/2 h-full bg-white/20 skew-x-[-20deg] -translate-x-full group-hover:translate-x-[300%] transition-transform duration-1000 z-0"></div>
                            </button>
                        </form>
                    </div>

                    <!-- Khối Đơn hàng gần đây -->
                    <div class="bg-white border border-black/10 rounded-3xl p-6 shadow-2xs hover:border-black/20 transition-all">
                        <h3 class="text-xl font-extrabold tracking-tight text-[#111111] mb-3 flex items-center gap-2">
                            <i class="bi bi-clock-history"></i> ĐƠN HÀNG GẦN ĐÂY
                        </h3>
                        
                        <?php if (empty($recentOrders)): ?>
                            <div class="text-center py-8 bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                                <p class="text-gray-400 mb-0 font-medium">Bạn chưa có đơn hàng nào.</p>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-col">
                                <?php foreach ($recentOrders as $order):
                                    $badge = getStatusBadge($order['status']);
                                ?>
                                <div onclick="window.location.href='/bookstore/pages/user/my_orders.php'" class="bg-[#F8F6F0]/60 border border-black/5 rounded-2xl px-4 py-2.5 mb-2 flex items-center justify-between hover:bg-white hover:shadow-lg hover:border-black/30 hover:translate-x-1 transition-all duration-300 cursor-pointer group">
                                    <div>
                                        <p class="font-bold text-[#111111] mb-0.5 group-hover:text-black transition-colors">
                                            Đơn hàng #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                        </p>
                                        <p class="text-gray-500 text-xs font-medium mb-0">
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= !empty($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : '—' ?>
                                        </p>
                                    </div>
                                    <div class="text-end flex flex-col items-end gap-1">
                                        <p class="font-extrabold text-[#FF4500] mb-0 text-base">
                                            <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                        </p>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold shadow-sm <?= $badge['class'] ?>">
                                            <span class="w-1.5 h-1.5 rounded-full bg-current opacity-75 animate-ping"></span>
                                            <?= $badge['label'] ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB ĐỔI MẬT KHẨU -->
                <div class="tab-pane fade <?= $activeTab === 'password' ? 'show active' : '' ?>" id="v-pills-password" role="tabpanel" tabindex="0">
                    <!-- Khung Card Đổi mật khẩu -->
                    <div class="bg-[#FDFCF7] border border-black/10 rounded-3xl p-6 sm:p-8 shadow-[0_10px_30px_rgba(0,0,0,0.03)] mb-8 transition-all duration-300 hover:shadow-[0_20px_40px_rgba(0,0,0,0.06)] hover:border-black/20">
                        
                        <h2 class="text-xl font-extrabold tracking-tight text-[#111111] mb-6 pb-4 border-b border-black/10 flex items-center justify-between">
                            <span class="flex items-center gap-2"><i class="bi bi-shield-lock text-xl"></i> ĐỔI MẬT KHẨU</span>
                            <div class="w-10 h-10 rounded-full bg-black/5 flex items-center justify-center text-gray-500 hidden sm:flex">
                                <i class="bi bi-key text-lg"></i>
                            </div>
                        </h2>
                        
                        <form action="" method="POST">
                            <input type="hidden" name="tab" value="password">
                            
                            <div class="mb-6">
                                <label for="current_password" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2">Mật khẩu hiện tại <span class="text-red-500">*</span></label>
                                <input type="password" id="current_password" name="current_password" required placeholder="Nhập mật khẩu cũ"
                                       class="w-full bg-white border border-black/15 rounded-2xl px-4 py-3.5 text-sm font-semibold text-[#111] transition-all duration-300 shadow-2xs hover:border-black hover:-translate-y-0.5 hover:shadow-md focus:bg-white focus:border-black focus:ring-4 focus:ring-black/5 focus:-translate-y-0.5 outline-none <?= isset($errors['current_password']) ? 'border-red-500' : '' ?>">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label for="new_password" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2">Mật khẩu mới <span class="text-red-500">*</span></label>
                                    <input type="password" id="new_password" name="new_password" required placeholder="Mật khẩu mới"
                                           class="w-full bg-white border border-black/15 rounded-2xl px-4 py-3.5 text-sm font-semibold text-[#111] transition-all duration-300 shadow-2xs hover:border-black hover:-translate-y-0.5 hover:shadow-md focus:bg-white focus:border-black focus:ring-4 focus:ring-black/5 focus:-translate-y-0.5 outline-none <?= isset($errors['new_password']) ? 'border-red-500' : '' ?>">
                                </div>
                                <div>
                                    <label for="confirm_password" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2">Xác nhận mật khẩu <span class="text-red-500">*</span></label>
                                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Nhập lại mật khẩu mới"
                                           class="w-full bg-white border border-black/15 rounded-2xl px-4 py-3.5 text-sm font-semibold text-[#111] transition-all duration-300 shadow-2xs hover:border-black hover:-translate-y-0.5 hover:shadow-md focus:bg-white focus:border-black focus:ring-4 focus:ring-black/5 focus:-translate-y-0.5 outline-none <?= isset($errors['confirm_password']) ? 'border-red-500' : '' ?>">
                                </div>
                            </div>

                            <button type="submit" class="w-full sm:w-auto px-8 bg-[#111111] text-white font-bold py-4 rounded-full text-sm tracking-wide uppercase hover:bg-black/80 hover:shadow-[0_15px_30px_rgba(0,0,0,0.2)] hover:-translate-y-0.5 active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 ml-auto mt-6 cursor-pointer relative overflow-hidden group border-none">
                                <span class="relative z-10 flex items-center gap-2"><i class="bi bi-shield-check"></i> CẬP NHẬT MẬT KHẨU</span>
                                <div class="absolute inset-0 w-1/2 h-full bg-white/20 skew-x-[-20deg] -translate-x-full group-hover:translate-x-[300%] transition-transform duration-1000 z-0"></div>
                            </button>
                        </form>
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