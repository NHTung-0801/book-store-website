<?php
// checkout.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// ── 1. KIỂM TRA ĐĂNG NHẬP ───────────────────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: /bookstore/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ── 2. XỬ LÝ LƯU ĐƠN HÀNG KHI NHẤN "XÁC NHẬN" (POST REQUEST) ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $note     = trim($_POST['note'] ?? ''); // Có thể dùng mở rộng sau này
    
    // Kiểm tra dữ liệu đầu vào
    if (empty($fullname) || empty($phone) || empty($address)) {
        $_SESSION['error'] = "Vui lòng nhập đầy đủ thông tin giao hàng.";
        header('Location: /bookstore/checkout.php');
        exit;
    }

    try {
        // Bắt đầu Transaction để bảo vệ toàn vẹn dữ liệu
        $pdo->beginTransaction();

        // 2.1. Đọc sản phẩm từ giỏ hàng
        $stmtCart = $pdo->prepare("
            SELECT c.book_id, c.quantity, b.price, b.stock_quantity 
            FROM cart c 
            JOIN books b ON c.book_id = b.id 
            WHERE c.user_id = ?
        ");
        $stmtCart->execute([$userId]);
        $items = $stmtCart->fetchAll();

        if (empty($items)) {
            throw new Exception("Giỏ hàng của bạn đang trống.");
        }

        $calcTotal = 0;
        foreach ($items as $item) {
            if ($item['quantity'] > $item['stock_quantity']) {
                throw new Exception("Sách có ID {$item['book_id']} không đủ số lượng trong kho.");
            }
            $calcTotal += ($item['price'] * $item['quantity']);
        }

        // 2.2. Tạo đơn hàng vào bảng orders (Mặc định status: pending) - Đã thêm trường note
        $stmtOrder = $pdo->prepare("
            INSERT INTO orders (user_id, fullname, phone, address, note, total_price, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmtOrder->execute([$userId, $fullname, $phone, $address, $note, $calcTotal]);
        $orderId = $pdo->lastInsertId();

        // 2.3. Insert vào order_details và trừ tồn kho (books)
        $stmtDetail = $pdo->prepare("INSERT INTO order_details (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmtUpdateStock = $pdo->prepare("UPDATE books SET stock_quantity = stock_quantity - ? WHERE id = ?");

        foreach ($items as $item) {
            $stmtDetail->execute([$orderId, $item['book_id'], $item['quantity'], $item['price']]);
            $stmtUpdateStock->execute([$item['quantity'], $item['book_id']]);
        }

        // 2.4. Xóa sạch giỏ hàng của user sau khi mua xong
        $stmtClearCart = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmtClearCart->execute([$userId]);

        // Xác nhận Transaction thành công
        $pdo->commit();

        // Chuyển hướng sang trang quản lý đơn hàng
        $_SESSION['success'] = "Đặt hàng thành công!";
        header('Location: /bookstore/my_orders.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack(); // Hoàn tác nếu có bất kỳ lỗi nào xảy ra
        $_SESSION['error'] = "Lỗi khi xử lý đơn hàng: " . $e->getMessage();
        header('Location: /bookstore/checkout.php');
        exit;
    }
}

// ── 3. QUERY DỮ LIỆU ĐỂ HIỂN THỊ GIAO DIỆN THANH TOÁN ───────────────────────
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
$stmtCount->execute([$userId]);
if ($stmtCount->fetchColumn() == 0) {
    header('Location: /bookstore/cart.php');
    exit;
}

$stmtUser = $pdo->prepare("SELECT fullname, email, phone, address FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$stmtCartView = $pdo->prepare("
    SELECT  c.quantity, b.title, b.price, (b.price * c.quantity) AS subtotal
    FROM    cart c
    JOIN    books b ON c.book_id = b.id
    WHERE   c.user_id = ?
");
$stmtCartView->execute([$userId]);
$cartItems = $stmtCartView->fetchAll();

$totalPrice = array_sum(array_column($cartItems, 'subtotal'));
?>

<main class="container my-5">

    <div class="page-header-custom mb-4">
        <h3 class="title">
            <i class="bi bi-credit-card"></i> Thanh toán đơn hàng
        </h3>
        <span class="badge-custom">
            <i class="bi bi-shield-lock-fill me-1"></i> Giao dịch bảo mật 100%
        </span>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger d-flex align-items-center shadow-sm rounded-3 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
            <div><?= htmlspecialchars($_SESSION['error']) ?></div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row g-4">
        
        <div class="col-lg-7">
            <form id="checkoutForm" action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-top: 4px solid #ffc107 !important;">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bolder text-dark mb-0" style="color: #0f3460;">
                            <i class="bi bi-geo-alt-fill text-warning me-2 fs-4"></i>Thông tin nhận hàng
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-3">
                        
                        <div class="mb-3">
                            <label for="fullname" class="form-label fw-semibold text-secondary small text-uppercase">Họ và tên người nhận <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control border-secondary-subtle border-start-0 bg-light shadow-none px-0" id="fullname" name="fullname"
                                       value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required
                                       placeholder="Nhập họ và tên...">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label fw-semibold text-secondary small text-uppercase">Số điện thoại liên hệ <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="tel" class="form-control border-secondary-subtle border-start-0 bg-light shadow-none px-0" id="phone" name="phone"
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required
                                       placeholder="Ví dụ: 0912345678">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="address" class="form-label fw-semibold text-secondary small text-uppercase">Địa chỉ giao hàng chi tiết <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted align-items-start pt-3"><i class="bi bi-map"></i></span>
                                <textarea class="form-control border-secondary-subtle border-start-0 bg-light shadow-none px-0" id="address" name="address" rows="3" required
                                          placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố..."><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <label for="note" class="form-label fw-semibold text-secondary small text-uppercase">Ghi chú đơn hàng (Tùy chọn)</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted align-items-start pt-3"><i class="bi bi-pencil-square"></i></span>
                                <textarea class="form-control border-secondary-subtle border-start-0 bg-light shadow-none px-0" id="note" name="note" rows="2"
                                          placeholder="Giao vào giờ hành chính, gọi trước khi giao..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="border-top: 4px solid #0dcaf0 !important;">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bolder text-dark mb-0" style="color: #0f3460;">
                            <i class="bi bi-wallet2 text-info me-2 fs-4"></i>Phương thức thanh toán
                        </h5>
                    </div>
                    <div class="card-body p-4 pt-2">
                        <div class="form-check p-3 rounded-3 border bg-info-subtle border-info-subtle d-flex align-items-center">
                            <input class="form-check-input fs-4 mt-0 ms-2 me-3 shadow-none border-info" type="radio" name="payment_method" id="cod" value="cod" checked>
                            <label class="form-check-label text-dark d-flex flex-column" for="cod" style="cursor: pointer;">
                                <span class="fw-bold" style="font-size: 1.05rem;">Thanh toán khi nhận hàng (COD)</span>
                                <span class="text-muted small fw-normal mt-1">Bạn sẽ thanh toán bằng tiền mặt khi shipper giao sách tới.</span>
                            </label>
                        </div>
                    </div>
                </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden sticky-top" style="top: 100px;">
                <div class="card-header bg-dark text-white fw-bold py-3 border-0">
                    <i class="bi bi-receipt me-2 text-warning"></i>Chi tiết đơn hàng
                </div>
                
                <div class="card-body p-0">
                    <div class="p-4 bg-light">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-3">Sản phẩm (<?= count($cartItems) ?>)</h6>
                        <ul class="list-group list-group-flush rounded-3 shadow-sm border border-secondary-subtle">
                            <?php foreach ($cartItems as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-white p-3 border-secondary-subtle">
                                <div class="me-3 pe-3 border-end border-secondary-subtle" style="width: 70%;">
                                    <h6 class="mb-1 text-truncate fw-bold text-dark" style="font-size: 0.95rem; color: #0f3460;"><?= htmlspecialchars($item['title']) ?></h6>
                                    <small class="text-muted fw-medium"><?= number_format($item['price'], 0, ',', '.') ?>₫ × <span class="badge bg-warning text-dark rounded-pill shadow-sm"><?= $item['quantity'] ?></span></small>
                                </div>
                                <span class="fw-bold text-danger text-end" style="width: 30%;">
                                    <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="p-4 bg-white border-top border-secondary-subtle">
                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span>Tạm tính</span>
                            <span class="fw-medium text-dark"><?= number_format($totalPrice, 0, ',', '.') ?>₫</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small mb-3 pb-3" style="border-bottom: 1px dashed #dee2e6;">
                            <span>Phí vận chuyển</span>
                            <span class="text-success fw-bold bg-success-subtle px-2 py-1 rounded-2">Miễn phí</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="fw-bolder fs-5 text-dark">Tổng cộng</span>
                            <span class="fw-bolder text-danger" style="font-size: 1.6rem;">
                                <?= number_format($totalPrice, 0, ',', '.') ?>₫
                            </span>
                        </div>

                        <div class="d-grid gap-3">
                            <button type="submit" class="btn btn-warning btn-lg fw-bold py-3 shadow-sm rounded-pill checkout-btn d-flex align-items-center justify-content-center border-0 text-dark">
                                <i class="bi bi-check2-circle fs-4 me-2"></i> XÁC NHẬN ĐẶT HÀNG
                            </button>
                            
                            <a href="/bookstore/cart.php" class="btn btn-light btn-lg border border-secondary-subtle fw-semibold py-2 shadow-sm rounded-pill d-flex align-items-center justify-content-center text-muted transition-hover">
                                <i class="bi bi-arrow-left me-2"></i>Quay lại Giỏ hàng
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        
        </form>
    </div>

</main>

<style>
    .checkout-btn { transition: all 0.3s ease; }
    .checkout-btn:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 .5rem 1rem rgba(255, 193, 7, 0.4)!important; 
        background-color: #ffca2c; 
    }
    .transition-hover { transition: all 0.2s ease; }
    .transition-hover:hover { background-color: #f8f9fa; color: #0f3460 !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('checkoutForm');
    if (!form) return;
    form.addEventListener('submit', function() {
        var btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Đang xử lý...';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>