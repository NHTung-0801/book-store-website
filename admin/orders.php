<?php
// admin/orders.php

// ── KIỂM TRA PHÂN QUYỀN ADMIN ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header('Location: /bookstore/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// ── CÁC TRẠNG THÁI HỢP LỆ ────────────────────────────────────────────────────
const VALID_STATUSES = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];

// ── ACTION: CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])
    && $_POST['action'] === 'update_status') {

    $orderId   = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $newStatus = trim($_POST['status'] ?? '');

    if ($orderId && in_array($newStatus, VALID_STATUSES)) {
        $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")
            ->execute([$newStatus, $orderId]);
        header('Location: /bookstore/admin/orders.php?msg=updated&page=' . ($_GET['page'] ?? 1));
    } else {
        header('Location: /bookstore/admin/orders.php?msg=error&page=' . ($_GET['page'] ?? 1));
    }
    exit;
}

// ── BỘ LỌC + TÌM KIẾM ────────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');

// ── PHÂN TRANG ────────────────────────────────────────────────────────────────
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

// Lấy danh sách đơn hàng kèm số lượng sản phẩm
$stmtOrders = $pdo->prepare("
    SELECT  o.id,
            o.fullname,
            o.phone,
            o.address,
            o.total_price,
            o.status,
            o.created_at,
            COUNT(od.id) AS item_count
    FROM    orders o
    LEFT JOIN order_details od ON od.order_id = o.id
    $whereSQL
    GROUP BY o.id
    ORDER BY o.id DESC
    LIMIT ? OFFSET ?
");
$stmtOrders->execute([...$whereParams, $perPage, $offset]);
$orders = $stmtOrders->fetchAll();

// ── THỐNG KÊ NHANH THEO TRẠNG THÁI ───────────────────────────────────────────
$statsRaw = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status
")->fetchAll();
$stats = array_column($statsRaw, 'cnt', 'status');

// ── QUERY CHI TIẾT ĐƠN HÀNG CHO MODAL ───────────────────────────────────────
// Lấy toàn bộ order_details của trang hiện tại để render modal mà không cần AJAX
$orderIds = array_column($orders, 'id');
$detailsMap = [];
if (!empty($orderIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmtDetails = $pdo->prepare("
        SELECT  od.order_id,
                od.quantity,
                od.price,
                od.price * od.quantity AS subtotal,
                b.id        AS book_id,
                b.title,
                b.author,
                b.image
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

// ── HELPER: badge + label theo trạng thái ────────────────────────────────────
function getStatusMeta(string $status): array {
    return match($status) {
        'pending'   => ['badge' => 'bg-warning text-dark', 'icon' => 'bi-clock',        'label' => 'Chờ xác nhận'],
        'confirmed' => ['badge' => 'bg-info text-dark',    'icon' => 'bi-check-circle',  'label' => 'Đã xác nhận'],
        'shipping'  => ['badge' => 'bg-primary',           'icon' => 'bi-truck',         'label' => 'Đang giao'],
        'delivered' => ['badge' => 'bg-success',           'icon' => 'bi-bag-check',     'label' => 'Đã giao'],
        'cancelled' => ['badge' => 'bg-danger',            'icon' => 'bi-x-circle',      'label' => 'Đã hủy'],
        default     => ['badge' => 'bg-secondary',         'icon' => 'bi-question-circle','label' => ucfirst($status)],
    };
}

// ── THÔNG BÁO REDIRECT ────────────────────────────────────────────────────────
$msgMap = [
    'updated' => ['type' => 'success', 'text' => 'Cập nhật trạng thái đơn hàng thành công!'],
    'error'   => ['type' => 'danger',  'text' => 'Có lỗi xảy ra. Vui lòng thử lại.'],
];
$msg = $msgMap[$_GET['msg'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng — Book Store Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .admin-sidebar {
            width: 250px; min-height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            position: fixed; top: 0; left: 0; z-index: 1000;
        }
        .admin-sidebar .sidebar-brand {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,.65); padding: .65rem 1.25rem;
            border-radius: 8px; margin: 2px .75rem;
            font-size: .9rem; transition: all .2s ease;
        }
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            background: rgba(255,193,7,.15); color: #ffc107;
        }
        .admin-sidebar .nav-link i { width: 20px; text-align: center; margin-right: 8px; }
        .admin-sidebar .nav-section {
            font-size: .7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .08em; color: rgba(255,255,255,.3);
            padding: 1rem 1.25rem .35rem;
        }
        .admin-main  { margin-left: 250px; min-height: 100vh; }
        .admin-topbar {
            background: #fff; border-bottom: 1px solid #e9ecef;
            padding: .85rem 1.5rem; position: sticky; top: 0; z-index: 999;
        }
        .admin-table thead th {
            background: #f8f9fa; font-size: .78rem;
            text-transform: uppercase; letter-spacing: .05em;
            color: #6c757d; border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }
        /* Ẩn spinner mặc định của input number */
        .status-select { min-width: 145px; font-size: .82rem; }

        /* Modal chi tiết */
        .modal-book-img {
            width: 48px; height: 64px;
            object-fit: cover; border-radius: 6px;
        }
        .filter-tab {
            cursor: pointer; border-bottom: 3px solid transparent;
            padding: .4rem .8rem; font-size: .85rem;
            color: #6c757d; text-decoration: none;
            transition: all .2s;
        }
        .filter-tab:hover  { color: #000; }
        .filter-tab.active { border-bottom-color: #ffc107; color: #000; font-weight: 600; }
    </style>
</head>
<body>

<!-- ══ SIDEBAR ══════════════════════════════════════════════ -->
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <a href="/bookstore/admin/index.php"
           class="text-decoration-none d-flex align-items-center gap-2">
            <i class="bi bi-book-half text-warning fs-4"></i>
            <div>
                <div class="text-white fw-bold lh-1">Book Store</div>
                <div class="text-warning" style="font-size:.7rem;">Admin Panel</div>
            </div>
        </a>
    </div>
    <nav class="mt-2 pb-4">
        <div class="nav-section">Tổng quan</div>
        <a href="/bookstore/admin/index.php" class="nav-link">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>
        <div class="nav-section">Quản lý</div>
        <a href="/bookstore/admin/books.php" class="nav-link">
            <i class="bi bi-book"></i>Quản lý sách
        </a>
        <a href="/bookstore/admin/categories.php" class="nav-link">
            <i class="bi bi-tags"></i>Thể loại
        </a>
        <a href="/bookstore/admin/orders.php" class="nav-link active">
            <i class="bi bi-bag-check"></i>Đơn hàng
            <?php $pendingCount = $stats['pending'] ?? 0; ?>
            <?php if ($pendingCount > 0): ?>
                <span class="badge bg-danger rounded-pill ms-auto"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <a href="/bookstore/admin/users.php" class="nav-link">
            <i class="bi bi-people"></i>Thành viên
        </a>
        <div class="nav-section">Hệ thống</div>
        <a href="/bookstore/index.php" class="nav-link" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i>Xem website
        </a>
        <a href="/bookstore/logout.php" class="nav-link text-danger-emphasis">
            <i class="bi bi-box-arrow-right"></i>Đăng xuất
        </a>
    </nav>
</aside>

<!-- ══ MAIN ══════════════════════════════════════════════════ -->
<div class="admin-main">

    <!-- Topbar -->
    <div class="admin-topbar d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-bold">
            <i class="bi bi-bag-check me-2 text-warning"></i>Quản lý đơn hàng
        </h5>
        <span class="text-muted small">
            <?= htmlspecialchars($_SESSION['fullname']) ?> · Quản trị viên
        </span>
    </div>

    <div class="p-4">

        <!-- Thông báo -->
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?= $msg['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ── STAT CARDS TRẠNG THÁI ── -->
        <div class="row g-3 mb-4">
            <?php
            $statCards = [
                ['key' => '',           'label' => 'Tất cả',       'icon' => 'bi-list-ul',     'color' => 'secondary'],
                ['key' => 'pending',    'label' => 'Chờ xác nhận', 'icon' => 'bi-clock',       'color' => 'warning'],
                ['key' => 'confirmed',  'label' => 'Đã xác nhận',  'icon' => 'bi-check-circle','color' => 'info'],
                ['key' => 'shipping',   'label' => 'Đang giao',    'icon' => 'bi-truck',       'color' => 'primary'],
                ['key' => 'delivered',  'label' => 'Đã giao',      'icon' => 'bi-bag-check',   'color' => 'success'],
                ['key' => 'cancelled',  'label' => 'Đã hủy',       'icon' => 'bi-x-circle',    'color' => 'danger'],
            ];
            foreach ($statCards as $sc):
                $cnt = $sc['key'] === ''
                     ? $totalOrders  // nếu đang filter thì tổng = filtered, nên dùng tổng thực
                     : ($stats[$sc['key']] ?? 0);
                // Tổng tất cả tính lại từ stats
                if ($sc['key'] === '') {
                    $cnt = array_sum($stats);
                }
                $isActive = ($filterStatus === $sc['key']);
            ?>
            <div class="col-6 col-md-4 col-xl-2">
                <a href="/bookstore/admin/orders.php?status=<?= $sc['key'] ?>&search=<?= urlencode($search) ?>"
                   class="card border-0 shadow-sm text-decoration-none
                          <?= $isActive ? 'border border-' . $sc['color'] . ' border-2' : '' ?>"
                   style="border-radius:12px; overflow:hidden;">
                    <div class="card-body py-3 px-3 text-center">
                        <i class="bi <?= $sc['icon'] ?> text-<?= $sc['color'] ?> fs-4"></i>
                        <div class="fw-bold fs-5 mt-1"><?= $cnt ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= $sc['label'] ?></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── THANH TÌM KIẾM ── -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body py-3 px-4">
                <form method="GET" action="" class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                    <div class="input-group" style="max-width:360px;">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" name="search"
                               class="form-control"
                               placeholder="Tìm mã đơn, tên khách, SĐT..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn btn-warning fw-semibold">
                        Tìm kiếm
                    </button>
                    <?php if ($search || $filterStatus): ?>
                        <a href="/bookstore/admin/orders.php"
                           class="btn btn-outline-secondary">
                            <i class="bi bi-x me-1"></i>Xóa lọc
                        </a>
                    <?php endif; ?>
                    <span class="text-muted small ms-auto">
                        Tìm thấy <strong><?= $totalOrders ?></strong> đơn hàng
                    </span>
                </form>
            </div>
        </div>

        <!-- ── BẢNG DANH SÁCH ĐƠN HÀNG ── -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width:90px;">Mã đơn</th>
                                <th>Người nhận</th>
                                <th style="width:130px;">Số điện thoại</th>
                                <th class="text-end" style="width:130px;">Tổng tiền</th>
                                <th class="text-center" style="width:60px;">SP</th>
                                <th style="width:130px;">Ngày đặt</th>
                                <th style="width:260px;">Trạng thái</th>
                                <th class="text-center pe-4" style="width:90px;">Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                    Không có đơn hàng nào phù hợp.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order):
                                $meta = getStatusMeta($order['status']);
                            ?>
                            <tr>
                                <!-- Mã đơn -->
                                <td class="ps-4 fw-bold text-muted small">
                                    #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                </td>

                                <!-- Người nhận + địa chỉ -->
                                <td>
                                    <p class="fw-semibold small mb-0">
                                        <?= htmlspecialchars($order['fullname']) ?>
                                    </p>
                                    <p class="text-muted mb-0 text-truncate"
                                       style="font-size:.75rem; max-width:180px;"
                                       title="<?= htmlspecialchars($order['address']) ?>">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        <?= htmlspecialchars($order['address']) ?>
                                    </p>
                                </td>

                                <!-- SĐT -->
                                <td class="small">
                                    <a href="tel:<?= htmlspecialchars($order['phone']) ?>"
                                       class="text-dark text-decoration-none">
                                        <?= htmlspecialchars($order['phone']) ?>
                                    </a>
                                </td>

                                <!-- Tổng tiền -->
                                <td class="text-end fw-bold text-danger small">
                                    <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                </td>

                                <!-- Số sản phẩm -->
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill">
                                        <?= $order['item_count'] ?>
                                    </span>
                                </td>

                                <!-- Ngày đặt -->
                                <td class="small text-muted">
                                    <?php if (!empty($order['created_at'])): ?>
                                        <span><?= date('d/m/Y', strtotime($order['created_at'])) ?></span><br>
                                        <span style="font-size:.72rem;">
                                            <?= date('H:i', strtotime($order['created_at'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Cột trạng thái: badge + form inline update -->
                                <td>
                                    <form method="POST"
                                          action="/bookstore/admin/orders.php?page=<?= $currentPage ?>"
                                          class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="action"   value="update_status">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

                                        <select name="status"
                                                class="form-select form-select-sm status-select">
                                            <?php foreach (VALID_STATUSES as $st):
                                                $m = getStatusMeta($st);
                                            ?>
                                                <option value="<?= $st ?>"
                                                    <?= $order['status'] === $st ? 'selected' : '' ?>>
                                                    <?= $m['label'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit"
                                                class="btn btn-sm btn-warning fw-semibold flex-shrink-0"
                                                title="Lưu trạng thái">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>

                                    <!-- Badge hiện trạng thái hiện tại -->
                                    <span class="badge <?= $meta['badge'] ?> mt-1 px-2 py-1"
                                          style="font-size:.7rem;">
                                        <i class="bi <?= $meta['icon'] ?> me-1"></i>
                                        <?= $meta['label'] ?>
                                    </span>
                                </td>

                                <!-- Nút Xem chi tiết → mở Modal -->
                                <td class="text-center pe-4">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-info"
                                            title="Xem chi tiết đơn hàng"
                                            data-bs-toggle="modal"
                                            data-bs-target="#orderModal<?= $order['id'] ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div><!-- /.table-responsive -->

                <!-- Phân trang -->
                <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                    <p class="text-muted small mb-0">
                        Hiển thị <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalOrders) ?>
                        trong <?= $totalOrders ?> đơn hàng
                    </p>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link"
                                   href="?page=<?= $currentPage - 1 ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link"
                                       href="?page=<?= $p ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link"
                                   href="?page=<?= $currentPage + 1 ?>&status=<?= urlencode($filterStatus) ?>&search=<?= urlencode($search) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>

            </div><!-- /.card-body -->
        </div><!-- /.card -->

    </div><!-- /.p-4 -->
</div><!-- /.admin-main -->

<!-- ══════════════════════════════════════════════════════════
     MODALS CHI TIẾT ĐƠN HÀNG
     Render sẵn tất cả modal của trang hiện tại — dùng dữ liệu
     đã query từ trước, không cần AJAX
══════════════════════════════════════════════════════════ -->
<?php foreach ($orders as $order):
    $meta  = getStatusMeta($order['status']);
    $items = $detailsMap[$order['id']] ?? [];
?>
<div class="modal fade" id="orderModal<?= $order['id'] ?>"
     tabindex="-1" aria-labelledby="modalLabel<?= $order['id'] ?>"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="modalLabel<?= $order['id'] ?>">
                    <i class="bi bi-receipt me-2 text-warning"></i>
                    Chi tiết đơn hàng #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-0">
                <div class="row g-0">

                    <!-- Cột trái: thông tin khách + giao hàng -->
                    <div class="col-md-4 bg-light border-end p-4">
                        <h6 class="fw-bold text-muted text-uppercase small mb-3">
                            <i class="bi bi-person me-1"></i>Thông tin người nhận
                        </h6>
                        <ul class="list-unstyled small">
                            <li class="mb-2">
                                <i class="bi bi-person-fill me-2 text-warning"></i>
                                <strong><?= htmlspecialchars($order['fullname']) ?></strong>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-telephone-fill me-2 text-warning"></i>
                                <?= htmlspecialchars($order['phone']) ?>
                            </li>
                            <li class="mb-3">
                                <i class="bi bi-map-fill me-2 text-warning"></i>
                                <?= htmlspecialchars($order['address']) ?>
                            </li>
                        </ul>

                        <h6 class="fw-bold text-muted text-uppercase small mb-3 mt-4">
                            <i class="bi bi-info-circle me-1"></i>Thông tin đơn hàng
                        </h6>
                        <ul class="list-unstyled small text-muted">
                            <li class="mb-2">
                                Ngày đặt:
                                <strong class="text-dark">
                                    <?= !empty($order['created_at'])
                                        ? date('d/m/Y H:i', strtotime($order['created_at']))
                                        : '—' ?>
                                </strong>
                            </li>
                            <li class="mb-2">
                                Trạng thái:
                                <span class="badge <?= $meta['badge'] ?> ms-1">
                                    <i class="bi <?= $meta['icon'] ?> me-1"></i>
                                    <?= $meta['label'] ?>
                                </span>
                            </li>
                            <li>
                                Số sản phẩm:
                                <strong class="text-dark"><?= count($items) ?> loại</strong>
                            </li>
                        </ul>

                        <!-- Tổng tiền nổi bật -->
                        <div class="mt-4 p-3 bg-white rounded-3 border text-center">
                            <div class="text-muted small mb-1">Tổng thanh toán</div>
                            <div class="fw-bold fs-4 text-danger">
                                <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                            </div>
                            <div class="text-success small">
                                <i class="bi bi-truck me-1"></i>Miễn phí vận chuyển
                            </div>
                        </div>
                    </div>

                    <!-- Cột phải: danh sách sách đã mua -->
                    <div class="col-md-8 p-4">
                        <h6 class="fw-bold text-muted text-uppercase small mb-3">
                            <i class="bi bi-box-seam me-1"></i>
                            Sản phẩm đã đặt (<?= count($items) ?> loại)
                        </h6>

                        <?php if (empty($items)): ?>
                            <p class="text-muted text-center py-4">
                                Không có dữ liệu sản phẩm.
                            </p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($items as $item):
                                    $imgPath = '/bookstore/assets/images/books/' . $item['image'];
                                    $imgSrc  = (!empty($item['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
                                                ? $imgPath
                                                : '/bookstore/assets/images/books/placeholder.png';
                                ?>
                                <div class="d-flex gap-3 align-items-center
                                            p-3 rounded-3 border bg-light">
                                    <!-- Ảnh bìa -->
                                    <img src="<?= htmlspecialchars($imgSrc) ?>"
                                         alt="<?= htmlspecialchars($item['title']) ?>"
                                         class="modal-book-img flex-shrink-0">

                                    <!-- Thông tin sách -->
                                    <div class="flex-grow-1 overflow-hidden">
                                        <p class="fw-semibold small mb-0 text-truncate">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </p>
                                        <p class="text-muted mb-1" style="font-size:.78rem;">
                                            <i class="bi bi-person me-1"></i>
                                            <?= htmlspecialchars($item['author']) ?>
                                        </p>
                                        <p class="text-muted small mb-0">
                                            <!-- Đơn giá tại thời điểm mua (lưu trong order_details.price) -->
                                            <?= number_format($item['price'], 0, ',', '.') ?>₫
                                            × <?= $item['quantity'] ?> cuốn
                                        </p>
                                    </div>

                                    <!-- Thành tiền -->
                                    <div class="text-end flex-shrink-0">
                                        <span class="fw-bold text-danger">
                                            <?= number_format($item['subtotal'], 0, ',', '.') ?>₫
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Tổng cộng dưới danh sách -->
                            <div class="d-flex justify-content-between
                                        align-items-center mt-3 pt-3 border-top">
                                <span class="text-muted small">
                                    Tổng <?= array_sum(array_column($items, 'quantity')) ?> cuốn sách
                                </span>
                                <span class="fw-bold text-danger fs-5">
                                    <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                </div><!-- /.row -->
            </div><!-- /.modal-body -->

            <!-- Footer -->
            <div class="modal-footer bg-light">
                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i>Đóng
                </button>
                <!-- Nút in đơn hàng (in trang hiện tại) -->
                <button type="button"
                        class="btn btn-outline-dark"
                        onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>In đơn
                </button>
            </div>

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmArFmcZZm7MFEBp3VLFHnFX8oH"
        crossorigin="anonymous"></script>
</body>
</html>