<?php
// admin/orders.php

// ── 1. LOGIC & BẢO MẬT (PHẢI ĐẶT TRÊN CÙNG) ───────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header('Location: /bookstore/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Các trạng thái hợp lệ
const VALID_STATUSES = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled', 'failed'];

// Các trạng thái đóng băng (Không được phép thay đổi nữa)
// ĐÃ XÓA 'failed' ĐỂ ADMIN CÓ THỂ TIẾP TỤC CẬP NHẬT TRẠNG THÁI LẦN SAU
const LOCKED_STATUSES = ['delivered', 'cancelled'];

// ── ACTION: CẬP TRẠNG THÁI ĐƠN HÀNG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId   = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $newStatus = trim($_POST['status'] ?? '');

    if ($orderId && in_array($newStatus, VALID_STATUSES)) {
        
        // Truy vấn trạng thái hiện tại của đơn hàng để kiểm tra bảo mật
        $stmtStatus = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $stmtStatus->execute([$orderId]);
        $currentStatus = $stmtStatus->fetchColumn();

        // Chặn nếu đơn hàng đã ở trạng thái Đóng băng
        if (in_array($currentStatus, LOCKED_STATUSES)) {
            header('Location: /bookstore/admin/orders.php?msg=locked&page=' . ($_GET['page'] ?? 1));
            exit;
        }

        // Cập nhật trạng thái mới
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $orderId]);
        header('Location: /bookstore/admin/orders.php?msg=updated&page=' . ($_GET['page'] ?? 1) . '&status=' . urlencode($_GET['status'] ?? '') . '&search=' . urlencode($_GET['search'] ?? ''));
    } else {
        header('Location: /bookstore/admin/orders.php?msg=error&page=' . ($_GET['page'] ?? 1));
    }
    exit;
}

// ── BỘ LỌC + TÌM KIẾM
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');

// ── PHÂN TRANG
$perPage     = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// Xây WHERE động dựa theo filter
$whereParts = [];
$whereParams = [];
if ($filterStatus && in_array($filterStatus, VALID_STATUSES)) {
    $whereParts[]  = "o.status = ?";
    $whereParams[] = $filterStatus;
}
if ($search !== '') {
    $whereParts[]  = "(o.fullname LIKE ? OR o.phone LIKE ? OR o.id LIKE ?)";
    $whereParams[] = "%$search%";
    $whereParams[] = "%$search%";
    $whereParams[] = "%$search%";
}
$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Đếm tổng
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM orders o $whereSQL");
$stmtCount->execute($whereParams);
$totalOrders = (int) $stmtCount->fetchColumn();
$totalPages  = (int) ceil($totalOrders / $perPage);

// Lấy danh sách đơn hàng
$stmtOrders = $pdo->prepare("
    SELECT  o.id, o.fullname, o.phone, o.address, o.total_price, o.status, o.created_at, COUNT(od.book_id) AS item_count
    FROM    orders o
    LEFT JOIN order_details od ON od.order_id = o.id
    $whereSQL
    GROUP BY o.id
    ORDER BY o.id DESC
    LIMIT ? OFFSET ?
");
$stmtOrders->execute([...$whereParams, $perPage, $offset]);
$orders = $stmtOrders->fetchAll();

// Thống kê nhanh theo trạng thái
$statsRaw = $pdo->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll();
$stats = array_column($statsRaw, 'cnt', 'status');

// Lấy chi tiết order_details cho Modal
$orderIds = array_column($orders, 'id');
$detailsMap = [];
if (!empty($orderIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmtDetails = $pdo->prepare("
        SELECT  od.order_id, od.quantity, od.price, od.price * od.quantity AS subtotal,
                b.id AS book_id, b.title, b.author, b.image
        FROM    order_details od
        JOIN    books b ON b.id = od.book_id
        WHERE   od.order_id IN ($inPlaceholders)
        ORDER BY b.title ASC
    ");
    $stmtDetails->execute($orderIds);
    foreach ($stmtDetails->fetchAll() as $row) {
        $detailsMap[$row['order_id']][] = $row;
    }
}

// Hàm hỗ trợ render Badge trạng thái
function getStatusMeta(string $status): array {
    return match($status) {
        'pending'   => ['badge' => 'bg-warning text-dark', 'icon' => 'bi-clock',               'label' => 'Chờ xác nhận'],
        'confirmed' => ['badge' => 'bg-info text-dark',    'icon' => 'bi-check-circle',        'label' => 'Đã xác nhận'],
        'shipping'  => ['badge' => 'bg-primary',           'icon' => 'bi-truck',               'label' => 'Đang giao'],
        'delivered' => ['badge' => 'bg-success',           'icon' => 'bi-bag-check',           'label' => 'Đã giao'],
        'cancelled' => ['badge' => 'bg-danger',            'icon' => 'bi-x-circle',            'label' => 'Đã hủy'],
        'failed'    => ['badge' => 'bg-dark',              'icon' => 'bi-exclamation-triangle','label' => 'Giao hàng thất bại'],
        default     => ['badge' => 'bg-secondary',         'icon' => 'bi-question-circle',     'label' => ucfirst($status)],
    };
}

// Map thông báo
$msgMap = [
    'updated' => ['type' => 'success', 'text' => 'Cập nhật trạng thái đơn hàng thành công!', 'icon' => 'book'],
    'error'   => ['type' => 'danger',  'text' => 'Có lỗi xảy ra. Vui lòng thử lại.', 'icon' => 'close-window'],
    'locked'  => ['type' => 'danger',  'text' => 'Đơn hàng này đã được đóng băng, không thể thay đổi trạng thái!', 'icon' => 'close-window'],
];
$msg = $msgMap[$_GET['msg'] ?? ''] ?? null;


// ── 2. GỌI HEADER ADMIN ───────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
    .status-select { min-width: 145px; font-size: .82rem; }
    .modal-book-img { width: 48px; height: 64px; object-fit: cover; border-radius: 6px; }
</style>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show shadow-sm d-flex align-items-center gap-2">
        <img src="https://img.icons8.com/3d-fluency/94/<?= $msg['icon'] ?>.png" style="width: 24px; height: 24px;" alt="Alert">
        <span class="mb-0"><?= $msg['text'] ?></span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['key' => '',           'label' => 'Tất cả',             'icon' => 'shopping-cart', 'color' => 'secondary'],
        ['key' => 'pending',    'label' => 'Chờ xác nhận',       'icon' => 'alarm-clock',   'color' => 'warning'],
        ['key' => 'confirmed',  'label' => 'Đã xác nhận',        'icon' => 'book',          'color' => 'info'],
        ['key' => 'shipping',   'label' => 'Đang giao',          'icon' => 'truck',         'color' => 'primary'],
        ['key' => 'delivered',  'label' => 'Đã giao',            'icon' => 'money-bag',     'color' => 'success'],
        ['key' => 'failed',     'label' => 'Giao hàng thất bại', 'icon' => 'box-important', 'color' => 'dark'],
        ['key' => 'cancelled',  'label' => 'Đã hủy',             'icon' => 'close-window',  'color' => 'danger'],
    ];
    foreach ($statCards as $sc):
        $cnt = $sc['key'] === '' ? $totalOrders : ($stats[$sc['key']] ?? 0);
        if ($sc['key'] === '') $cnt = array_sum($stats);
        $isActive = ($filterStatus === $sc['key']);
    ?>
    <div class="col-6 col-md-4 col-xl">
        <a href="/bookstore/admin/orders.php?status=<?= $sc['key'] ?>&search=<?= urlencode($search) ?>"
           class="card border-0 shadow-sm text-decoration-none <?= $isActive ? 'border border-' . $sc['color'] . ' border-2 bg-light' : '' ?>"
           style="border-radius:12px;">
            <div class="card-body py-3 px-2 text-center">
                <div class="mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:46px;height:46px;">
                    <img src="https://img.icons8.com/3d-fluency/94/<?= $sc['icon'] ?>.png" alt="<?= $sc['label'] ?>" style="width: 100%; height: 100%; object-fit: contain; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">
                </div>
                <div class="fw-bold fs-5 mt-1 text-dark"><?= $cnt ?></div>
                <div class="text-muted" style="font-size:.70rem;"><?= $sc['label'] ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3 px-4">
        <form method="GET" action="" class="d-flex gap-2 align-items-center flex-wrap">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
            <div class="input-group" style="max-width:360px;">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Tìm mã đơn, tên khách, SĐT..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-dark fw-semibold">Tìm kiếm</button>
            <?php if ($search || $filterStatus): ?>
                <a href="/bookstore/admin/orders.php" class="btn btn-outline-secondary"><i class="bi bi-x me-1"></i>Xóa lọc</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0 d-flex align-items-center text-dark">
            <img src="https://img.icons8.com/3d-fluency/94/truck.png" width="26" height="26" class="me-2" alt="Orders">
            Danh sách đơn hàng
            <span class="badge bg-secondary ms-2"><?= $totalOrders ?></span>
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Mã đơn</th>
                        <th>Người nhận</th>
                        <th>Số điện thoại</th>
                        <th class="text-end">Tổng tiền</th>
                        <th class="text-center">SP</th>
                        <th>Ngày đặt</th>
                        <th style="min-width:180px;">Trạng thái</th>
                        <th class="text-center pe-4">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <img src="https://img.icons8.com/3d-fluency/94/box-important.png" style="width: 56px; height: 56px; filter: grayscale(0.5); opacity: 0.8;" class="mb-3 d-block mx-auto" alt="No data">
                            Không có đơn hàng nào phù hợp.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order):
                        $meta = getStatusMeta($order['status']);
                        $isLocked = in_array($order['status'], LOCKED_STATUSES);
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-secondary">
                            #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                        </td>
                        <td>
                            <p class="fw-bold mb-0 text-dark"><?= htmlspecialchars($order['fullname']) ?></p>
                            <p class="text-muted mb-0 small text-truncate" style="max-width:180px;">
                                <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($order['address']) ?>
                            </p>
                        </td>
                        <td><?= htmlspecialchars($order['phone']) ?></td>
                        <td class="text-end fw-bold text-danger">
                            <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary rounded-pill"><?= $order['item_count'] ?></span>
                        </td>
                        <td class="small text-muted">
                            <?php if (!empty($order['created_at'])): ?>
                                <?= date('d/m/Y', strtotime($order['created_at'])) ?><br>
                                <?= date('H:i', strtotime($order['created_at'])) ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isLocked): ?>
                                <div class="d-inline-block">
                                    <span class="badge <?= $meta['badge'] ?> px-3 py-2" style="font-size:.85rem;">
                                        <i class="bi <?= $meta['icon'] ?> me-1"></i><?= $meta['label'] ?>
                                    </span>
                                    <div class="small text-muted mt-2 fw-semibold text-center">
                                        <i class="bi bi-lock-fill me-1"></i>Đã khóa
                                    </div>
                                </div>
                            <?php else: ?>
                                <form method="POST" action="/bookstore/admin/orders.php?page=<?= $currentPage ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>" class="d-flex align-items-center gap-2">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <select name="status" class="form-select form-select-sm status-select">
                                        <?php foreach (VALID_STATUSES as $st): $m = getStatusMeta($st); ?>
                                            <option value="<?= $st ?>" <?= $order['status'] === $st ? 'selected' : '' ?>>
                                                <?= $m['label'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-warning" title="Lưu trạng thái"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <span class="badge <?= $meta['badge'] ?> mt-1"><i class="bi <?= $meta['icon'] ?> me-1"></i><?= $meta['label'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center pe-4">
                            <button type="button" class="btn btn-sm btn-outline-info" title="Xem chi tiết" data-bs-toggle="modal" data-bs-target="#orderModal<?= $order['id'] ?>">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top bg-light">
            <p class="text-muted small mb-0">Hiển thị trang <?= $currentPage ?> / <?= $totalPages ?></p>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php foreach ($orders as $order):
    $meta  = getStatusMeta($order['status']);
    $items = $detailsMap[$order['id']] ?? [];
?>
<div class="modal fade" id="orderModal<?= $order['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center">
                    <img src="https://img.icons8.com/3d-fluency/94/shopping-cart.png" width="28" height="28" class="me-2" alt="Receipt">
                    Chi tiết đơn hàng #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-md-4 bg-light border-end p-4">
                        <h6 class="fw-bold text-muted text-uppercase small mb-3">Thông tin người nhận</h6>
                        <ul class="list-unstyled small">
                            <li class="mb-2"><i class="bi bi-person-fill text-warning me-2"></i><strong><?= htmlspecialchars($order['fullname']) ?></strong></li>
                            <li class="mb-2"><i class="bi bi-telephone-fill text-warning me-2"></i><?= htmlspecialchars($order['phone']) ?></li>
                            <li class="mb-3"><i class="bi bi-map-fill text-warning me-2"></i><?= htmlspecialchars($order['address']) ?></li>
                        </ul>
                        <h6 class="fw-bold text-muted text-uppercase small mb-3 mt-4">Thông tin đơn hàng</h6>
                        <ul class="list-unstyled small text-muted">
                            <li class="mb-2">Ngày đặt: <strong class="text-dark"><?= !empty($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : '—' ?></strong></li>
                            <li class="mb-2">Trạng thái: <span class="badge <?= $meta['badge'] ?> ms-1"><?= $meta['label'] ?></span></li>
                        </ul>
                        <div class="mt-4 p-3 bg-white rounded-3 border text-center">
                            <div class="text-muted small mb-1">Tổng thanh toán</div>
                            <div class="fw-bold fs-4 text-danger"><?= number_format($order['total_price'], 0, ',', '.') ?>₫</div>
                        </div>
                    </div>
                    <div class="col-md-8 p-4">
                        <h6 class="fw-bold text-muted text-uppercase small mb-3">Sản phẩm đã đặt (<?= count($items) ?> loại)</h6>
                        <?php if (empty($items)): ?>
                            <p class="text-muted text-center py-4">Không có dữ liệu sản phẩm.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($items as $item):
                                    $imgPath = '/bookstore/assets/images/books/' . $item['image'];
                                    $imgSrc  = (!empty($item['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath)) ? $imgPath : '/bookstore/assets/images/books/placeholder.png';
                                ?>
                                <div class="d-flex gap-3 align-items-center p-3 rounded-3 border bg-light">
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="modal-book-img flex-shrink-0" alt="Book">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <p class="fw-semibold small mb-0 text-truncate"><?= htmlspecialchars($item['title']) ?></p>
                                        <p class="text-muted small mb-0"><?= number_format($item['price'], 0, ',', '.') ?>₫ × <?= $item['quantity'] ?> cuốn</p>
                                    </div>
                                    <div class="text-end flex-shrink-0"><span class="fw-bold text-danger"><?= number_format($item['subtotal'], 0, ',', '.') ?>₫</span></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php
// ── 4. GỌI FOOTER ADMIN ───────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/admin_footer.php';
?>