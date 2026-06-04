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
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

// ── QUERY GIỎ HÀNG ĐỂ HIỂN THỊ TÓM TẮT ĐƠN HÀNG ────────────────────────────
$stmtCart = $pdo->prepare("
    SELECT  c.book_id,
            c.quantity,
            b.title,
            b.price,
            b.image,
            b.stock_quantity,
            (b.price * c.quantity) AS subtotal
    FROM    cart c
    JOIN    books b ON c.book_id = b.id
    WHERE   c.user_id = ?
    ORDER BY b.title ASC
");
$stmtCart->execute([$userId]);
$cartItems = $stmtCart->fetchAll();

// Tính tổng tiền hiển thị trước khi đặt
$totalPrice = array_sum(array_column($cartItems, 'subtotal'));

// Biến lưu lỗi / kết quả xử lý
$errors     = [];
$successMsg = '';
$orderId    = null;

// ── XỬ LÝ POST: ĐẶT HÀNG ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. LẤY VÀ LÀM SẠCH DỮ LIỆU FORM ─────────────────────────────────────
    $fullname = trim($_POST['fullname'] ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $address  = trim($_POST['address']  ?? '');
    $email    = trim($_POST['email']    ?? '');

    // 2. VALIDATE ────────────────────────────────────────────────────────────
    if (empty($fullname)) {
        $errors['fullname'] = 'Vui lòng nhập họ và tên.';
    }
    if (empty($phone)) {
        $errors['phone'] = 'Vui lòng nhập số điện thoại.';
    } elseif (!preg_match('/^(0|\+84)[0-9]{8,10}$/', $phone)) {
        $errors['phone'] = 'Số điện thoại không hợp lệ.';
    }
    if (empty($address)) {
        $errors['address'] = 'Vui lòng nhập địa chỉ giao hàng.';
    }
    if (empty($email)) {
        $errors['email'] = 'Vui lòng nhập email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ.';
    }

    // 3. XỬ LÝ TRANSACTION NẾU KHÔNG CÓ LỖI ─────────────────────────────────
    if (empty($errors)) {
        try {
            // Bắt đầu transaction — đảm bảo toàn bộ các bước thành công hoặc rollback hết
            $pdo->beginTransaction();

            // ── BƯỚC A: TÍNH TỔNG TIỀN TỪ CART TRONG TRANSACTION ────────────
            // Query lại trong transaction để đảm bảo dữ liệu nhất quán (tránh race condition)
            $stmtTotal = $pdo->prepare("
                SELECT SUM(b.price * c.quantity) AS total
                FROM   cart c
                JOIN   books b ON c.book_id = b.id
                WHERE  c.user_id = ?
            ");
            $stmtTotal->execute([$userId]);
            $totalInTransaction = (float) $stmtTotal->fetchColumn();

            if ($totalInTransaction <= 0) {
                throw new Exception('Giỏ hàng không hợp lệ.');
            }

            // ── BƯỚC B: INSERT VÀO BẢNG ORDERS ──────────────────────────────
            $stmtOrder = $pdo->prepare("
                INSERT INTO orders (user_id, fullname, phone, address, total_price, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmtOrder->execute([$userId, $fullname, $phone, $address, $totalInTransaction]);

            // Lấy order_id vừa tạo để dùng cho order_details
            $orderId = $pdo->lastInsertId();

            // ── BƯỚC C: SELECT GIỎ HÀNG VÀ INSERT VÀO ORDER_DETAILS ─────────
            $stmtItems = $pdo->prepare("
                SELECT  c.book_id,
                        c.quantity,
                        b.price,
                        b.stock_quantity
                FROM    cart c
                JOIN    books b ON c.book_id = b.id
                WHERE   c.user_id = ?
            ");
            $stmtItems->execute([$userId]);
            $orderItems = $stmtItems->fetchAll();

            // Chuẩn bị sẵn các statement để tái sử dụng trong vòng lặp (hiệu quả hơn)
            $stmtDetail = $pdo->prepare("
                INSERT INTO order_details (order_id, book_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            $stmtUpdateStock = $pdo->prepare("
                UPDATE books
                SET    stock_quantity = stock_quantity - ?
                WHERE  id = ?
                  AND  stock_quantity >= ?
            ");

            foreach ($orderItems as $item) {

                // Kiểm tra tồn kho lần cuối trước khi insert (có thể đã thay đổi)
                if ($item['stock_quantity'] < $item['quantity']) {
                    throw new Exception(
                        "Sách '{$item['book_id']}' không đủ số lượng trong kho. Vui lòng cập nhật giỏ hàng."
                    );
                }

                // Insert chi tiết đơn hàng — lưu price tại thời điểm mua (không bị ảnh hưởng nếu giá thay đổi sau)
                $stmtDetail->execute([
                    $orderId,
                    $item['book_id'],
                    $item['quantity'],
                    $item['price']
                ]);

                // ── BƯỚC D: TRỪ stock_quantity TRONG BẢNG BOOKS ─────────────
                // Điều kiện AND stock_quantity >= quantity đảm bảo không trừ âm
                $stmtUpdateStock->execute([
                    $item['quantity'],
                    $item['book_id'],
                    $item['quantity']
                ]);

                // Kiểm tra rowCount — nếu = 0 nghĩa là điều kiện stock không thỏa
                if ($stmtUpdateStock->rowCount() === 0) {
                    throw new Exception('Cập nhật tồn kho thất bại. Vui lòng thử lại.');
                }
            }

            // ── BƯỚC E: XÓA TOÀN BỘ GIỎ HÀNG CỦA USER NÀY ─────────────────
            $stmtClearCart = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmtClearCart->execute([$userId]);

            // ── BƯỚC F: COMMIT — XÁC NHẬN TOÀN BỘ TRANSACTION ───────────────
            $pdo->commit();

            // Đặt hàng thành công — reset cartItems để ẩn tóm tắt đơn hàng cũ
            $successMsg = 'Đặt hàng thành công!';
            $cartItems  = [];
            $totalPrice = 0;

        } catch (Exception $e) {
            // Có bất kỳ lỗi nào → rollback toàn bộ, không có gì được lưu vào DB
            $pdo->rollBack();
            $errors['transaction'] = 'Đặt hàng thất bại: ' . $e->getMessage();
            $orderId = null;
        }
    }
}
?>

<!-- ========== NỘI DUNG TRANG THANH TOÁN ========== -->
<main class="container my-5">

    <!-- Tiêu đề -->
    <h3 class="fw-bold mb-4">
        <i class="bi bi-credit-card me-2 text-warning"></i>Thanh toán
    </h3>

    <!-- Thanh bước (Step indicator) -->
    <div class="checkout-steps d-flex align-items-center gap-2 mb-5">
        <span class="step-done">
            <i class="bi bi-cart-check me-1"></i>Giỏ hàng
        </span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="step-active <?= $successMsg ? 'step-done' : '' ?>">
            <i class="bi bi-pencil-square me-1"></i>Thông tin giao hàng
        </span>
        <i class="bi bi-chevron-right text-muted"></i>
        <span class="<?= $successMsg ? 'step-active' : 'step-inactive' ?>">
            <i class="bi bi-check-circle me-1"></i>Xác nhận
        </span>
    </div>

    <?php if ($successMsg): ?>
        <!-- ══ THÔNG BÁO ĐẶT HÀNG THÀNH CÔNG ══ -->
        <div class="text-center py-5">
            <div class="success-checkmark mb-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
            </div>
            <h4 class="fw-bold text-success mb-2">Đặt hàng thành công!</h4>
            <p class="text-muted mb-1">
                Cảm ơn bạn đã mua hàng tại <strong>Book Store</strong>.
            </p>
            <p class="text-muted mb-4">
                Mã đơn hàng của bạn: <strong class="text-dark">#<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?></strong>
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="/bookstore/my_orders.php" class="btn btn-warning fw-bold px-4">
                    <i class="bi bi-bag-check me-2"></i>Xem đơn hàng của tôi
                </a>
                <a href="/bookstore/index.php" class="btn btn-outline-secondary px-4">
                    <i class="bi bi-house me-2"></i>Về trang chủ
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- ══ LAYOUT 2 CỘT: FORM + TÓM TẮT ══ -->
        <div class="row g-4">

            <!-- CỘT TRÁI: FORM THÔNG TIN GIAO HÀNG -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-dark text-white fw-bold py-3">
                        <i class="bi bi-truck me-2 text-warning"></i>Thông tin giao hàng
                    </div>
                    <div class="card-body p-4">

                        <!-- Lỗi transaction -->
                        <?php if (isset($errors['transaction'])): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?= htmlspecialchars($errors['transaction']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" novalidate>

                            <!-- Họ và tên -->
                            <div class="mb-3">
                                <label for="fullname" class="form-label fw-semibold">
                                    Họ và tên <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="fullname"
                                    name="fullname"
                                    class="form-control <?= isset($errors['fullname']) ? 'is-invalid' : '' ?>"
                                    value="<?= htmlspecialchars($_POST['fullname'] ?? $user['fullname'] ?? '') ?>"
                                    placeholder="Nguyễn Văn A"
                                >
                                <?php if (isset($errors['fullname'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        <?= htmlspecialchars($errors['fullname']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold">
                                    Email <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                    value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '') ?>"
                                    placeholder="example@email.com"
                                >
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        <?= htmlspecialchars($errors['email']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Số điện thoại -->
                            <div class="mb-3">
                                <label for="phone" class="form-label fw-semibold">
                                    Số điện thoại <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                    value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>"
                                    placeholder="0901 234 567"
                                >
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        <?= htmlspecialchars($errors['phone']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Địa chỉ giao hàng -->
                            <div class="mb-4">
                                <label for="address" class="form-label fw-semibold">
                                    Địa chỉ giao hàng <span class="text-danger">*</span>
                                </label>
                                <textarea
                                    id="address"
                                    name="address"
                                    rows="3"
                                    class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                                    placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố"
                                ><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? '') ?></textarea>
                                <?php if (isset($errors['address'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        <?= htmlspecialchars($errors['address']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Phương thức thanh toán (COD duy nhất) -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Phương thức thanh toán</label>
                                <div class="form-check border rounded-3 p-3 bg-light">
                                    <input class="form-check-input" type="radio"
                                           name="payment" id="cod" value="cod" checked>
                                    <label class="form-check-label fw-semibold ms-2" for="cod">
                                        <i class="bi bi-cash-coin text-success me-2"></i>
                                        Thanh toán khi nhận hàng (COD)
                                    </label>
                                </div>
                            </div>

                            <!-- Nút đặt hàng -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning btn-lg fw-bold py-3">
                                    <i class="bi bi-bag-check me-2"></i>
                                    Đặt hàng —
                                    <?= number_format($totalPrice, 0, ',', '.') ?>₫
                                </button>
                            </div>

                        </form>
                    </div><!-- /.card-body -->
                </div><!-- /.card -->
            </div>

            <!-- CỘT PHẢI: TÓM TẮT ĐƠN HÀNG -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm sticky-top" style="top: 80px;">
                    <div class="card-header bg-dark text-white fw-bold py-3">
                        <i class="bi bi-receipt me-2 text-warning"></i>
                        Đơn hàng (<?= count($cartItems) ?> sản phẩm)
                    </div>
                    <div class="card-body p-0">

                        <!-- Danh sách sách trong đơn -->
                        <ul class="list-group list-group-flush">
                            <?php foreach ($cartItems as $item): ?>
                            <?php
                                $imgPath = '/bookstore/assets/images/books/' . $item['image'];
                                $imgSrc  = (!empty($item['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
                                            ? $imgPath
                                            : '/bookstore/assets/images/books/placeholder.png';
                            ?>
                            <li class="list-group-item px-4 py-3">
                                <div class="d-flex gap-3 align-items-center">
                                    <!-- Ảnh nhỏ + badge số lượng -->
                                    <div class="position-relative flex-shrink-0">
                                        <img src="<?= htmlspecialchars($imgSrc) ?>"
                                             alt="<?= htmlspecialchars($item['title']) ?>"
                                             class="rounded" style="width:48px;height:64px;object-fit:cover;">
                                        <span class="position-absolute top-0 start-100 translate-middle
                                                     badge rounded-pill bg-warning text-dark">
                                            <?= $item['quantity'] ?>
                                        </span>
                                    </div>
                                    <!-- Tên + thành tiền -->
                                    <div class="flex-grow-1 overflow-hidden">
                                        <p class="fw-semibold small mb-0 text-truncate">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </p>
                                        <p class="text-muted x-small mb-0" style="font-size:.8rem;">
                                            <?= number_format($item['price'], 0, ',', '.') ?>₫
                                            × <?= $item['quantity'] ?>
                                        </p>
                                    </div>
                                    <span class="fw-bold text-danger small flex-shrink-0">
                                        <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                    </span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Tổng cộng -->
                        <div class="px-4 py-3 border-top">
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

                    </div><!-- /.card-body -->
                </div><!-- /.card -->

                <!-- Link quay lại giỏ hàng -->
                <div class="mt-3 text-center">
                    <a href="/bookstore/cart.php"
                       class="text-muted small text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Quay lại giỏ hàng
                    </a>
                </div>

            </div>
        </div><!-- /.row -->
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>