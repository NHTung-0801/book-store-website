<?php
// my_orders.php

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

// ── KIỂM TRA ĐĂNG NHẬP ───────────────────────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: /bookstore/pages/auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ── XỬ LÝ LỌC TRẠNG THÁI (STATUS FILTER) TỪ URL ─────────────────────────────
$statusFilter = filter_input(INPUT_GET, 'status');
$validFilters = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];
if ($statusFilter && !in_array($statusFilter, $validFilters)) {
    $statusFilter = null; // Gỡ bỏ nếu tham số truyền vào không hợp lệ
}

// ── QUERY DANH SÁCH ĐƠN HÀNG CỦA USER (CÓ BỘ LỌC) ───────────────────────────
$sqlOrders = "
    SELECT  id,
            fullname,
            phone,
            address,
            total_price,
            status,
            created_at
    FROM    orders
    WHERE   user_id = ?
";
$params = [$userId];

// Áp dụng điều kiện lọc nếu người dùng có chọn
if ($statusFilter) {
    if ($statusFilter === 'shipping') {
        // Gom cả 'shipping' và 'failed' vào mục Đang giao
        $sqlOrders .= " AND status IN ('shipping', 'failed')";
    } else {
        $sqlOrders .= " AND status = ?";
        $params[] = $statusFilter;
    }
}
// Sắp xếp đơn mới nhất lên đầu
$sqlOrders .= " ORDER BY id DESC";

$stmtOrders = $pdo->prepare($sqlOrders);
$stmtOrders->execute($params);
$orders = $stmtOrders->fetchAll();

// ── QUERY CHI TIẾT TỪNG ĐƠN HÀNG (sách bên trong) ───────────────────────────
$stmtDetails = $pdo->prepare("
    SELECT  od.order_id,
            od.quantity,
            od.price,
            od.price * od.quantity  AS subtotal,
            b.title,
            b.author,
            b.image
    FROM    order_details od
    JOIN    books b ON od.book_id = b.id
    JOIN    orders o ON od.order_id = o.id
    WHERE   o.user_id = ?
    ORDER BY od.order_id DESC, b.title ASC
");
$stmtDetails->execute([$userId]);
$allDetails = $stmtDetails->fetchAll();

$detailsMap = [];
foreach ($allDetails as $detail) {
    $detailsMap[$detail['order_id']][] = $detail;
}

// ── HÀM HELPER: Lấy thông tin hiển thị trạng thái ───────────────────────────
function getStatusBadge(string $status): array {
    return match($status) {
        'pending'   => ['class' => 'bg-warning text-dark',  'icon' => 'bi-clock',               'label' => 'Chờ xác nhận',       'color' => '#ffc107'],
        'confirmed' => ['class' => 'bg-info text-dark',     'icon' => 'bi-check-circle',         'label' => 'Đã xác nhận',        'color' => '#0dcaf0'],
        'shipping'  => ['class' => 'bg-primary text-white', 'icon' => 'bi-truck',                'label' => 'Đang giao hàng',     'color' => '#0d6efd'],
        'delivered' => ['class' => 'bg-success text-white', 'icon' => 'bi-bag-check',            'label' => 'Hoàn tất',           'color' => '#198754'],
        'cancelled' => ['class' => 'bg-danger text-white',  'icon' => 'bi-x-circle',             'label' => 'Đã hủy',             'color' => '#dc3545'],
        'failed'    => ['class' => 'bg-dark text-white',    'icon' => 'bi-exclamation-triangle', 'label' => 'Giao hàng thất bại', 'color' => '#212529'],
        default     => ['class' => 'bg-secondary text-white','icon' => 'bi-question-circle',     'label' => ucfirst($status),      'color' => '#6c757d'],
    };
}

// ── KHAI BÁO CÁC TAB LỌC TRẠNG THÁI ─────────────────────────────────────────
$tabs = [
    ''          => 'Tất cả',
    'pending'   => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping'  => 'Đang giao',
    'delivered' => 'Hoàn tất',
    'cancelled' => 'Đã hủy'
];
?>

<main class="max-w-6xl mx-auto px-4 pt-[76px] pb-20 min-h-screen">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <!-- Cụm Bên Trái (Icon + Tiêu đề chính) -->
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-black text-white flex items-center justify-center shadow-md shrink-0">
                <i class="bi bi-bag-check text-base"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#111111] m-0 uppercase" style="font-family: var(--font-body) !important;">ĐƠN HÀNG CỦA TÔI</h1>
        </div>

        <!-- Cụm Bên Phải (Badge Đếm số lượng đơn) -->
        <div class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-[#F8F6F0] border border-black/10 font-bold text-sm text-[#111111] shadow-sm self-start sm:self-auto">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <?= count($orders) ?> đơn hàng <?= $statusFilter ? 'được tìm thấy' : '' ?>
        </div>
    </div>

    <div class="flex flex-nowrap items-center gap-2 pb-6 border-b border-black/10 mb-6 overflow-x-auto hide-scrollbar">
        <?php foreach ($tabs as $key => $label): ?>
            <?php $isActive = ($statusFilter === $key || (!$statusFilter && $key === '')); ?>
            <a href="?status=<?= $key ?>" 
               class="rounded-full px-4 sm:px-5 py-2 text-[13px] sm:text-sm whitespace-nowrap transition-all duration-300 ease-in-out cursor-pointer inline-block <?= $isActive ? 'bg-[#111111] text-white shadow-md scale-105 font-semibold' : 'bg-white border border-black/10 text-gray-600 hover:border-black/30 hover:bg-black/5 hover:text-black hover:-translate-y-0.5 font-medium' ?>" style="text-decoration: none;">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>
        <div class="text-center py-5">
            <i class="bi bi-bag-x text-muted" style="font-size: 5rem;"></i>
            <h5 class="text-muted mt-3">Không có đơn hàng nào</h5>
            <p class="text-muted small">Bạn chưa có đơn hàng nào thuộc trạng thái này!</p>
            <a href="/bookstore/index.php" class="btn btn-warning fw-bold px-4 mt-2">
                <i class="bi bi-book me-2"></i>Khám phá sách ngay
            </a>
        </div>

    <?php else: ?>

        <style>
            .accordion-button::after {
                display: none !important;
            }
            .accordion-btn-custom i.chevron-icon {
                transition: transform 0.3s ease;
            }
            .accordion-btn-custom:not(.collapsed) i.chevron-icon {
                transform: rotate(-180deg);
            }
        </style>
        
        <div class="accordion accordion-flush order-accordion" id="orderAccordion">

            <?php foreach ($orders as $index => $order):
                $badge   = getStatusBadge($order['status']);
                $items   = $detailsMap[$order['id']] ?? [];
                $collapseId = 'order-' . $order['id'];
                $isFirst = ($index === 0);
            ?>

            <div class="accordion-item border-0 shadow-sm mb-2 rounded-3 overflow-hidden" 
                 style="border-left: 4px solid #ffc107 !important;">

                <h2 class="accordion-header">
                    <button
                        class="accordion-button accordion-btn-custom <?= $isFirst ? '' : 'collapsed' ?> py-2 px-3"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= $collapseId ?>"
                        aria-expanded="<?= $isFirst ? 'true' : 'false' ?>"
                        style="min-height: 50px;"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 w-full cursor-pointer pr-2">

                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" 
                                     style="width: 32px; height: 32px; background-color: #fff8e1; color: #ffc107;">
                                    <i class="bi bi-box-seam fs-6"></i>
                                </div>
                                <div class="lh-sm" style="text-align: left;">
                                    <span class="fw-bold text-dark d-block" style="font-size: 0.95rem;">
                                        Đơn hàng #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                    </span>
                                    <?php if (!empty($order['created_at'])): ?>
                                        <span class="text-muted" style="font-size: 0.75rem;">
                                            <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 sm:gap-4 self-end sm:self-auto shrink-0">
                                <span class="font-extrabold text-base sm:text-lg text-[#FF4500] text-right w-[110px] whitespace-nowrap shrink-0">
                                    <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                </span>
                                <span class="<?= $badge['class'] ?> inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-bold leading-none whitespace-nowrap h-7 w-[130px] shrink-0">
                                    <i class="bi <?= $badge['icon'] ?> me-1"></i>
                                    <?= $badge['label'] ?>
                                </span>
                                
                                <!-- Custom Chevron Icon -->
                                <div class="flex items-center justify-center shrink-0 ml-1 text-gray-500">
                                    <i class="bi bi-chevron-down chevron-icon text-lg"></i>
                                </div>
                            </div>

                        </div>
                    </button>
                </h2>

                <div id="<?= $collapseId ?>"
                     class="accordion-collapse collapse <?= $isFirst ? 'show' : '' ?>"
                     data-bs-parent="#orderAccordion">

                    <div class="accordion-body p-0 border-t border-black/5 bg-[#FAFAFA]">

                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-0">
                            
                            <!-- Cột Trái: Danh sách Sản phẩm -->
                            <div class="col-span-1 lg:col-span-7 p-5 sm:p-6 border-b lg:border-b-0 lg:border-r border-black/5">
                                <div class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">
                                    Sản phẩm (<?= count($items) ?> cuốn)
                                </div>
                                
                                <?php if (empty($items)): ?>
                                    <p class="text-[15px] text-gray-500 italic">Không có dữ liệu sản phẩm.</p>
                                <?php else: ?>
                                    <div class="flex flex-col gap-3">
                                        <?php foreach ($items as $item):
                                            $imgPath = '/bookstore/assets/images/books/' . $item['image'];
                                            $imgSrc  = (!empty($item['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
                                                        ? $imgPath : '/bookstore/assets/images/books/placeholder.png';
                                        ?>
                                        <div class="flex items-start gap-4 p-3 rounded-xl bg-white border border-black/5 shadow-[0_2px_10px_rgba(0,0,0,0.02)] hover:shadow-[0_4px_15px_rgba(0,0,0,0.04)] transition-all duration-300">
                                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-[72px] h-[100px] object-cover rounded-md shadow-sm border border-black/10 shrink-0">
                                            <div class="flex-grow flex flex-col justify-between h-full py-1">
                                                <div>
                                                    <h4 class="font-bold text-base text-[#111111] mb-1.5 leading-tight line-clamp-2">
                                                        <?= htmlspecialchars($item['title']) ?>
                                                    </h4>
                                                    <div class="text-[15px] text-gray-500 font-medium">
                                                        <?= htmlspecialchars($item['author']) ?>
                                                    </div>
                                                </div>
                                                <div class="flex justify-between items-center mt-3">
                                                    <span class="text-[14px] font-semibold px-2.5 py-1 bg-[#F8F6F0] rounded-md text-gray-700 border border-black/5">
                                                        <?= number_format($item['price'], 0, ',', '.') ?>₫ &times; <?= $item['quantity'] ?>
                                                    </span>
                                                    <span class="font-extrabold text-[#FF4500] text-base">
                                                        <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Cột Phải: Thanh toán & Giao hàng -->
                            <div class="col-span-1 lg:col-span-5 p-5 sm:p-6 bg-white">
                                <div class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-4">
                                    Thanh toán & Giao hàng
                                </div>
                                
                                <div class="bg-[#F8F6F0] rounded-xl p-4 mb-5 border border-black/5 shadow-inner">
                                    <div class="flex justify-between text-[15px] mb-2 text-gray-600 font-medium">
                                        <span>Tạm tính</span>
                                        <span><?= number_format($order['total_price'], 0, ',', '.') ?>₫</span>
                                    </div>
                                    <div class="flex justify-between text-[15px] mb-3 text-gray-600 font-medium">
                                        <span>Phí vận chuyển</span>
                                        <span class="text-emerald-600 font-bold">Miễn phí</span>
                                    </div>
                                    <div class="border-t border-black/10 pt-3 flex justify-between items-center">
                                        <span class="font-bold text-[14px] text-[#111111]">TỔNG CỘNG</span>
                                        <span class="font-extrabold text-[22px] text-[#FF4500]">
                                            <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                        </span>
                                    </div>
                                </div>

                                <div class="text-[15px] text-gray-700 space-y-2.5 mb-6 p-4 rounded-xl border border-black/5 bg-gray-50">
                                    <div class="font-bold text-[#111111] text-base mb-1.5"><?= htmlspecialchars($order['fullname']) ?></div>
                                    <div class="flex gap-2.5 items-center"><i class="bi bi-telephone-fill text-gray-400"></i> <?= htmlspecialchars($order['phone']) ?></div>
                                    <div class="flex gap-2.5 items-start mt-1.5"><i class="bi bi-geo-alt-fill text-gray-400 mt-1"></i> <span class="leading-relaxed"><?= htmlspecialchars($order['address']) ?></span></div>
                                </div>

                                <div class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-3">
                                    Trạng thái đơn hàng
                                </div>
                                <div class="relative pl-3 border-l-2 border-black/10 space-y-5 ml-2 mt-2">
                                    <?php 
                                        $isError = false;
                                        if ($order['status'] === 'failed') {
                                            $steps = ['pending', 'confirmed', 'shipping', 'failed'];
                                            $stepLabels = ['Chờ xác nhận', 'Đã xác nhận', 'Đang giao hàng', 'Giao hàng thất bại'];
                                            $currentStep = 3;
                                            $isError = true;
                                        } elseif ($order['status'] === 'cancelled') {
                                            $steps = ['pending', 'cancelled'];
                                            $stepLabels = ['Chờ xác nhận', 'Đã hủy'];
                                            $currentStep = 1;
                                            $isError = true;
                                        } else {
                                            $steps = ['pending', 'confirmed', 'shipping', 'delivered'];
                                            $stepLabels = ['Chờ xác nhận', 'Đã xác nhận', 'Đang giao hàng', 'Giao thành công'];
                                            $currentStep = array_search($order['status'], $steps);
                                            if ($currentStep === false) {
                                                if ($order['status'] === 'completed') $currentStep = 3;
                                                else $currentStep = 0;
                                            }
                                        }
                                    ?>
                                    <?php foreach ($steps as $i => $step): 
                                        $isDone = $i <= $currentStep;
                                        $isCurrent = $i == $currentStep;
                                        $dotColor = ($isError && $isCurrent) ? 'bg-[#FF4500]' : ($isDone ? 'bg-[#111111]' : 'bg-gray-300');
                                        $pingColor = ($isError && $isCurrent) ? 'bg-[#FF4500]' : 'bg-[#111111]';
                                        $textColor = ($isError && $isCurrent) ? 'text-[#FF4500]' : 'text-[#111111]';
                                    ?>
                                        <div class="relative">
                                            <div class="absolute -left-[21px] top-1 w-3 h-3 rounded-full border-2 border-white <?= $dotColor ?>">
                                                <?= $isCurrent ? '<span class="absolute -inset-1.5 rounded-full animate-ping ' . $pingColor . ' opacity-40"></span>' : '' ?>
                                            </div>
                                            <div class="pl-3">
                                                <p class="text-[15px] <?= $isDone ? 'font-bold ' . $textColor : 'font-medium text-gray-400' ?>">
                                                    <?= $stepLabels[$i] ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                            </div>

                        </div>
                    </div></div></div></div></div><?php endforeach; ?>

        </div><?php endif; ?>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>