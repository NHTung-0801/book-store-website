<?php
// checkout.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// ── KIỂM TRA ĐĂNG NHẬP ───────────────────────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: /bookstore/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ── KIỂM TRA GIỎ HÀNG KHÔNG ĐƯỢC RỖNG ───────────────────────────────────────
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
$stmtCount->execute([$userId]);
if ($stmtCount->fetchColumn() == 0) {
    // Giỏ rỗng không cho vào checkout
    header('Location: /bookstore/cart.php');
    exit;
}

// ── QUERY THÔNG TIN USER ĐỂ TỰ ĐIỀN FORM ────────────────────────────────────
$stmtUser = $pdo->prepare("
    SELECT fullname, email, phone, address
    FROM   users
    WHERE  id = ?
    LIMIT  1
");
$user = $stmtUser->execute([$userId]) ? $stmtUser->fetch() : null;

// CSRF token (tạo nếu chưa có)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── QUERY GIỎ HÀNG ĐỂ HIỂN THỊ TÓM TẮT ĐƠN HÀNG ────────────────────────────
$stmtCart = $pdo->prepare("
    SELECT  c.book_id,
            c.quantity,
            b.title,
            b.price,
            b.image,
            b.stock_quantity
    FROM    cart c
    JOIN    books b ON c.book_id = b.id
    WHERE   c.user_id = ?
");
$stmtCart->execute([$userId]);
$cartItems = $stmtCart->fetchAll();

// Tính tổng tiền của toàn bộ giỏ hàng
$totalPrice = 0;
foreach ($cartItems as $item) {
    $totalPrice += $item['price'] * $item['quantity'];
}

$errors  = [];
$success = false;

// ── XỬ LÝ ĐẶT HÀNG KHI SUBMIT FORM ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');

    // CSRF token validation
    $postedToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !is_string($postedToken) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $errors['csrf'] = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    }

    // Validate dữ liệu người nhận
    if (empty($fullname)) {
        $errors['fullname'] = 'Họ và tên người nhận không được để trống.';
    }
    if (empty($phone)) {
        $errors['phone'] = 'Số điện thoại không được để trống.';
    }
    if (empty($address)) {
        $errors['address'] = 'Địa chỉ giao hàng không được để trống.';
    }

    if (empty($errors)) {
        try {
            // Sử dụng Transaction để đảm bảo an toàn dữ liệu tuyệt đối
            $pdo->beginTransaction();

            // 1. Chèn dữ liệu vào bảng orders
            $stmtOrder = $pdo->prepare("
                INSERT INTO orders (user_id, fullname, phone, address, total_price, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmtOrder->execute([$userId, $fullname, $phone, $address, $totalPrice]);
            $orderId = $pdo->lastInsertId();

            // 2. Chèn dữ liệu vào order_details và cập nhật lại kho sách
            foreach ($cartItems as $item) {
                $stmtDetail = $pdo->prepare("
                    INSERT INTO order_details (order_id, book_id, quantity, price)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtDetail->execute([$orderId, $item['book_id'], $item['quantity'], $item['price']]);

                // Trừ số lượng tồn kho của sách
                $newStock = $item['stock_quantity'] - $item['quantity'];
                $stmtUpdateStock = $pdo->prepare("
                    UPDATE books SET stock_quantity = ? WHERE id = ?
                ");
                $stmtUpdateStock->execute([$newStock, $item['book_id']]);
            }

            // 3. Xóa sạch giỏ hàng của user sau khi đã đặt hàng thành công
            $stmtClear = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmtClear->execute([$userId]);

            $pdo->commit();
            $success = true;

        } catch (Exception $e) {
            $pdo->rollBack();
            // Log full exception server-side and show generic message to user
            error_log('[checkout] Order processing error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $errors['submit'] = 'Có lỗi xảy ra trong quá trình xử lý đơn hàng. Vui lòng thử lại sau.';
        }
    }
}
?>

<main class="container my-5">

    <?php if ($success): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Đặt hàng thành công!',
                    text: 'Đơn hàng của bạn đã được ghi nhận và đang chờ xử lý.',
                    confirmButtonColor: '#ffc107',
                    confirmButtonText: 'Xem đơn hàng của tôi'
                }).then((result) => {
                    // Chuyển hướng sang trang lịch sử đơn hàng của khách
                    window.location.href = '/bookstore/my_orders.php';
                });
            });
        </script>
    <?php endif; ?>

    <?php if (isset($errors['submit'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi đặt hàng',
                    text: <?= json_encode($errors['submit']) ?>,
                    confirmButtonColor: '#d33'
                });
            });
        </script>
    <?php endif; ?>

    <div class="mb-4">
        <h2 class="fw-bold"><i class="bi bi-credit-card me-2 text-warning"></i>Thanh toán đơn hàng</h2>
        <p class="text-muted">Vui lòng kiểm tra lại thông tin và xác nhận đặt hàng.</p>
    </div>

    <?php if (!$success): ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">
                            <i class="bi bi-geo-alt me-2 text-warning"></i>Thông tin giao hàng
                        </h5>

                        <form id="checkoutForm" method="POST" action="" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <div class="mb-3">
                                <label for="fullname" class="form-label fw-semibold">Họ và tên người nhận <span class="text-danger">*</span></label>
                                <input type="text" id="fullname" name="fullname" 
                                       class="form-control <?= isset($errors['fullname']) ? 'is-invalid' : '' ?>" 
                                       placeholder="Nhập đầy đủ họ tên người nhận"
                                       value="<?= htmlspecialchars($_POST['fullname'] ?? $user['fullname'] ?? '') ?>">
                                <?php if (isset($errors['fullname'])): ?>
                                    <div class="invalid-feedback"><i class="bi bi-exclamation-circle me-1"></i><?= $errors['fullname'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label fw-semibold">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="text" id="phone" name="phone" 
                                       class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" 
                                       placeholder="Nhập số điện thoại liên hệ"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><i class="bi bi-exclamation-circle me-1"></i><?= $errors['phone'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label for="address" class="form-label fw-semibold">Địa chỉ nhận hàng <span class="text-danger">*</span></label>
                                <textarea id="address" name="address" rows="3" 
                                          class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" 
                                          placeholder="Ghi rõ số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố..."><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
                                <?php if (isset($errors['address'])): ?>
                                    <div class="invalid-feedback"><i class="bi bi-exclamation-circle me-1"></i><?= $errors['address'] ?></div>
                                <?php endif; ?>
                            </div>

                            <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                                <i class="bi bi-cash-coin me-2 text-warning"></i>Phương thức thanh toán
                            </h5>
                            <div class="form-check p-3 rounded-3 border bg-light mb-4 d-flex align-items-center gap-2">
                                <input class="form-check-input ms-1 shadow-none" type="radio" name="payment_method" id="cod" checked>
                                <label class="form-check-label fw-semibold text-dark" for="cod">
                                    <i class="bi bi-truck me-1 text-secondary"></i> Thanh toán khi nhận hàng (COD)
                                </label>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning fw-bold py-2.5 fs-5 shadow-sm">
                                    <i class="bi bi-bag-check-fill me-2"></i>Xác nhận đặt hàng
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm border-0 sticky-lg-top" style="top: 2rem; z-index: 10;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                            <i class="bi bi-journal-text me-2 text-warning"></i>Tóm tắt đơn hàng
                        </h5>

                        <div class="overflow-y-auto pe-1" style="max-height: 280px;">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($cartItems as $item): ?>
                                    <li class="list-group-item px-0 py-3 border-bottom">
                                        <div class="d-flex gap-3 align-items-center">
                                            <img src="/bookstore/assets/images/books/<?= htmlspecialchars($item['image'] ?: 'placeholder.png') ?>" 
                                                 alt="Book cover" class="rounded border shadow-sm flex-shrink-0" 
                                                 style="width: 44px; height: 60px; object-fit: cover;">
                                            <div class="flex-grow-1 overflow-hidden">
                                                <p class="fw-bold small mb-0 text-truncate text-dark"><?= htmlspecialchars($item['title']) ?></p>
                                                <p class="text-muted small mb-0"><?= number_format($item['price'], 0, ',', '.') ?>₫ × <?= $item['quantity'] ?></p>
                                            </div>
                                            <div class="text-end flex-shrink-0 fw-semibold text-dark">
                                                <?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>₫
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="bg-light p-3 rounded-3 border mt-4">
                            <div class="d-flex justify-content-between text-muted small mb-2">
                                <span>Tạm tính</span>
                                <span><?= number_format($totalPrice, 0, ',', '.') ?>₫</span>
                            </div>
                            <div class="d-flex justify-content-between text-muted small mb-3">
                                <span>Phí vận chuyển</span>
                                <span class="text-success fw-semibold">Miễn phí</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold fs-6">Tổng cộng</span>
                                <span class="fw-bold fs-5 text-danger">
                                    <?= number_format($totalPrice, 0, ',', '.') ?>₫
                                </span>
                            </div>
                        </div>

                    </div></div><div class="mt-3 text-center">
                    <a href="/bookstore/cart.php" class="text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại giỏ hàng
                    </a>
                </div>
            </div>
        </div><?php endif; ?>

</main>

<script>
// Prevent double submit on checkout form
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('checkoutForm');
    if (!form) return;
    form.addEventListener('submit', function() {
        var btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = 'Đang xử lý...';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>