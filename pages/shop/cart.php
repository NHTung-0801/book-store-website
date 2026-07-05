<?php
// cart.php
$pageTitle = 'Giỏ hàng | NOVELTY';

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

<main class="max-w-6xl mx-auto px-4 pt-[76px] pb-20 min-h-screen">

    <!-- Nâng cấp Hero Header Giỏ hàng -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-black/10 mb-6">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-black text-white flex items-center justify-center shadow-md shrink-0">
                <i class="bi bi-cart3 text-base"></i>
            </div>
            <h3 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#111111] m-0 uppercase" style="font-family: var(--font-body) !important;">GIỎ HÀNG CỦA TÔI</h3>
        </div>
        <?php if (!empty($cartItems)): ?>
            <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-[#F8F6F0] border border-black/10 font-bold text-sm text-[#111111] shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <?= count($cartItems) ?> sản phẩm
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($cartItems)): ?>
        <div class="text-center py-20 bg-white rounded-3xl border border-black/5 shadow-sm">
            <img src="https://img.icons8.com/3d-fluency/94/shopping-cart.png" style="width: 120px; height: 120px; filter: grayscale(1); opacity: 0.7;" class="mb-4 mx-auto" alt="Empty Cart">
            <h5 class="text-2xl font-bold text-[#111111] mb-2" style="font-family: var(--font-body) !important;">Giỏ hàng của bạn đang trống</h5>
            <p class="text-gray-500 mb-6">Hãy chọn thêm sách yêu thích để bắt đầu!</p>
            <a href="/bookstore/index.php" class="inline-flex items-center gap-2 px-6 py-3 bg-black text-white rounded-full font-bold shadow-md hover:bg-gray-800 transition-colors text-decoration-none">
                <i class="bi bi-book"></i> Khám phá sách ngay
            </a>
        </div>

    <?php else: ?>
        <form id="checkoutForm" action="/bookstore/pages/shop/checkout.php" method="POST">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start pb-32">

            <!-- Cột trái (Sản phẩm) -->
            <div class="lg:col-span-7">
                <div class="mb-6">
                    <?php foreach ($cartItems as $item): ?>
                    <?php
                        $imgPath = '/bookstore/assets/images/books/' . $item['image'];
                        $imgSrc  = (!empty($item['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
                                    ? $imgPath
                                    : '/bookstore/assets/images/books/placeholder.png';
                    ?>
                    
                    <!-- Cart Item Card -->
                    <div class="bg-white border border-black/10 rounded-2xl p-4 sm:p-5 mb-4 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-md transition-all duration-300 flex items-center gap-4 relative overflow-hidden group">
                        
                        <!-- Checkbox -->
                        <div class="shrink-0 pl-1">
                            <input type="checkbox" name="selected_books[]" value="<?= $item['book_id'] ?>" data-subtotal="<?= $item['subtotal'] ?>" checked class="item-check w-5 h-5 accent-black rounded cursor-pointer border-gray-300">
                        </div>

                        <!-- Image -->
                        <a href="/bookstore/pages/shop/product.php?id=<?= $item['book_id'] ?>" class="shrink-0">
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                 alt="<?= htmlspecialchars($item['title']) ?>"
                                 class="w-20 h-28 object-cover rounded-xl shadow-sm hover:scale-105 transition-transform duration-300 border border-black/5">
                        </a>

                        <!-- Info -->
                        <div class="flex-grow flex flex-col min-w-0 py-1">
                            <a href="/bookstore/pages/shop/product.php?id=<?= $item['book_id'] ?>"
                               class="text-lg font-bold text-[#111111] leading-snug line-clamp-1 hover:text-[#FF4500] transition-colors text-decoration-none">
                                <?= htmlspecialchars($item['title']) ?>
                            </a>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mt-0.5 line-clamp-1 mb-0">
                                <?= htmlspecialchars($item['author']) ?>
                            </p>
                            
                            <!-- Action Row -->
                            <div class="flex items-center justify-between w-full mt-4 pt-3 border-t border-black/5">
                                <!-- Qty selector -->
                                <div class="inline-flex items-center border border-black/15 bg-[#F8F6F0] rounded-full p-1 shadow-2xs">
                                    <button type="button" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-black/10 transition border-0 bg-transparent text-gray-700" onclick="updateQuantity(<?= $item['book_id'] ?>, 'decrease', <?= $item['stock_quantity'] ?>)">-</button>
                                    <span class="w-8 text-center font-bold text-sm text-[#111111]"><?= $item['quantity'] ?></span>
                                    <button type="button" class="w-7 h-7 flex items-center justify-center rounded-full hover:bg-black/10 transition border-0 bg-transparent text-gray-700" onclick="updateQuantity(<?= $item['book_id'] ?>, 'increase', <?= $item['stock_quantity'] ?>)">+</button>
                                </div>
                                
                                <div class="flex items-center">
                                    <!-- Price -->
                                    <span class="text-lg font-extrabold text-[#FF4500]">
                                        <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                    </span>
                                    
                                    <!-- Trash Button -->
                                    <button type="button" onclick="document.getElementById('form-remove-<?= $item['book_id'] ?>').submit()" class="w-9 h-9 rounded-full bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white flex items-center justify-center transition-all duration-200 cursor-pointer shadow-2xs ml-4 border-0">
                                        <i class="bi bi-trash3 text-[15px]"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Continue Shopping -->
                <a href="/bookstore/index.php" class="inline-flex items-center gap-2 font-semibold text-sm text-gray-600 hover:text-[#111111] mt-2 transition-colors cursor-pointer group text-decoration-none px-2">
                    <i class="bi bi-arrow-left group-hover:-translate-x-1 transition-transform"></i> Tiếp tục mua sắm
                </a>
            </div>

            <!-- ── ORDER SUMMARY ── -->
            <div class="lg:col-span-5">
                <div class="bg-[#FDFCF7] border border-black/10 rounded-3xl p-6 md:p-8 shadow-[0_15px_35px_rgba(0,0,0,0.05)] sticky top-32 h-fit z-10 transition-all duration-300 hover:border-black/30 hover:shadow-[0_20px_40px_rgba(0,0,0,0.08)]">
                    <h4 class="text-xl font-extrabold tracking-tight text-[#111111] mb-6 pb-4 border-b border-black/10" style="font-family: var(--font-body) !important;">TÓM TẮT ĐƠN HÀNG</h4>
                    
                    <div class="flex justify-between items-center text-sm font-medium text-gray-600 mb-4">
                        <span id="summary-count">Tạm tính (<?= count($cartItems) ?> sản phẩm)</span>
                        <span class="text-[#111111]" id="summary-subtotal"><?= number_format($totalPrice, 0, ',', '.') ?>₫</span>
                    </div>
                    <div class="flex justify-between items-center text-sm font-medium text-gray-600 mb-4">
                        <span>Phí vận chuyển</span>
                        <span class="font-bold text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full text-xs">Miễn phí</span>
                    </div>

                    <div class="border-t border-dashed border-black/15 pt-5 mt-4 flex justify-between items-center">
                        <span class="text-base font-bold text-[#111]">TỔNG CỘNG</span>
                        <span class="text-2xl sm:text-3xl font-extrabold text-[#FF4500]" id="summary-total">
                            <?= number_format($totalPrice, 0, ',', '.') ?>₫
                        </span>
                    </div>

                    <button type="submit" id="btn-checkout" class="w-full bg-[#111111] text-white font-semibold py-4 rounded-full text-sm tracking-wide uppercase hover:bg-black/80 hover:shadow-[0_15px_30px_rgba(0,0,0,0.15)] active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 mt-6 cursor-pointer relative overflow-hidden group after:absolute after:inset-0 after:w-1/2 after:h-full after:bg-white/15 after:-skew-x-[20deg] after:-translate-x-full group-hover:after:translate-x-[300%] after:transition-transform after:duration-1000 border-0">
                        TIẾN HÀNH THANH TOÁN
                        <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </div>
            </div>

        </div>
        </form>

        <!-- Hidden Forms for Actions -->
        <?php foreach ($cartItems as $item): ?>
            <form method="POST" action="/bookstore/actions/update_cart.php" id="form-qty-<?= $item['book_id'] ?>" class="d-none update-form">
                <input type="hidden" name="book_id" value="<?= $item['book_id'] ?>">
                <button type="submit" name="action" value="decrease" data-action="decrease"></button>
                <input type="number" name="quantity" value="<?= $item['quantity'] ?>" class="cart-qty-input">
                <button type="submit" name="action" value="increase" data-action="increase" data-max="<?= $item['stock_quantity'] ?>"></button>
            </form>

            <form method="POST" action="/bookstore/actions/remove_cart.php" id="form-remove-<?= $item['book_id'] ?>" class="d-none">
                <input type="hidden" name="book_id" value="<?= $item['book_id'] ?>">
            </form>
        <?php endforeach; ?>

    <?php endif; ?>

</main>

<script>
function updateQuantity(bookId, action, maxQty) {
    const form = document.getElementById('form-qty-' + bookId);
    if (!form) return;
    const inputQty = form.querySelector('input[name="quantity"]');
    if (!inputQty) return;

    let currentVal = parseInt(inputQty.value) || 1;
    if (action === 'decrease') {
        if (currentVal > 1) {
            inputQty.value = currentVal - 1;
            form.submit();
        }
    } else if (action === 'increase') {
        if (currentVal < maxQty) {
            inputQty.value = currentVal + 1;
            form.submit();
        } else {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: 'Chỉ còn ' + maxQty + ' cuốn trong kho!',
                showConfirmButton: false,
                timer: 3000
            });
        }
    }
}

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