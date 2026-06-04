<?php
// my_orders.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// ── KIỂM TRA ĐĂNG NHẬP ───────────────────────────────────────────────────────
if (!$isLoggedIn) {
    header('Location: /bookstore/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ── QUERY DANH SÁCH ĐƠN HÀNG CỦA USER ───────────────────────────────────────
// Lấy toàn bộ đơn hàng, sắp xếp mới nhất lên đầu
$stmtOrders = $pdo->prepare("
    SELECT  id,
            fullname,
            phone,
            address,
            total_price,
            status,
            created_at
    FROM    orders
    WHERE   user_id = ?
    ORDER BY id DESC
");
$stmtOrders->execute([$userId]);
$orders = $stmtOrders->fetchAll();

// ── QUERY CHI TIẾT TỪNG ĐƠN HÀNG (sách bên trong) ───────────────────────────
// Dùng 1 query duy nhất lấy hết order_details của user, tránh N+1 query
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

// Nhóm order_details theo order_id để dễ render
// Kết quả: $detailsMap[order_id] = [ [...item1], [...item2], ... ]
$detailsMap = [];
foreach ($allDetails as $detail) {
    $detailsMap[$detail['order_id']][] = $detail;
}

// ── HÀM HELPER: Trả về class badge Bootstrap theo trạng thái đơn hàng ────────
function getStatusBadge(string $status): array {
    return match($status) {
        'pending'   => ['class' => 'bg-warning text-dark',  'icon' => 'bi-clock',               'label' => 'Chờ xác nhận'],
        'confirmed' => ['class' => 'bg-info text-dark',     'icon' => 'bi-check-circle',         'label' => 'Đã xác nhận'],
        'shipping'  => ['class' => 'bg-primary',            'icon' => 'bi-truck',                'label' => 'Đang giao hàng'],
        'delivered' => ['class' => 'bg-success',            'icon' => 'bi-bag-check',            'label' => 'Đã giao hàng'],
        'cancelled' => ['class' => 'bg-danger',             'icon' => 'bi-x-circle',             'label' => 'Đã hủy'],
        default     => ['class' => 'bg-secondary',          'icon' => 'bi-question-circle',      'label' => ucfirst($status)],
    };
}
?>

<!-- ========== NỘI DUNG TRANG ĐƠN HÀNG CỦA TÔI ========== -->
<main class="container my-5">

    <!-- Tiêu đề trang -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h3 class="fw-bold mb-0">
            <i class="bi bi-bag-check me-2 text-warning"></i>Đơn hàng của tôi
        </h3>
        <?php if (!empty($orders)): ?>
            <span class="badge bg-warning text-dark rounded-pill fs-6">
                <?= count($orders) ?> đơn hàng
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($orders)): ?>
        <!-- Trạng thái chưa có đơn hàng nào -->
        <div class="text-center py-5">
            <i class="bi bi-bag-x text-muted" style="font-size: 5rem;"></i>
            <h5 class="text-muted mt-3">Bạn chưa có đơn hàng nào</h5>
            <p class="text-muted small">Hãy chọn sách và đặt hàng ngay!</p>
            <a href="/bookstore/index.php" class="btn btn-warning fw-bold px-4 mt-2">
                <i class="bi bi-book me-2"></i>Khám phá sách ngay
            </a>
        </div>

    <?php else: ?>

        <!-- Timeline / Danh sách đơn hàng dạng Accordion -->
        <div class="accordion accordion-flush order-accordion" id="orderAccordion">

            <?php foreach ($orders as $index => $order):
                $badge   = getStatusBadge($order['status']);
                $items   = $detailsMap[$order['id']] ?? [];
                $collapseId = 'order-' . $order['id'];
                // Đơn đầu tiên (mới nhất) mặc định mở ra
                $isFirst = ($index === 0);
            ?>

            <div class="accordion-item border-0 shadow-sm mb-3 rounded-3 overflow-hidden">

                <!-- ── HEADER ĐƠN HÀNG (luôn hiển thị) ── -->
                <h2 class="accordion-header">
                    <button
                        class="accordion-button <?= $isFirst ? '' : 'collapsed' ?> py-3 px-4"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= $collapseId ?>"
                        aria-expanded="<?= $isFirst ? 'true' : 'false' ?>"
                    >
                        <div class="d-flex align-items-center justify-content-between w-100 me-3 flex-wrap gap-2">

                            <!-- Mã đơn hàng + ngày đặt -->
                            <div>
                                <span class="fw-bold text-dark">
                                    Đơn hàng #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                </span>
                                <?php if (!empty($order['created_at'])): ?>
                                    <span class="text-muted small ms-2">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Tổng tiền + badge trạng thái -->
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-danger">
                                    <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                </span>
                                <span class="badge <?= $badge['class'] ?> px-3 py-2 rounded-pill">
                                    <i class="bi <?= $badge['icon'] ?> me-1"></i>
                                    <?= $badge['label'] ?>
                                </span>
                            </div>

                        </div>
                    </button>
                </h2>

                <!-- ── CHI TIẾT ĐƠN HÀNG (collapsible) ── -->
                <div id="<?= $collapseId ?>"
                     class="accordion-collapse collapse <?= $isFirst ? 'show' : '' ?>"
                     data-bs-parent="#orderAccordion">

                    <div class="accordion-body p-0">

                        <div class="row g-0">

                            <!-- Cột trái: Danh sách sách trong đơn -->
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
                                                <!-- Ảnh bìa -->
                                                <img
                                                    src="<?= htmlspecialchars($imgSrc) ?>"
                                                    alt="<?= htmlspecialchars($item['title']) ?>"
                                                    class="rounded flex-shrink-0"
                                                    style="width: 52px; height: 70px; object-fit: cover;"
                                                >
                                                <!-- Thông tin sách -->
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <p class="fw-semibold mb-0 text-truncate">
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
                                                <!-- Thành tiền -->
                                                <span class="fw-bold text-danger flex-shrink-0">
                                                    <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                                </span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Cột phải: Thông tin giao hàng + tổng tiền -->
                            <div class="col-lg-4 bg-light">
                                <div class="p-4">

                                    <!-- Thông tin giao hàng -->
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
                                        <li>
                                            <i class="bi bi-map-fill me-2 text-warning"></i>
                                            <?= htmlspecialchars($order['address']) ?>
                                        </li>
                                    </ul>

                                    <!-- Tổng tiền -->
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
                                            <span class="fw-bold text-danger fs-5">
                                                <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Trạng thái đơn hàng nổi bật -->
                                    <div class="mt-3 text-center">
                                        <span class="badge <?= $badge['class'] ?> px-4 py-2 rounded-pill fs-6 w-100">
                                            <i class="bi <?= $badge['icon'] ?> me-1"></i>
                                            <?= $badge['label'] ?>
                                        </span>
                                    </div>

                                    <!-- Thanh tiến trình trạng thái -->
                                    <?php if ($order['status'] !== 'cancelled'): ?>
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
                            </div><!-- /.col-lg-4 -->

                        </div><!-- /.row -->
                    </div><!-- /.accordion-body -->
                </div><!-- /.accordion-collapse -->

            </div><!-- /.accordion-item -->
            <?php endforeach; ?>

        </div><!-- /.accordion -->

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>