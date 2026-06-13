<?php
// cart.php

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

// ── KIỂM TRA ĐĂNG NHẬP ───────────────────────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: /bookstore/pages/auth/login.php');
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

    <div class="page-header-custom">
        <h3 class="title">
            <i class="bi bi-cart3"></i> Giỏ hàng của tôi
        </h3>
        <?php if (!empty($cartItems)): ?>
            <span class="badge-custom">
                <?= count($cartItems) ?> sản phẩm
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="text-center py-5">
            <img src="https://img.icons8.com/3d-fluency/94/shopping-cart.png" style="width: 120px; height: 120px; filter: grayscale(1); opacity: 0.7;" class="mb-3" alt="Empty Cart">
            <h5 class="text-muted mt-3">Giỏ hàng của bạn đang trống</h5>
            <p class="text-muted small">Hãy chọn thêm sách yêu thích để bắt đầu!</p>
            <a href="/bookstore/index.php" class="btn btn-warning fw-bold px-4 mt-2 shadow-sm rounded-3">
                <i class="bi bi-book me-2"></i>Khám phá sách ngay
            </a>
        </div>

    <?php else: ?>
        <div class="row g-4">

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 bg-white">
                            
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3 text-center border-bottom" style="background-color: #fff8e1 !important; width: 50px;">
                                        <input class="form-check-input shadow-none" type="checkbox" id="selectAll" checked style="cursor: pointer; transform: scale(1.2);">
                                    </th>
                                    <th class="py-3 text-dark text-uppercase fw-bolder border-bottom" style="background-color: #fff8e1 !important; font-size: 0.85rem; letter-spacing: 0.5px; width: 90px;">Ảnh</th>
                                    <th class="py-3 text-dark text-uppercase fw-bolder border-bottom" style="background-color: #fff8e1 !important; font-size: 0.85rem; letter-spacing: 0.5px;">Tên sách</th>
                                    <th class="text-center py-3 text-dark text-uppercase fw-bolder border-bottom" style="background-color: #fff8e1 !important; font-size: 0.85rem; letter-spacing: 0.5px; width: 140px;">Số lượng</th>
                                    <th class="text-end py-3 text-dark text-uppercase fw-bolder border-bottom" style="background-color: #fff8e1 !important; font-size: 0.85rem; letter-spacing: 0.5px; width: 110px;">Đơn giá</th>
                                    <th class="text-end py-3 text-dark text-uppercase fw-bolder border-bottom" style="background-color: #fff8e1 !important; font-size: 0.85rem; letter-spacing: 0.5px; width: 120px;">Thành tiền</th>
                                    <th class="text-center pe-4 py-3 border-bottom" style="background-color: #fff8e1 !important; width: 70px;"></th>
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
                                <tr style="transition: background-color 0.2s;">
                                    
                                    <td class="ps-4 py-4 text-center">
                                        <input class="form-check-input item-check shadow-none" type="checkbox" name="selected_books[]" value="<?= $item['book_id'] ?>" data-subtotal="<?= $item['subtotal'] ?>" form="checkoutForm" checked style="cursor: pointer; transform: scale(1.2); border-color: #adb5bd;">
                                    </td>

                                    <td class="py-4">
                                        <a href="/bookstore/pages/shop/product.php?id=<?= $item['book_id'] ?>">
                                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                                 alt="<?= htmlspecialchars($item['title']) ?>"
                                                 class="cart-book-img rounded-3 shadow-sm border border-light" style="width: 60px; height: 85px; object-fit: cover;">
                                        </a>
                                    </td>

                                    <td class="py-4">
                                        <a href="/bookstore/pages/shop/product.php?id=<?= $item['book_id'] ?>"
                                           class="fw-bold text-decoration-none cart-title" style="color: #2b2b2b; font-size: 1.05rem;">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </a>
                                        <p class="text-muted small mb-0 mt-2 d-inline-flex align-items-center bg-light px-2 py-1 rounded-pill border">
                                            <span class="d-flex align-items-center justify-content-center rounded-circle bg-warning text-dark me-2" style="width: 18px; height: 18px;">
                                                <i class="bi bi-person-fill" style="font-size: 0.65rem;"></i>
                                            </span>
                                            <?= htmlspecialchars($item['author']) ?>
                                        </p>
                                        <?php if ($item['quantity'] > $item['stock_quantity']): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-danger-subtle text-danger small rounded-2">
                                                    <i class="bi bi-exclamation-circle me-1"></i>
                                                    Chỉ còn <?= $item['stock_quantity'] ?> cuốn
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center py-4">
                                        <form method="POST" action="/bookstore/actions/update_cart.php" class="d-inline-flex align-items-center gap-1 update-form bg-white border rounded-pill p-1 shadow-sm">
                                            <input type="hidden" name="book_id" value="<?= $item['book_id'] ?>">
                                            
                                            <button type="button" class="btn btn-sm rounded-circle d-flex align-items-center justify-content-center qty-btn text-secondary border-0" data-action="decrease" style="width: 28px; height: 28px; background-color: #f8f9fa;">
                                                <i class="bi bi-dash fw-bold"></i>
                                            </button>

                                            <input type="number" name="quantity" class="form-control form-control-sm text-center fw-bold bg-transparent border-0 cart-qty-input shadow-none px-0" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_quantity'] ?>" style="width: 40px; font-size: 1rem;">

                                            <button type="button" class="btn btn-sm rounded-circle d-flex align-items-center justify-content-center qty-btn text-secondary border-0" data-action="increase" data-max="<?= $item['stock_quantity'] ?>" style="width: 28px; height: 28px; background-color: #f8f9fa;">
                                                <i class="bi bi-plus fw-bold"></i>
                                            </button>
                                        </form>
                                    </td>

                                    <td class="text-end py-4 text-secondary fw-medium">
                                        <?= number_format($item['price'], 0, ',', '.') ?>₫
                                    </td>

                                    <td class="text-end py-4 fw-bold text-danger" style="font-size: 1.05rem;">
                                        <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                    </td>

                                    <td class="text-center pe-4 py-4">
                                        <form method="POST" action="/bookstore/actions/remove_cart.php" class="form-remove-cart">
                                            <input type="hidden" name="book_id" value="<?= $item['book_id'] ?>">
                                            <button type="submit" class="btn btn-sm rounded-3 shadow-sm d-inline-flex align-items-center justify-content-center" style="background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; width: 35px; height: 35px; transition: all 0.2s;" title="Xóa khỏi giỏ hàng" onmouseover="this.style.backgroundColor='#fca5a5'" onmouseout="this.style.backgroundColor='#fee2e2'">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <a href="/bookstore/index.php" class="btn btn-light border text-secondary rounded-pill shadow-sm fw-normal px-4">
                        <i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm
                    </a>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 cart-summary sticky-top rounded-4 overflow-hidden" style="top: 100px;">
                    <div class="card-header bg-dark text-white fw-bold py-3 border-0">
                        <i class="bi bi-receipt me-2 text-warning"></i>Tóm tắt đơn hàng
                    </div>
                    <div class="card-body p-4 bg-white">

                        <div class="d-flex justify-content-between mb-3 text-secondary">
                            <span id="summary-count">Tạm tính (<?= count($cartItems) ?> sản phẩm)</span>
                            <span class="fw-medium text-dark" id="summary-subtotal"><?= number_format($totalPrice, 0, ',', '.') ?>₫</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 text-secondary">
                            <span>Phí vận chuyển</span>
                            <span class="text-success fw-semibold bg-success-subtle px-2 py-1 rounded-2" style="font-size: 0.85rem;">Miễn phí</span>
                        </div>

                        <hr class="border-secondary opacity-25 my-4">

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="fw-bold fs-5 text-dark">Tổng cộng</span>
                            <span class="fw-bold fs-4 text-danger" id="summary-total">
                                <?= number_format($totalPrice, 0, ',', '.') ?>₫
                            </span>
                        </div>

                        <div class="d-grid">
                            <form id="checkoutForm" action="/bookstore/pages/shop/checkout.php" method="POST">
                                <button type="submit" id="btn-checkout" class="btn btn-warning w-100 btn-lg fw-bold py-3 shadow-sm rounded-pill">
                                    <i class="bi bi-credit-card me-2"></i>Tiến hành thanh toán
                                </button>
                            </form>
                        </div>

                        <div class="mt-4 text-center text-muted small bg-light py-2 rounded-3 border">
                            <i class="bi bi-shield-check text-success me-1"></i>
                            Thanh toán an toàn &amp; bảo mật
                        </div>

                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Xử lý Logic Checkbox Chọn Sản Phẩm Thanh Toán
    const selectAll = document.getElementById('selectAll');
    const itemChecks = document.querySelectorAll('.item-check');
    const summaryCount = document.getElementById('summary-count');
    const summarySubtotal = document.getElementById('summary-subtotal');
    const summaryTotal = document.getElementById('summary-total');
    const btnCheckout = document.getElementById('btn-checkout');

    function calculateTotal() {
        let total = 0;
        let count = 0;
        
        itemChecks.forEach(check => {
            if (check.checked) {
                total += parseFloat(check.getAttribute('data-subtotal'));
                count++;
            }
        });

        // Format tiền tệ chuẩn VN
        let formattedTotal = new Intl.NumberFormat('vi-VN').format(total) + '₫';

        summaryCount.innerText = `Tạm tính (${count} sản phẩm)`;
        summarySubtotal.innerText = formattedTotal;
        summaryTotal.innerText = formattedTotal;

        // Nếu không chọn món nào thì vô hiệu hóa nút thanh toán
        if (count === 0) {
            btnCheckout.classList.add('disabled');
            btnCheckout.type = 'button'; 
        } else {
            btnCheckout.classList.remove('disabled');
            btnCheckout.type = 'submit';
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            itemChecks.forEach(check => check.checked = this.checked);
            calculateTotal();
        });
    }

    itemChecks.forEach(check => {
        check.addEventListener('change', function() {
            if (!this.checked) selectAll.checked = false;
            if (document.querySelectorAll('.item-check:checked').length === itemChecks.length) {
                selectAll.checked = true;
            }
            calculateTotal();
        });
    });

    // 2. Logic nút Xóa giỏ hàng
    const removeForms = document.querySelectorAll('.form-remove-cart');
    removeForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); 
            Swal.fire({
                title: 'Xóa khỏi giỏ hàng?',
                text: "Bạn có chắc chắn muốn bỏ cuốn sách này?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Xóa ngay',
                cancelButtonText: 'Giữ lại',
                border_radius: '15px'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); 
                }
            });
        });
    });

    // 3. Logic Tự động Submit khi Tăng/Giảm số lượng
    const updateForms = document.querySelectorAll('.update-form');
    updateForms.forEach(form => {
        const btnDecrease = form.querySelector('[data-action="decrease"]');
        const btnIncrease = form.querySelector('[data-action="increase"]');
        const inputQty = form.querySelector('.cart-qty-input');

        if(btnDecrease && btnIncrease && inputQty) {
            btnDecrease.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                let currentVal = parseInt(inputQty.value) || 1;
                if (currentVal > 1) {
                    inputQty.value = currentVal - 1;
                    form.submit(); 
                }
            });

            btnIncrease.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                let currentVal = parseInt(inputQty.value) || 1;
                let maxVal = parseInt(btnIncrease.getAttribute('data-max')) || 999;
                if (currentVal < maxVal) {
                    inputQty.value = currentVal + 1;
                    form.submit(); 
                } else {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'warning',
                        title: 'Chỉ còn ' + maxVal + ' cuốn trong kho!',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            });

            inputQty.addEventListener('change', function() {
                let currentVal = parseInt(this.value) || 1;
                let maxVal = parseInt(btnIncrease.getAttribute('data-max')) || 999;
                if (currentVal < 1) this.value = 1;
                if (currentVal > maxVal) this.value = maxVal;
                form.submit();
            });
        }
    });

    // Chạy tính toán ngay khi load trang
    calculateTotal();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>