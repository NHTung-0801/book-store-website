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

<main class="container my-5">

    <div class="page-header-custom mb-4">
        <h3 class="title">
            <i class="bi bi-bag-check"></i> Đơn hàng của tôi
        </h3>
        <span class="badge-custom">
            <?= count($orders) ?> đơn hàng <?= $statusFilter ? 'được tìm thấy' : '' ?>
        </span>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4 pb-3 border-bottom">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="?status=<?= $key ?>" 
               class="btn btn-sm rounded-pill px-3 <?= ($statusFilter === $key || (!$statusFilter && $key === '')) ? 'btn-warning fw-bold shadow-sm' : 'btn-light text-muted border' ?>">
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
                        class="accordion-button <?= $isFirst ? '' : 'collapsed' ?> py-2 px-3"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= $collapseId ?>"
                        aria-expanded="<?= $isFirst ? 'true' : 'false' ?>"
                        style="min-height: 50px;"
                    >
                        <div class="d-flex align-items-center justify-content-between w-100 me-2 flex-wrap gap-2">

                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" 
                                     style="width: 32px; height: 32px; background-color: #fff8e1; color: #ffc107;">
                                    <i class="bi bi-box-seam fs-6"></i>
                                </div>
                                <div class="lh-sm">
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

                            <div class="d-flex align-items-center gap-3">
                                <span class="fw-bold text-danger" style="font-size: 1rem;">
                                    <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                </span>
                                <span class="badge <?= $badge['class'] ?> px-2 py-1 rounded-pill shadow-sm fw-medium" style="font-size: 0.75rem;">
                                    <i class="bi <?= $badge['icon'] ?> me-1"></i>
                                    <?= $badge['label'] ?>
                                </span>
                            </div>

                        </div>
                    </button>
                </h2>

                <div id="<?= $collapseId ?>"
                     class="accordion-collapse collapse <?= $isFirst ? 'show' : '' ?>"
                     data-bs-parent="#orderAccordion">

                    <div class="accordion-body p-0">

                        <div class="row g-0">

                            <div class="col-lg-8 border-end">
                                <div class="p-4">
                                    <h6 class="fw-bold text-muted text-uppercase small mb-3">
                                        <i class="bi bi-box-seam me-1"></i>
                                        Sản phẩm (<?= count($items) ?> cuốn)
                                    </h6>

                                    <?php if (empty($items)): ?>
                                        <p class="text-muted small">Không có dữ liệu sản phẩm.</p>
                                    <?php else: ?>
                                        <div class="d-flex flex-column gap-3">
                                            <?php foreach ($items as $item):
                                                $imgPath = '/bookstore/assets/images/books/' . $item['image'];
                                                $imgSrc  = (!empty($item['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
                                                            ? $imgPath
                                                            : '/bookstore/assets/images/books/placeholder.png';
                                            ?>
                                            <div class="d-flex gap-3 align-items-center">
                                                <img
                                                    src="<?= htmlspecialchars($imgSrc) ?>"
                                                    alt="<?= htmlspecialchars($item['title']) ?>"
                                                    class="rounded flex-shrink-0"
                                                    style="width: 52px; height: 70px; object-fit: cover;"
                                                >
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <p class="fw-semibold mb-0 text-truncate" style="font-size: 0.95rem;">
                                                        <?= htmlspecialchars($item['title']) ?>
                                                    </p>
                                                    <p class="text-muted small mb-0">
                                                        <i class="bi bi-person me-1"></i>
                                                        <?= htmlspecialchars($item['author']) ?>
                                                    </p>
                                                    <p class="text-muted small mb-0">
                                                        <?= number_format($item['price'], 0, ',', '.') ?>₫
                                                        × <?= $item['quantity'] ?> cuốn
                                                    </p>
                                                </div>
                                                <span class="fw-bold text-danger flex-shrink-0">
                                                    <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-lg-4 bg-light">
                                <div class="p-4">

                                    <h6 class="fw-bold text-muted text-uppercase small mb-3">
                                        <i class="bi bi-geo-alt me-1"></i>Thông tin giao hàng
                                    </h6>
                                    <ul class="list-unstyled small text-secondary mb-4">
                                        <li class="mb-2">
                                            <i class="bi bi-person-fill me-2 text-warning"></i>
                                            <strong>
                                                <?= htmlspecialchars($order['fullname']) ?>
                                            </strong>
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-telephone-fill me-2 text-warning"></i>
                                            <?= htmlspecialchars($order['phone']) ?>
                                        </li>
                                        <li class="lh-sm">
                                            <i class="bi bi-map-fill me-2 text-warning"></i>
                                            <?= htmlspecialchars($order['address']) ?>
                                        </li>
                                    </ul>

                                    <div class="border-top pt-3">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>Tạm tính</span>
                                            <span>
                                                <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted mb-2">
                                            <span>Phí vận chuyển</span>
                                            <span class="text-success fw-semibold">Miễn phí</span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold">Tổng cộng</span>
                                            <span class="fw-bold text-danger fs-6">
                                                <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-3 text-center">
                                        <span class="badge <?= $badge['class'] ?> px-4 py-2 rounded-pill fs-6 w-100 shadow-sm fw-medium">
                                            <i class="bi <?= $badge['icon'] ?> me-1"></i>
                                            <?= $badge['label'] ?>
                                        </span>
                                    </div>

                                    <?php if (!in_array($order['status'], ['cancelled', 'failed'])): ?>
                                    <div class="mt-4">
                                        <?php
                                        // Xác định bước hiện tại trong tiến trình
                                        $steps        = ['pending', 'confirmed', 'shipping', 'delivered'];
                                        $currentStep  = array_search($order['status'], $steps);
                                        $currentStep  = ($currentStep === false) ? 0 : $currentStep;
                                        $stepLabels   = ['Chờ xác nhận', 'Đã xác nhận', 'Đang giao', 'Hoàn tất'];
                                        $stepIcons    = ['bi-clock', 'bi-check-circle', 'bi-truck', 'bi-bag-check'];
                                        ?>
                                        <div class="order-progress">
                                            <?php foreach ($steps as $i => $step): ?>
                                                <div class="progress-step <?= $i <= $currentStep ? 'done' : 'pending-step' ?>">
                                                    <div class="step-dot">
                                                        <i class="bi <?= $stepIcons[$i] ?>"></i>
                                                    </div>
                                                    <span class="step-label">
                                                        <?= $stepLabels[$i] ?>
                                                    </span>
                                                </div>
                                                <?php if ($i < count($steps) - 1): ?>
                                                    <div class="progress-line <?= $i < $currentStep ? 'done' : '' ?>"></div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                </div>
                            </div></div></div></div></div><?php endforeach; ?>

        </div><?php endif; ?>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>