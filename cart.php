<?php
// cart.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// ── KIỂM TRA ĐĂNG NHẬP ───────────────────────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: /bookstore/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ── TRUY VẤN GIỎ HÀNG, JOIN BOOKS ───────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT  c.book_id,
            c.quantity,
            b.title,
            b.author,
            b.price,
            b.image,
            b.stock_quantity,
            (b.price * c.quantity) AS subtotal
    FROM    cart c
    JOIN    books b ON c.book_id = b.id
    WHERE   c.user_id = ?
    ORDER BY b.title ASC
");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

// ── TÍNH TỔNG TIỀN ────────────────────────────────────────────────────────────
$totalPrice = array_sum(array_column($cartItems, 'subtotal'));

// ── ĐỌC THÔNG BÁO STATUS TỪ REDIRECT ─────────────────────────────────────────
$status = $_GET['status'] ?? '';
?>

<!-- ========== NỘI DUNG TRANG GIỎ HÀNG ========== -->
<main class="container my-5">

    <!-- Tiêu đề trang -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <h3 class="fw-bold mb-0">
            <i class="bi bi-cart3 me-2 text-warning"></i>Giỏ hàng của tôi
        </h3>
        <?php if (!empty($cartItems)): ?>
            <span class="badge bg-warning text-dark rounded-pill fs-6">
                <?= count($cartItems) ?> sản phẩm
            </span>
        <?php endif; ?>
    </div>

    <!-- Thông báo trạng thái -->
    <?php if ($status === 'added'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-cart-check-fill me-2"></i>
            Đã thêm sách vào giỏ hàng thành công!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($status === 'updated'): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <i class="bi bi-arrow-repeat me-2"></i>
            Đã cập nhật số lượng.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($status === 'removed'): ?>
        <div class="alert alert-secondary alert-dismissible fade show">
            <i class="bi bi-trash me-2"></i>
            Đã xóa sách khỏi giỏ hàng.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($status === 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Có lỗi xảy ra, vui lòng thử lại.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
        <!-- Trạng thái giỏ hàng rỗng -->
        <div class="text-center py-5">
            <i class="bi bi-cart-x text-muted" style="font-size: 5rem;"></i>
            <h5 class="text-muted mt-3">Giỏ hàng của bạn đang trống</h5>
            <p class="text-muted small">Hãy chọn thêm sách yêu thích để bắt đầu!</p>
            <a href="/bookstore/index.php" class="btn btn-warning fw-bold px-4 mt-2">
                <i class="bi bi-book me-2"></i>Khám phá sách ngay
            </a>
        </div>

    <?php else: ?>
        <div class="row g-4">

            <!-- ══ CỘT TRÁI: BẢNG GIỎ HÀNG ══ -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="ps-4" style="width: 80px;">Ảnh</th>
                                        <th>Tên sách</th>
                                        <th class="text-center" style="width: 130px;">Số lượng</th>
                                        <th class="text-end" style="width: 120px;">Đơn giá</th>
                                        <th class="text-end" style="width: 130px;">Thành tiền</th>
                                        <th class="text-center" style="width: 70px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                <?php
                                    $imgPath = '/bookstore/assets/images/books/' . $item['image'];
                                    $imgSrc  = (!empty($item['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
                                                ? $imgPath
                                                : '/bookstore/assets/images/books/placeholder.png';
                                ?>
                                <tr>
                                    <!-- Ảnh bìa sách -->
                                    <td class="ps-4">
                                        <a href="/bookstore/product.php?id=<?= $item['book_id'] ?>">
                                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                                 alt="<?= htmlspecialchars($item['title']) ?>"
                                                 class="cart-book-img rounded">
                                        </a>
                                    </td>

                                    <!-- Tên sách + tác giả -->
                                    <td>
                                        <a href="/bookstore/product.php?id=<?= $item['book_id'] ?>"
                                           class="fw-semibold text-dark text-decoration-none cart-title">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </a>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-person me-1"></i>
                                            <?= htmlspecialchars($item['author']) ?>
                                        </p>
                                        <!-- Cảnh báo nếu số lượng trong giỏ vượt tồn kho -->
                                        <?php if ($item['quantity'] > $item['stock_quantity']): ?>
                                            <span class="badge bg-danger-subtle text-danger small">
                                                <i class="bi bi-exclamation-circle me-1"></i>
                                                Chỉ còn <?= $item['stock_quantity'] ?> cuốn
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Form cập nhật số lượng -->
                                    <td class="text-center">
                                        <form method="POST"
                                              action="/bookstore/actions/update_cart.php"
                                              class="d-flex align-items-center justify-content-center gap-1 update-form">
                                            <input type="hidden" name="book_id"
                                                   value="<?= $item['book_id'] ?>">

                                            <!-- Nút giảm -->
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary qty-btn"
                                                    data-action="decrease"
                                                    aria-label="Giảm">
                                                <i class="bi bi-dash"></i>
                                            </button>

                                            <!-- Input số lượng — blur tự submit form -->
                                            <input type="number"
                                                   name="quantity"
                                                   class="form-control form-control-sm text-center fw-bold qty-input cart-qty-input"
                                                   value="<?= $item['quantity'] ?>"
                                                   min="1"
                                                   max="<?= $item['stock_quantity'] ?>">

                                            <!-- Nút tăng -->
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary qty-btn"
                                                    data-action="increase"
                                                    data-max="<?= $item['stock_quantity'] ?>"
                                                    aria-label="Tăng">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </form>
                                    </td>

                                    <!-- Đơn giá -->
                                    <td class="text-end text-muted">
                                        <?= number_format($item['price'], 0, ',', '.') ?>₫
                                    </td>

                                    <!-- Thành tiền -->
                                    <td class="text-end fw-bold text-danger">
                                        <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                    </td>

                                    <!-- Nút xóa -->
                                    <td class="text-center">
                                        <form method="POST"
                                              action="/bookstore/actions/remove_cart.php">
                                            <input type="hidden" name="book_id"
                                                   value="<?= $item['book_id'] ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Xóa khỏi giỏ hàng"
                                                    onclick="return confirm('Xóa sách này khỏi giỏ hàng?')">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div><!-- /.table-responsive -->
                    </div><!-- /.card-body -->
                </div><!-- /.card -->

                <!-- Nút tiếp tục mua sắm -->
                <div class="mt-3">
                    <a href="/bookstore/index.php"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm
                    </a>
                </div>
            </div>

            <!-- ══ CỘT PHẢI: TÓM TẮT ĐƠN HÀNG ══ -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm cart-summary sticky-top" style="top: 80px;">
                    <div class="card-header bg-dark text-white fw-bold py-3">
                        <i class="bi bi-receipt me-2 text-warning"></i>Tóm tắt đơn hàng
                    </div>
                    <div class="card-body p-4">

                        <!-- Chi tiết từng dòng tổng -->
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span>Tạm tính (<?= count($cartItems) ?> sản phẩm)</span>
                            <span><?= number_format($totalPrice, 0, ',', '.') ?>₫</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span>Phí vận chuyển</span>
                            <span class="text-success fw-semibold">Miễn phí</span>
                        </div>

                        <hr>

                        <!-- Tổng tiền -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="fw-bold fs-5">Tổng cộng</span>
                            <span class="fw-bold fs-4 text-danger">
                                <?= number_format($totalPrice, 0, ',', '.') ?>₫
                            </span>
                        </div>

                        <!-- Nút thanh toán -->
                        <div class="d-grid">
                            <a href="/bookstore/checkout.php"
                               class="btn btn-warning btn-lg fw-bold py-3">
                                <i class="bi bi-credit-card me-2"></i>Tiến hành thanh toán
                            </a>
                        </div>

                        <!-- Cam kết -->
                        <div class="mt-3 text-center text-muted small">
                            <i class="bi bi-shield-check text-success me-1"></i>
                            Thanh toán an toàn &amp; bảo mật
                        </div>

                    </div><!-- /.card-body -->
                </div><!-- /.card -->
            </div>

        </div><!-- /.row -->
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>