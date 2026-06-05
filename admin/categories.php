<?php
// admin/categories.php

// ── KIỂM TRA PHÂN QUYỀN ADMIN ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header('Location: /bookstore/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$errors   = [];
$editCat  = null; // Dữ liệu thể loại đang được sửa (nếu có)

// ════════════════════════════════════════════════════════════
// XỬ LÝ CÁC ACTION
// ════════════════════════════════════════════════════════════

// Lấy action hiện tại (mặc định là 'list' - hiển thị danh sách)
$action = $_GET['action'] ?? 'list';
$editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ── ACTION: XÓA THỂ LOẠI ─────────────────────────────────────────────────────
if ($action === 'delete' && $editId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra thể loại có sách nào đang dùng không — tránh orphan data
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM books WHERE category_id = ?");
    $stmtCheck->execute([$editId]);
    $bookCount = (int) $stmtCheck->fetchColumn();

    if ($bookCount > 0) {
        // Không cho xóa nếu vẫn còn sách thuộc thể loại này
        header("Location: /bookstore/admin/categories.php?msg=has_books&count={$bookCount}");
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$editId]);
        header('Location: /bookstore/admin/categories.php?msg=deleted');
    }
    exit;
}

// ── ACTION: THÊM MỚI HOẶC CẬP NHẬT (POST) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {

    // 1. LẤY VÀ LÀM SẠCH DỮ LIỆU
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $postEditId  = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);

    // 2. VALIDATE
    if (empty($name)) {
        $errors['name'] = 'Tên thể loại không được để trống.';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Tên thể loại tối đa 100 ký tự.';
    }

    // Kiểm tra tên thể loại đã tồn tại chưa (loại trừ chính nó khi sửa)
    if (empty($errors['name'])) {
        $stmtDup = $pdo->prepare("
            SELECT id FROM categories
            WHERE  name = ? AND id != ?
            LIMIT  1
        ");
        $stmtDup->execute([$name, $postEditId ?: 0]);
        if ($stmtDup->fetch()) {
            $errors['name'] = 'Tên thể loại này đã tồn tại.';
        }
    }

    // 3. LƯU VÀO DATABASE NẾU KHÔNG CÓ LỖI
    if (empty($errors)) {
        if ($postEditId) {
            // Cập nhật thể loại
            $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?")
                ->execute([$name, $description, $postEditId]);
            header('Location: /bookstore/admin/categories.php?msg=updated');
        } else {
            // Thêm mới thể loại
            $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)")
                ->execute([$name, $description]);
            header('Location: /bookstore/admin/categories.php?msg=added');
        }
        exit;
    }
}

// ── ACTION: LẤY DỮ LIỆU ĐỂ HIỂN THỊ FORM SỬA ─────────────────────────────────
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editCat = $stmt->fetch();
    if (!$editCat) {
        header('Location: /bookstore/admin/categories.php?msg=notfound');
        exit;
    }
}

// ── LẤY DANH SÁCH THỂ LOẠI KÈM SỐ LƯỢNG SÁCH (cho giao diện List) ────────────
$categories = [];
if ($action === 'list') {
    $categories = $pdo->query("
        SELECT   c.id, c.name, c.description, COUNT(b.id) AS book_count
        FROM     categories c
        LEFT JOIN books b ON b.category_id = c.id
        GROUP BY c.id, c.name, c.description
        ORDER BY c.name ASC
    ")->fetchAll();
}

// ── MAP THÔNG BÁO REDIRECT ────────────────────────────────────────────────────
$msgKey  = $_GET['msg']   ?? '';
$bkCount = (int)($_GET['count'] ?? 0);
$msgMap  = [
    'added'     => ['type' => 'success', 'text' => 'Thêm thể loại mới thành công!'],
    'updated'   => ['type' => 'success', 'text' => 'Cập nhật thể loại thành công!'],
    'deleted'   => ['type' => 'warning', 'text' => 'Đã xóa thể loại khỏi hệ thống.'],
    'notfound'  => ['type' => 'danger',  'text' => 'Không tìm thấy thể loại cần sửa.'],
    'has_books' => ['type' => 'danger',  'text' => "Không thể xóa! Thể loại này đang chứa {$bkCount} cuốn sách. Hãy chuyển hoặc xóa sách trước."],
];
$msg = $msgMap[$msgKey] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý thể loại — Book Store Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        body { background: #f0f2f5; }

        /* ── SIDEBAR ───────── */
        .admin-sidebar {
            width: 250px; min-height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            position: fixed; top: 0; left: 0; z-index: 1000;
            transition: transform .3s ease;
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

        /* ── TABLE ──────────────────────────────────────── */
        .admin-table thead th {
            background: #f8f9fa; font-size: .78rem;
            text-transform: uppercase; letter-spacing: .05em;
            color: #6c757d; border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }

        /* Mobile Sidebar Toggle */
        @media (max-width: 991.98px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.show { transform: translateX(0); }
            .admin-main { margin-left: 0; }
        }
    </style>
</head>
<body>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <a href="/bookstore/admin/index.php" class="text-decoration-none d-flex align-items-center gap-2">
            <i class="bi bi-book-half text-warning fs-4"></i>
            <div>
                <div class="text-white fw-bold lh-1">Book Store</div>
                <div class="text-warning" style="font-size:.7rem;">Admin Panel</div>
            </div>
        </a>
    </div>
    <nav class="mt-2 pb-4">
        <div class="nav-section">Tổng quan</div>
        <a href="/bookstore/admin/index.php" class="nav-link"><i class="bi bi-speedometer2"></i>Dashboard</a>
        <div class="nav-section">Quản lý</div>
        <a href="/bookstore/admin/books.php" class="nav-link"><i class="bi bi-book"></i>Quản lý sách</a>
        <a href="/bookstore/admin/categories.php" class="nav-link active"><i class="bi bi-tags"></i>Thể loại</a>
        <a href="/bookstore/admin/orders.php" class="nav-link"><i class="bi bi-bag-check"></i>Đơn hàng</a>
        <a href="/bookstore/admin/users.php" class="nav-link"><i class="bi bi-people"></i>Thành viên</a>
        <div class="nav-section">Hệ thống</div>
        <a href="/bookstore/index.php" class="nav-link" target="_blank"><i class="bi bi-box-arrow-up-right"></i>Xem website</a>
        <a href="/bookstore/logout.php" class="nav-link text-danger-emphasis"><i class="bi bi-box-arrow-right"></i>Đăng xuất</a>
    </nav>
</aside>

<div class="admin-main">

    <div class="admin-topbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle"><i class="bi bi-list fs-5"></i></button>
            <h5 class="mb-0 fw-bold"><i class="bi bi-tags me-2 text-warning"></i>Quản lý thể loại</h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="text-end d-none d-sm-block">
                <p class="mb-0 fw-semibold small"><?= htmlspecialchars($_SESSION['fullname']) ?></p>
                <p class="text-muted mb-0" style="font-size:.75rem;">Quản trị viên</p>
            </div>
            <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center fw-bold text-dark" style="width:38px;height:38px;font-size:.9rem;">
                <?= strtoupper(mb_substr($_SESSION['fullname'], 0, 1)) ?>
            </div>
        </div>
    </div>

    <div class="p-4">

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show d-flex align-items-start gap-2 shadow-sm">
                <i class="bi bi-<?= $msg['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill flex-shrink-0 mt-1"></i>
                <span><?= $msg['text'] ?></span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action === 'add' || $action === 'edit'): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0 text-primary">
                                <i class="bi bi-<?= $editCat ? 'pencil-square' : 'plus-circle' ?> me-2"></i>
                                <?= $editCat ? 'Cập nhật thể loại' : 'Thêm thể loại mới' ?>
                            </h6>
                            <a href="/bookstore/admin/categories.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
                            </a>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="/bookstore/admin/categories.php?action=<?= $action ?><?= $editCat ? '&id=' . $editCat['id'] : '' ?>" novalidate>
                                <?php if ($editCat): ?>
                                    <input type="hidden" name="edit_id" value="<?= $editCat['id'] ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="catName" class="form-label fw-semibold small">
                                        Tên thể loại <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="catName" name="name"
                                           class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                           placeholder="Ví dụ: Văn học, Khoa học..."
                                           value="<?= htmlspecialchars($_POST['name'] ?? $editCat['name'] ?? '') ?>" autofocus>
                                    <?php if (isset($errors['name'])): ?>
                                        <div class="invalid-feedback"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['name']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-4">
                                    <label for="catDesc" class="form-label fw-semibold small">
                                        Mô tả <span class="text-muted fw-normal">(tùy chọn)</span>
                                    </label>
                                    <textarea id="catDesc" name="description" rows="4" class="form-control"
                                              placeholder="Mô tả ngắn về thể loại này..."><?= htmlspecialchars($_POST['description'] ?? $editCat['description'] ?? '') ?></textarea>
                                </div>

                                <hr class="my-4">
                                <div class="text-end">
                                    <a href="/bookstore/admin/categories.php" class="btn btn-light me-2">Hủy bỏ</a>
                                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                                        <i class="bi bi-save me-1"></i> Lưu thông tin
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <h6 class="fw-bold mb-0 text-dark">
                            Danh sách thể loại
                            <span class="badge bg-secondary ms-2"><?= count($categories) ?></span>
                        </h6>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="text-muted small d-none d-md-inline-block me-2">
                            <i class="bi bi-info-circle me-1"></i>Không thể xóa thể loại đang chứa sách
                        </span>
                        <a href="/bookstore/admin/categories.php?action=add" class="btn btn-sm btn-warning fw-bold text-dark">
                            <i class="bi bi-plus-lg me-1"></i>Thêm thể loại mới
                        </a>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover admin-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4" style="width: 50px;">#</th>
                                    <th>Tên thể loại</th>
                                    <th>Mô tả</th>
                                    <th class="text-center" style="width: 130px;">Số sách</th>
                                    <th class="text-center pe-4" style="width: 110px;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>Chưa có thể loại nào. Hãy thêm thể loại đầu tiên!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $index => $cat): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle flex-shrink-0" style="width:10px;height:10px;background:<?= ['#ffc107','#0d6efd','#198754','#dc3545','#6f42c1','#0dcaf0','#fd7e14'][$index % 7] ?>"></div>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($cat['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-muted small">
                                        <?php if (!empty($cat['description'])): ?>
                                            <?= htmlspecialchars(mb_strlen($cat['description']) > 60 ? mb_substr($cat['description'], 0, 60) . '...' : $cat['description']) ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Chưa có mô tả</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($cat['book_count'] > 0): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary"><?= $cat['book_count'] ?> cuốn</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Chưa có</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <a href="/bookstore/admin/categories.php?action=edit&id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Sửa"><i class="bi bi-pencil"></i></a>
                                        <?php if ($cat['book_count'] > 0): ?>
                                            <button class="btn btn-sm btn-outline-danger" disabled title="Không thể xóa — còn <?= $cat['book_count'] ?> sách"><i class="bi bi-trash3"></i></button>
                                        <?php else: ?>
                                            <form method="POST" action="/bookstore/admin/categories.php?action=delete&id=<?= $cat['id'] ?>" class="d-inline" onsubmit="return confirm('Xóa thể loại này? Hành động này không thể hoàn tác!');">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JS xử lý toggle menu Sidebar trên điện thoại
    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
        document.getElementById('adminSidebar').classList.toggle('show');
    });
</script>
</body>
</html>