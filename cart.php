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
?>

<main class="container my-5">

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

    <?php if (empty($cartItems)): ?>
        <div class="text-center py-5">
            <img src="https://img.icons8.com/3d-fluency/94/shopping-cart.png" style="width: 120px; height: 120px; filter: grayscale(1); opacity: 0.7;" class="mb-3" alt="Empty Cart">
            <h5 class="text-muted mt-3">Giỏ hàng của bạn đang trống</h5>
            <p class="text-muted small">Hãy chọn thêm sách yêu thích để bắt đầu!</p>
            <a href="/bookstore/index.php" class="btn btn-warning fw-bold px-4 mt-2 shadow-sm">
                <i class="bi bi-book me-2"></i>Khám phá sách ngay
            </a>
        </div>

    <?php else: ?>
        <div class="row g-4">

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
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
                                    <td class="ps-4">
                                        <a href="/bookstore/product.php?id=<?= $item['book_id'] ?>">
                                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                                 alt="<?= htmlspecialchars($item['title']) ?>"
                                                 class="cart-book-img rounded border shadow-sm" style="width: 50px; height: 70px; object-fit: cover;">
                                        </a>
                                    </td>

                                    <td>
                                        <a href="/bookstore/product.php?id=<?= $item['book_id'] ?>"
                                           class="fw-bold text-dark text-decoration-none cart-title">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </a>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-person me-1"></i>
                                            <?= htmlspecialchars($item['author']) ?>
                                        </p>
                                        <?php if ($item['quantity'] > $item['stock_quantity']): ?>
                                            <span class="badge bg-danger-subtle text-danger small mt-1">
                                                <i class="bi bi-exclamation-circle me-1"></i>
                                                Chỉ còn <?= $item['stock_quantity'] ?> cuốn
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <form method="POST"
                                              action="/bookstore/actions/update_cart.php"
                                              class="d-flex align-items-center justify-content-center gap-1 update-form">
                                            <input type="hidden" name="book_id"
                                                   value="<?= $item['book_id'] ?>">

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary qty-btn"
                                                    data-action="decrease"
                                                    aria-label="Giảm">
                                                <i class="bi bi-dash"></i>
                                            </button>

                                            <input type="number"
                                                   name="quantity"
                                                   class="form-control form-control-sm text-center fw-bold qty-input cart-qty-input shadow-none"
                                                   value="<?= $item['quantity'] ?>"
                                                   min="1"
                                                   max="<?= $item['stock_quantity'] ?>" style="width: 50px;">

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary qty-btn"
                                                    data-action="increase"
                                                    data-max="<?= $item['stock_quantity'] ?>"
                                                    aria-label="Tăng">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </form>
                                    </td>

                                    <td class="text-end text-muted">
                                        <?= number_format($item['price'], 0, ',', '.') ?>₫
                                    </td>

                                    <td class="text-end fw-bold text-danger">
                                        <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                    </td>

                                    <td class="text-center pe-4">
                                        <form method="POST" action="/bookstore/actions/remove_cart.php" class="form-remove-cart">
                                            <input type="hidden" name="book_id" value="<?= $item['book_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa khỏi giỏ hàng">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div></div></div><div class="mt-3">
                    <a href="/bookstore/index.php"
                       class="btn btn-outline-secondary shadow-sm">
                        <i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm
                    </a>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm cart-summary sticky-top" style="top: 80px;">
                    <div class="card-header bg-dark text-white fw-bold py-3">
                        <i class="bi bi-receipt me-2 text-warning"></i>Tóm tắt đơn hàng
                    </div>
                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span>Tạm tính (<?= count($cartItems) ?> sản phẩm)</span>
                            <span><?= number_format($totalPrice, 0, ',', '.') ?>₫</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span>Phí vận chuyển</span>
                            <span class="text-success fw-semibold">Miễn phí</span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="fw-bold fs-5">Tổng cộng</span>
                            <span class="fw-bold fs-4 text-danger">
                                <?= number_format($totalPrice, 0, ',', '.') ?>₫
                            </span>
                        </div>

                        <div class="d-grid">
                            <a href="/bookstore/checkout.php"
                               class="btn btn-warning btn-lg fw-bold py-3 shadow-sm">
                                <i class="bi bi-credit-card me-2"></i>Tiến hành thanh toán
                            </a>
                        </div>

                        <div class="mt-3 text-center text-muted small">
                            <i class="bi bi-shield-check text-success me-1"></i>
                            Thanh toán an toàn &amp; bảo mật
                        </div>

                    </div></div></div>

        </div><?php endif; ?>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const removeForms = document.querySelectorAll('.form-remove-cart');
    removeForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Ngăn form gửi đi ngay lập tức
            Swal.fire({
                title: 'Xóa khỏi giỏ hàng?',
                text: "Bạn có chắc chắn muốn bỏ cuốn sách này?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Đồng ý xóa',
                cancelButtonText: 'Giữ lại'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // Chỉ submit form khi người dùng xác nhận
                }
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>