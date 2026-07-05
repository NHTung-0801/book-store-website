<?php
// checkout.php
$pageTitle = 'Thanh toán | NOVELTY';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

// ── 1. KIỂM TRA ĐĂNG NHẬP ───────────────────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: /bookstore/pages/auth/login.php');
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
        header('Location: /bookstore/pages/shop/checkout.php');
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
        
        $_SESSION['success'] = "Đặt hàng thành công!";

        // HIỂN THỊ MODAL THÀNH CÔNG TRƯỚC KHI CHUYỂN HƯỚNG
        ?>
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-md transition-opacity duration-300">
            <div class="bg-white p-10 rounded-[2.5rem] shadow-2xl text-center max-w-sm w-full mx-4 transform scale-100 transition-all duration-500 animate-fade-in-up">
                <div class="w-24 h-24 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-6 border-4 border-emerald-100">
                    <i class="bi bi-check-lg text-6xl text-emerald-500"></i>
                </div>
                <h3 class="text-3xl font-extrabold text-[#111] mb-2 tracking-tight">Thành công!</h3>
                <p class="text-gray-500 font-medium mb-8 text-sm">Đơn hàng của bạn đã được hệ thống ghi nhận.</p>
                <div class="w-10 h-10 mx-auto border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mb-4"></div>
                <p class="text-xs text-gray-400 uppercase tracking-widest font-bold">Đang chuyển trang...</p>
            </div>
        </div>
        <style>
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px) scale(0.95); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
            .animate-fade-in-up {
                animation: fadeInUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }
        </style>
        <script>
            setTimeout(function() {
                window.location.href = '/bookstore/pages/user/my_orders.php';
            }, 2500);
        </script>
        <?php
        require_once __DIR__ . '/../../includes/footer.php';
        exit;

    } catch (Exception $e) {
        $pdo->rollBack(); // Hoàn tác nếu có bất kỳ lỗi nào xảy ra
        $_SESSION['error'] = "Lỗi khi xử lý đơn hàng: " . $e->getMessage();
        header('Location: /bookstore/pages/shop/checkout.php');
        exit;
    }
}

// ── 3. QUERY DỮ LIỆU ĐỂ HIỂN THỊ GIAO DIỆN THANH TOÁN ───────────────────────
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
$stmtCount->execute([$userId]);
if ($stmtCount->fetchColumn() == 0) {
    header('Location: /bookstore/pages/shop/cart.php');
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

<main class="max-w-6xl mx-auto px-4 pt-[76px] pb-24 min-h-screen">

    <!-- Header & Badge -->
    <div class="flex items-center justify-between pb-6 border-b border-black/10 mb-6">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-black text-white flex items-center justify-center shadow-md shrink-0">
                <i class="bi bi-truck text-base"></i>
            </div>
            <h3 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#111111] m-0 uppercase" style="font-family: var(--font-body) !important;">THANH TOÁN ĐƠN HÀNG</h3>
        </div>
        <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-emerald-50 border border-emerald-200 font-bold text-xs text-emerald-700 shadow-2xs">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Giao dịch bảo mật 100%
        </span>
    </div>

    <!-- Error Alert -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-6 py-4 rounded-2xl flex items-center gap-3 shadow-2xs mb-8 font-medium text-sm">
            <i class="bi bi-exclamation-triangle-fill text-xl"></i>
            <span><?= htmlspecialchars($_SESSION['error']) ?></span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- JS Error Alert (Hidden by default) -->
    <div id="js-error-alert" class="hidden bg-rose-50 border border-rose-200 text-rose-700 px-6 py-4 rounded-2xl items-center gap-3 shadow-2xs mb-8 font-medium text-sm">
        <i class="bi bi-exclamation-triangle-fill text-xl"></i>
        <span>Vui lòng nhập đầy đủ thông tin giao hàng.</span>
    </div>

    <form id="checkoutForm" action="" method="POST">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start pb-12">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <!-- Left Col (Form) -->
            <div class="lg:col-span-7">
                <h4 class="text-xl font-extrabold tracking-tight text-[#111111] mb-6 pb-4 border-b border-black/10 uppercase" style="font-family: var(--font-body) !important;">Thông tin giao hàng</h4>
                
                <div class="flex flex-col gap-6">
                    <!-- Standard input fullname -->
                    <div>
                        <label for="fullname" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2 flex items-center gap-1.5"><i class="bi bi-person-circle text-gray-400 text-sm"></i> Họ và tên người nhận *</label>
                        <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required
                               class="w-full bg-white border border-black/15 rounded-2xl px-4 py-3.5 text-sm font-medium text-[#111] transition-all duration-200 shadow-2xs hover:border-black/40 focus:border-black focus:ring-4 focus:ring-black/5 outline-none">
                    </div>

                    <!-- Standard input phone -->
                    <div>
                        <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2 flex items-center gap-1.5"><i class="bi bi-telephone text-gray-400 text-sm"></i> Số điện thoại liên hệ *</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required
                               class="w-full bg-white border border-black/15 rounded-2xl px-4 py-3.5 text-sm font-medium text-[#111] transition-all duration-200 shadow-2xs hover:border-black/40 focus:border-black focus:ring-4 focus:ring-black/5 outline-none">
                    </div>

                    <!-- Standard input address -->
                    <div>
                        <label for="address" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2 flex items-center gap-1.5"><i class="bi bi-geo-alt text-gray-400 text-sm"></i> Địa chỉ giao hàng chi tiết *</label>
                        <textarea id="address" name="address" rows="2" required
                               class="w-full bg-white border border-black/15 rounded-2xl px-4 py-3.5 text-sm font-medium text-[#111] transition-all duration-200 shadow-2xs hover:border-black/40 focus:border-black focus:ring-4 focus:ring-black/5 outline-none resize-none"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Standard input note -->
                    <div>
                        <label for="note" class="block text-xs font-bold uppercase tracking-wider text-[#111111] mb-2 flex items-center gap-1.5"><i class="bi bi-pencil-square text-gray-400 text-sm"></i> Ghi chú đơn hàng (Tùy chọn)</label>
                        <textarea id="note" name="note" rows="2"
                               class="w-full bg-white border border-black/15 rounded-2xl px-4 py-3.5 text-sm font-medium text-[#111] transition-all duration-200 shadow-2xs hover:border-black/40 focus:border-black focus:ring-4 focus:ring-black/5 outline-none resize-none"></textarea>
                    </div>
                </div>

                <h4 class="text-xl font-extrabold tracking-tight text-[#111111] mt-10 mb-6 pb-4 border-b border-black/10 uppercase">Phương thức thanh toán</h4>
                <label class="bg-white border-2 border-black/10 rounded-2xl p-5 transition-all duration-300 cursor-pointer flex items-start gap-4 shadow-2xs hover:border-black hover:shadow-md hover:-translate-y-0.5 group has-[:checked]:border-black has-[:checked]:bg-[#F8F6F0]">
                    <input type="radio" name="payment_method" id="cod" value="cod" checked class="mt-1 w-5 h-5 text-black border-gray-300 focus:ring-black accent-black cursor-pointer">
                    <div>
                        <span class="block font-bold text-lg text-[#111111] leading-none mb-2 transition-colors">Thanh toán khi nhận hàng (COD)</span>
                        <span class="block text-gray-500 text-sm">Bạn sẽ thanh toán bằng tiền mặt khi giao sách tới.</span>
                    </div>
                </label>
            </div>

            <!-- Right Col (Summary) -->
            <div class="lg:col-span-5">
                <div class="bg-[#FDFCF7] border border-black/10 rounded-3xl p-6 md:p-8 shadow-[0_15px_35px_rgba(0,0,0,0.05)] sticky top-28 h-fit z-10 transition-all duration-300 hover:shadow-lg hover:border-black/20">
                    <h4 class="text-xl font-extrabold tracking-tight text-[#111111] mb-6 pb-4 border-b border-black/10 flex items-center justify-between" style="font-family: var(--font-body) !important;">CHI TIẾT ĐƠN HÀNG</h4>
                    
                    <div class="flex justify-between items-center text-sm font-medium text-gray-600 mb-4">
                        <span>Sản phẩm (<?= count($cartItems) ?>)</span>
                    </div>

                    <div class="mb-6 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                        <?php foreach ($cartItems as $item): ?>
                        <div class="flex items-center justify-between gap-4 py-3 border-b border-black/5 text-sm">
                            <h6 class="mb-0 text-sm font-bold text-[#111] line-clamp-1 leading-snug flex-grow min-w-0"><?= htmlspecialchars($item['title']) ?></h6>
                            <span class="text-gray-600 font-medium whitespace-nowrap shrink-0">
                                <?= $item['quantity'] ?> <span class="text-gray-400 mx-1">×</span> <span class="text-[#111] font-bold"><?= number_format($item['price'], 0, ',', '.') ?>₫</span>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex justify-between items-center text-sm font-medium text-gray-600 my-4">
                        <span>Tạm tính</span>
                        <span class="text-[#111111] font-bold"><?= number_format($totalPrice, 0, ',', '.') ?>₫</span>
                    </div>
                    
                    <div class="flex justify-between items-center text-sm font-medium text-gray-600 my-4">
                        <span>Phí vận chuyển</span>
                        <span class="font-bold text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full text-xs">Miễn phí</span>
                    </div>

                    <div class="border-t border-dashed border-black/15 pt-5 mt-4 flex justify-between items-center mb-8">
                        <span class="text-base font-bold text-[#111]">TỔNG CỘNG</span>
                        <span class="text-2xl sm:text-3xl font-extrabold text-[#FF4500]">
                            <?= number_format($totalPrice, 0, ',', '.') ?>₫
                        </span>
                    </div>

                    <button type="submit" class="w-full bg-[#111111] text-white font-semibold py-4 rounded-full text-sm tracking-wide uppercase hover:bg-black/80 hover:shadow-[0_15px_30px_rgba(0,0,0,0.15)] active:scale-[0.98] transition-all duration-300 flex items-center justify-center gap-2 mt-6 cursor-pointer relative overflow-hidden group border-0 after:absolute after:inset-0 after:w-1/2 after:h-full after:bg-white/15 after:-skew-x-[20deg] after:-translate-x-full group-hover:after:translate-x-[300%] after:transition-transform after:duration-1000">
                        <span class="btn-text z-10 relative">XÁC NHẬN ĐẶT HÀNG</span>
                        <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform z-10 relative"></i>
                    </button>
                    
                    <a href="/bookstore/pages/shop/cart.php" class="inline-flex items-center justify-center gap-2 w-full text-center font-semibold text-xs text-gray-500 hover:text-black mt-4 transition-colors cursor-pointer uppercase tracking-wider text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Quay lại Giỏ hàng
                    </a>
                </div>
            </div>
            
        </div>
    </form>
</main>

<style>
    /* Styling scrollbar cho danh sách sản phẩm */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('checkoutForm');
    if (!form) return;
    
    // Disable default HTML5 validation tooltip so we can show our custom alert
    form.setAttribute('novalidate', 'true');
    
    var errorAlert = document.getElementById('js-error-alert');
    
    form.addEventListener('submit', function(e) {
        var fullname = document.getElementById('fullname').value.trim();
        var phone = document.getElementById('phone').value.trim();
        var address = document.getElementById('address').value.trim();
        
        if (!fullname || !phone || !address) {
            e.preventDefault();
            if (errorAlert) {
                // Ensure it uses flex instead of block so the icon and text align
                errorAlert.classList.remove('hidden');
                errorAlert.classList.add('flex');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            return;
        } else {
            if (errorAlert) {
                errorAlert.classList.add('hidden');
                errorAlert.classList.remove('flex');
            }
        }
        
        var btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Đang xử lý...';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>