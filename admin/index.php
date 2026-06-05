<?php
// admin/index.php

// ── BẮT BUỘC: KIỂM TRA PHÂN QUYỀN ADMIN Ở DÒNG ĐẦU TIÊN ────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header('Location: /bookstore/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// ── QUERY THỐNG KÊ TỔNG QUAN ─────────────────────────────────────────────────

// Tổng số sách
$totalBooks = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();

// Tổng số đơn hàng
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Tổng doanh thu (chỉ tính đơn đã giao thành công)
$totalRevenue = $pdo->query("
    SELECT COALESCE(SUM(total_price), 0)
    FROM   orders
    WHERE  status = 'delivered'
")->fetchColumn();

// Tổng số thành viên (role = 0)
$totalUsers = $pdo->query("
    SELECT COUNT(*) FROM users WHERE role = 0
")->fetchColumn();

// Tổng số đơn đang chờ xử lý
$pendingOrders = $pdo->query("
    SELECT COUNT(*) FROM orders WHERE status = 'pending'
")->fetchColumn();

// Tổng số sách sắp hết hàng (stock <= 5)
$lowStockBooks = $pdo->query("
    SELECT COUNT(*) FROM books WHERE stock_quantity <= 5 AND stock_quantity > 0
")->fetchColumn();

// Tổng số sách hết hàng
$outOfStockBooks = $pdo->query("
    SELECT COUNT(*) FROM books WHERE stock_quantity = 0
")->fetchColumn();

// ── 5 ĐƠN HÀNG MỚI NHẤT (ĐÃ FIX LỖI od.id THÀNH od.book_id) ──────────────────
$recentOrders = $pdo->query("
    SELECT  o.id,
            o.fullname,
            o.total_price,
            o.status,
            o.created_at,
            COUNT(od.book_id) AS item_count
    FROM    orders o
    LEFT JOIN order_details od ON o.id = od.order_id
    GROUP BY o.id
    ORDER BY o.id DESC
    LIMIT 5
")->fetchAll();

// ── 5 SÁCH MỚI NHẤT ĐƯỢC THÊM VÀO ───────────────────────────────────────────
$recentBooks = $pdo->query("
    SELECT  b.id, b.title, b.author, b.price,
            b.stock_quantity, c.name AS category_name
    FROM    books b
    LEFT JOIN categories c ON b.category_id = c.id
    ORDER BY b.id DESC
    LIMIT 5
")->fetchAll();

// Helper badge trạng thái đơn hàng (dùng lại từ my_orders.php)
function getStatusBadge(string $status): array {
    return match($status) {
        'pending'   => ['class' => 'bg-warning text-dark', 'icon' => 'bi-clock',      'label' => 'Chờ xác nhận'],
        'confirmed' => ['class' => 'bg-info text-dark',    'icon' => 'bi-check-circle','label' => 'Đã xác nhận'],
        'shipping'  => ['class' => 'bg-primary',           'icon' => 'bi-truck',       'label' => 'Đang giao'],
        'delivered' => ['class' => 'bg-success',           'icon' => 'bi-bag-check',   'label' => 'Đã giao'],
        'cancelled' => ['class' => 'bg-danger',            'icon' => 'bi-x-circle',    'label' => 'Đã hủy'],
        default     => ['class' => 'bg-secondary',         'icon' => 'bi-question-circle', 'label' => ucfirst($status)],
    };
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Book Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">
    <link href="/bookstore/assets/css/style.css" rel="stylesheet">
    <style>
        /* ── ADMIN LAYOUT ─────────────────────────────────── */
        body { background: #f0f2f5; }

        .admin-sidebar {
            width: 250px;
            min-height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            position: fixed;
            top: 0; left: 0;
            z-index: 1000;
            transition: transform .3s ease;
        }
        .admin-sidebar .sidebar-brand {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,.65);
            padding: .65rem 1.25rem;
            border-radius: 8px;
            margin: 2px .75rem;
            font-size: .9rem;
            transition: all .2s ease;
        }
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            background: rgba(255,193,7,.15);
            color: #ffc107;
        }
        .admin-sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
        .admin-sidebar .nav-section {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(255,255,255,.3);
            padding: 1rem 1.25rem .35rem;
        }

        .admin-main {
            margin-left: 250px;
            min-height: 100vh;
        }
        .admin-topbar {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            padding: .85rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        /* ── STAT CARDS ───────────────────────────────────── */
        .stat-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0,0,0,.12) !important;
        }
        .stat-card .stat-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1.1;
        }

        /* ── TABLE ────────────────────────────────────────── */
        .admin-table thead th {
            background: #f8f9fa;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6c757d;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }

        /* Mobile sidebar toggle */
        @media (max-width: 991.98px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-sidebar.show {
                transform: translateX(0);
            }
            .admin-main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<aside class="admin-sidebar" id="adminSidebar">

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
        <a href="/bookstore/admin/index.php"
           class="nav-link active">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>

        <div class="nav-section">Quản lý</div>
        <a href="/bookstore/admin/books.php" class="nav-link">
            <i class="bi bi-book"></i>Quản lý sách
        </a>
        <a href="/bookstore/admin/categories.php" class="nav-link">
            <i class="bi bi-tags"></i>Thể loại
        </a>
        <a href="/bookstore/admin/orders.php" class="nav-link">
            <i class="bi bi-bag-check"></i>Đơn hàng
            <?php if ($pendingOrders > 0): ?>
                <span class="badge bg-danger rounded-pill ms-auto">
                    <?= $pendingOrders ?>
                </span>
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

<div class="admin-main">

    <div class="admin-topbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none"
                    id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div>
                <h5 class="mb-0 fw-bold">Dashboard</h5>
                <p class="text-muted small mb-0">
                    <i class="bi bi-calendar3 me-1"></i>
                    <?= date('l, d/m/Y') ?>
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="text-end d-none d-sm-block">
                <p class="mb-0 fw-semibold small">
                    <?= htmlspecialchars($_SESSION['fullname']) ?>
                </p>
                <p class="text-muted mb-0" style="font-size:.75rem;">
                    Quản trị viên
                </p>
            </div>
            <div class="rounded-circle bg-warning d-flex align-items-center
                        justify-content-center fw-bold text-dark"
                 style="width:38px;height:38px;font-size:.9rem;">
                <?= strtoupper(mb_substr($_SESSION['fullname'], 0, 1)) ?>
            </div>
        </div>
    </div>

    <div class="p-4">

        <div class="row g-3 mb-4">

            <div class="col-6 col-lg-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 p-4">
                        <div class="stat-icon bg-warning bg-opacity-15">
                            <i class="bi bi-book-fill text-warning"></i>
                        </div>
                        <div>
                            <div class="stat-value text-dark">
                                <?= number_format($totalBooks) ?>
                            </div>
                            <div class="text-muted small">Tổng số sách</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 p-4">
                        <div class="stat-icon bg-primary bg-opacity-15">
                            <i class="bi bi-bag-fill text-primary"></i>
                        </div>
                        <div>
                            <div class="stat-value text-dark">
                                <?= number_format($totalOrders) ?>
                            </div>
                            <div class="text-muted small">Tổng đơn hàng</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 p-4">
                        <div class="stat-icon bg-info bg-opacity-15">
                            <i class="bi bi-people-fill text-info"></i>
                        </div>
                        <div>
                            <div class="stat-value text-dark">
                                <?= number_format($totalUsers) ?>
                            </div>
                            <div class="text-muted small">Thành viên</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3 p-4">
                        <div class="stat-icon bg-success bg-opacity-15">
                            <i class="bi bi-cash-coin text-success"></i>
                        </div>
                        <div>
                            <div class="stat-value text-success" style="font-size:1.4rem;">
                                <?= number_format($totalRevenue / 1000000, 1) ?>M
                            </div>
                            <div class="text-muted small">Doanh thu (₫)</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row g-3 mb-4">

            <?php if ($pendingOrders > 0): ?>
            <div class="col-md-4">
                <div class="alert alert-warning d-flex align-items-center gap-3 mb-0 rounded-3">
                    <i class="bi bi-clock-history fs-4 flex-shrink-0"></i>
                    <div>
                        <strong><?= $pendingOrders ?> đơn hàng</strong> đang chờ xác nhận.
                        <a href="/bookstore/admin/orders.php"
                           class="alert-link d-block small">Xử lý ngay →</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($lowStockBooks > 0): ?>
            <div class="col-md-4">
                <div class="alert alert-info d-flex align-items-center gap-3 mb-0 rounded-3">
                    <i class="bi bi-exclamation-triangle fs-4 flex-shrink-0"></i>
                    <div>
                        <strong><?= $lowStockBooks ?> đầu sách</strong> sắp hết hàng (≤5 cuốn).
                        <a href="/bookstore/admin/books.php"
                           class="alert-link d-block small">Kiểm tra →</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($outOfStockBooks > 0): ?>
            <div class="col-md-4">
                <div class="alert alert-danger d-flex align-items-center gap-3 mb-0 rounded-3">
                    <i class="bi bi-x-circle fs-4 flex-shrink-0"></i>
                    <div>
                        <strong><?= $outOfStockBooks ?> đầu sách</strong> đã hết hàng.
                        <a href="/bookstore/admin/books.php"
                           class="alert-link d-block small">Cập nhật →</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <div class="row g-3 mb-4">
            <?php
            $shortcuts = [
                ['href' => '/bookstore/admin/books.php',      'icon' => 'bi-book',        'color' => 'warning', 'label' => 'Quản lý sách',    'desc' => 'Thêm, sửa, xóa sách'],
                ['href' => '/bookstore/admin/categories.php', 'icon' => 'bi-tags',        'color' => 'info',    'label' => 'Thể loại',         'desc' => 'Quản lý thể loại sách'],
                ['href' => '/bookstore/admin/orders.php',     'icon' => 'bi-bag-check',   'color' => 'primary', 'label' => 'Quản lý đơn hàng','desc' => 'Duyệt & cập nhật đơn'],
                ['href' => '/bookstore/admin/users.php',      'icon' => 'bi-people',      'color' => 'success', 'label' => 'Thành viên',       'desc' => 'Danh sách người dùng'],
            ];
            foreach ($shortcuts as $s): ?>
            <div class="col-6 col-lg-3">
                <a href="<?= $s['href'] ?>"
                   class="card border-0 shadow-sm text-decoration-none stat-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="mx-auto mb-3 rounded-circle bg-<?= $s['color'] ?>
                                    bg-opacity-15 d-flex align-items-center
                                    justify-content-center"
                             style="width:56px;height:56px;">
                            <i class="bi <?= $s['icon'] ?> text-<?= $s['color'] ?> fs-4"></i>
                        </div>
                        <p class="fw-bold text-dark mb-1"><?= $s['label'] ?></p>
                        <p class="text-muted small mb-0"><?= $s['desc'] ?></p>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom d-flex
                                align-items-center justify-content-between py-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-clock-history me-2 text-warning"></i>
                            Đơn hàng mới nhất
                        </h6>
                        <a href="/bookstore/admin/orders.php"
                           class="btn btn-sm btn-outline-warning">
                            Xem tất cả
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Mã đơn</th>
                                        <th>Khách hàng</th>
                                        <th class="text-center">SP</th>
                                        <th class="text-end">Tổng tiền</th>
                                        <th class="text-center pe-4">Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($recentOrders)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            Chưa có đơn hàng nào.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order):
                                        $badge = getStatusBadge($order['status']);
                                    ?>
                                    <tr>
                                        <td class="ps-4 fw-semibold">
                                            <a href="/bookstore/admin/orders.php"
                                               class="text-dark text-decoration-none">
                                                #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="fw-semibold small">
                                                <?= htmlspecialchars($order['fullname']) ?>
                                            </span>
                                            <?php if (!empty($order['created_at'])): ?>
                                            <br>
                                            <span class="text-muted" style="font-size:.75rem;">
                                                <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary rounded-pill">
                                                <?= $order['item_count'] ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold text-danger small">
                                            <?= number_format($order['total_price'], 0, ',', '.') ?>₫
                                        </td>
                                        <td class="text-center pe-4">
                                            <span class="badge <?= $badge['class'] ?> rounded-pill px-2 py-1"
                                                  style="font-size:.72rem;">
                                                <i class="bi <?= $badge['icon'] ?> me-1"></i>
                                                <?= $badge['label'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom d-flex
                                align-items-center justify-content-between py-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-book me-2 text-warning"></i>
                            Sách mới thêm vào
                        </h6>
                        <a href="/bookstore/admin/books.php"
                           class="btn btn-sm btn-outline-warning">
                            Quản lý
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                        <?php if (empty($recentBooks)): ?>
                            <li class="list-group-item text-center text-muted py-4">
                                Chưa có sách nào.
                            </li>
                        <?php else: ?>
                            <?php foreach ($recentBooks as $book): ?>
                            <li class="list-group-item px-4 py-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <p class="fw-semibold small mb-0 text-truncate">
                                            <?= htmlspecialchars($book['title']) ?>
                                        </p>
                                        <p class="text-muted mb-0" style="font-size:.78rem;">
                                            <?= htmlspecialchars($book['author']) ?>
                                            <?php if (!empty($book['category_name'])): ?>
                                                · <span class="text-warning">
                                                    <?= htmlspecialchars($book['category_name']) ?>
                                                  </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="text-end flex-shrink-0">
                                        <p class="fw-bold text-danger small mb-0">
                                            <?= number_format($book['price'], 0, ',', '.') ?>₫
                                        </p>
                                        <?php if ($book['stock_quantity'] == 0): ?>
                                            <span class="badge bg-danger-subtle text-danger"
                                                  style="font-size:.68rem;">Hết hàng</span>
                                        <?php elseif ($book['stock_quantity'] <= 5): ?>
                                            <span class="badge bg-warning-subtle text-warning"
                                                  style="font-size:.68rem;">
                                                Còn <?= $book['stock_quantity'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success"
                                                  style="font-size:.68rem;">
                                                Còn <?= $book['stock_quantity'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

        </div></div></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle sidebar trên mobile
    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
        document.getElementById('adminSidebar').classList.toggle('show');
    });
</script>
</body>
</html>