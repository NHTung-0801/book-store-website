<?php
// admin/books.php

// ── KIỂM TRA PHÂN QUYỀN ADMIN ────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header('Location: /bookstore/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$errors     = [];
$success    = '';
$editBook   = null;

// ── ĐƯỜNG DẪN THƯ MỤC LƯU ẢNH ────────────────────────────────────────────────
define('UPLOAD_DIR',     __DIR__ . '/../assets/images/books/');
define('UPLOAD_MAX_MB',  5);
define('ALLOWED_TYPES',  ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// ── HÀM UPLOAD ẢNH BÌA SÁCH ──────────────────────────────────────────────────
function handleImageUpload(array $file): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Lỗi tải file ảnh lên (mã: ' . $file['error'] . ').');
    }
    if ($file['size'] > UPLOAD_MAX_MB * 1024 * 1024) {
        throw new Exception('Ảnh vượt quá ' . UPLOAD_MAX_MB . 'MB.');
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES)) {
        throw new Exception('Chỉ chấp nhận ảnh JPG, PNG, WEBP, GIF.');
    }
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
    $dest     = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('Không thể lưu file. Kiểm tra quyền thư mục.');
    }
    return $filename;
}

// Lấy danh mục thể loại
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// ════════════════════════════════════════════════════════════
// XỬ LÝ CÁC ACTION
// ════════════════════════════════════════════════════════════

$action = $_GET['action'] ?? 'list';
$editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ── ACTION: XÓA SÁCH ─────────────────────────────────────────────────────────
if ($action === 'delete' && $editId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT image FROM books WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $bookToDelete = $stmt->fetch();

    $stmtDel = $pdo->prepare("DELETE FROM books WHERE id = ?");
    $stmtDel->execute([$editId]);

    if (!empty($bookToDelete['image'])) {
        $imgFile = UPLOAD_DIR . $bookToDelete['image'];
        if (file_exists($imgFile)) {
            unlink($imgFile);
        }
    }
    header('Location: /bookstore/admin/books.php?msg=deleted');
    exit;
}

// ── ACTION: THÊM MỚI HOẶC CẬP NHẬT SÁCH (POST) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {

    $title          = trim($_POST['title']          ?? '');
    $author         = trim($_POST['author']         ?? '');
    $price          = trim($_POST['price']          ?? '');
    $stock          = trim($_POST['stock_quantity'] ?? '');
    $description    = trim($_POST['description']    ?? '');
    $categoryId     = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $postEditId     = filter_input(INPUT_POST, 'edit_id',     FILTER_VALIDATE_INT);

    if (empty($title)) $errors['title'] = 'Tên sách không được để trống.';
    elseif (mb_strlen($title) > 255) $errors['title'] = 'Tên sách tối đa 255 ký tự.';
    if (empty($author)) $errors['author'] = 'Tên tác giả không được để trống.';
    if (!is_numeric($price) || (float)$price < 0) $errors['price'] = 'Giá phải là số không âm.';
    if (!is_numeric($stock) || (int)$stock < 0) $errors['stock'] = 'Số lượng tồn kho phải là số không âm.';
    if (!$categoryId) $errors['category_id'] = 'Vui lòng chọn thể loại.';

    $newFilename = null;
    if (!empty($_FILES['image']['name'])) {
        try {
            $newFilename = handleImageUpload($_FILES['image']);
        } catch (Exception $e) {
            $errors['image'] = $e->getMessage();
        }
    } elseif (!$postEditId) {
        $errors['image'] = 'Vui lòng chọn ảnh bìa sách.';
    }

    if (empty($errors)) {
        if ($postEditId) {
            if ($newFilename) {
                $stmtOld = $pdo->prepare("SELECT image FROM books WHERE id = ?");
                $stmtOld->execute([$postEditId]);
                $oldImage = $stmtOld->fetchColumn();
                if (!empty($oldImage) && file_exists(UPLOAD_DIR . $oldImage)) {
                    unlink(UPLOAD_DIR . $oldImage);
                }
                $stmtUpdate = $pdo->prepare("UPDATE books SET title=?, author=?, price=?, stock_quantity=?, description=?, category_id=?, image=? WHERE id=?");
                $stmtUpdate->execute([$title, $author, (float)$price, (int)$stock, $description, $categoryId, $newFilename, $postEditId]);
            } else {
                $stmtUpdate = $pdo->prepare("UPDATE books SET title=?, author=?, price=?, stock_quantity=?, description=?, category_id=? WHERE id=?");
                $stmtUpdate->execute([$title, $author, (float)$price, (int)$stock, $description, $categoryId, $postEditId]);
            }
            header('Location: /bookstore/admin/books.php?msg=updated');
            exit;
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO books (title, author, price, stock_quantity, description, category_id, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([$title, $author, (float)$price, (int)$stock, $description, $categoryId, $newFilename]);
            header('Location: /bookstore/admin/books.php?msg=added');
            exit;
        }
    }
}

// Lấy dữ liệu cho mode Sửa
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editBook = $stmt->fetch();
    if (!$editBook) {
        header('Location: /bookstore/admin/books.php?msg=notfound');
        exit;
    }
}

// Lấy dữ liệu cho mode Danh sách
if ($action === 'list') {
    $perPage     = 10;
    $currentPage = max(1, (int)($_GET['page'] ?? 1));
    $offset      = ($currentPage - 1) * $perPage;
    $search      = trim($_GET['search'] ?? '');

    if ($search !== '') {
        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM books WHERE title LIKE ? OR author LIKE ?");
        $stmtTotal->execute(["%$search%", "%$search%"]);
    } else {
        $stmtTotal = $pdo->query("SELECT COUNT(*) FROM books");
    }
    $totalBooks  = (int) $stmtTotal->fetchColumn();
    $totalPages  = (int) ceil($totalBooks / $perPage);

    if ($search !== '') {
        $stmtBooks = $pdo->prepare("
            SELECT b.id, b.title, b.author, b.price, b.stock_quantity, b.image, c.name AS category_name
            FROM books b LEFT JOIN categories c ON b.category_id = c.id
            WHERE b.title LIKE ? OR b.author LIKE ?
            ORDER BY b.id DESC LIMIT ? OFFSET ?
        ");
        $stmtBooks->execute(["%$search%", "%$search%", $perPage, $offset]);
    } else {
        $stmtBooks = $pdo->prepare("
            SELECT b.id, b.title, b.author, b.price, b.stock_quantity, b.image, c.name AS category_name
            FROM books b LEFT JOIN categories c ON b.category_id = c.id
            ORDER BY b.id DESC LIMIT ? OFFSET ?
        ");
        $stmtBooks->execute([$perPage, $offset]);
    }
    $books = $stmtBooks->fetchAll();
}

// Messages
$msgMap = [
    'added'    => ['type' => 'success', 'text' => 'Thêm sách mới thành công!'],
    'updated'  => ['type' => 'success', 'text' => 'Cập nhật sách thành công!'],
    'deleted'  => ['type' => 'warning', 'text' => 'Đã xóa sách khỏi hệ thống.'],
    'notfound' => ['type' => 'danger',  'text' => 'Không tìm thấy sách.'],
];
$msg = $msgMap[$_GET['msg'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sách — Book Store Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
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
        .admin-main { margin-left: 250px; min-height: 100vh; }
        .admin-topbar {
            background: #fff; border-bottom: 1px solid #e9ecef;
            padding: .85rem 1.5rem; position: sticky; top: 0; z-index: 999;
        }
        .admin-table thead th {
            background: #f8f9fa; font-size: .78rem;
            text-transform: uppercase; letter-spacing: .05em;
            color: #6c757d; border-bottom: 2px solid #e9ecef; white-space: nowrap;
        }
        @media (max-width: 991.98px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.show { transform: translateX(0); }
            .admin-main { margin-left: 0; }
        }
        #imgPreviewWrap { display: none; }
        #imgPreview { width: 100px; height: 133px; object-fit: cover; border-radius: 8px; }
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
        <a href="/bookstore/admin/books.php" class="nav-link active"><i class="bi bi-book"></i>Quản lý sách</a>
        <a href="/bookstore/admin/categories.php" class="nav-link"><i class="bi bi-tags"></i>Thể loại</a>
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
            <h5 class="mb-0 fw-bold"><i class="bi bi-book me-2 text-warning"></i>Quản lý sách</h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="text-end d-none d-sm-block">
                <p class="mb-0 fw-semibold small"><?= htmlspecialchars($_SESSION['fullname']) ?></p>
            </div>
            <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center fw-bold text-dark" style="width:38px;height:38px;font-size:.9rem;">
                <?= strtoupper(mb_substr($_SESSION['fullname'], 0, 1)) ?>
            </div>
        </div>
    </div>

    <div class="p-4">
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show shadow-sm">
                <i class="bi bi-info-circle me-2"></i><?= $msg['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action === 'add' || $action === 'edit'): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0 text-primary">
                                <i class="bi bi-<?= $editBook ? 'pencil-square' : 'plus-circle' ?> me-2"></i>
                                <?= $editBook ? 'Cập nhật thông tin sách' : 'Thêm sách mới' ?>
                            </h6>
                            <a href="/bookstore/admin/books.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
                            </a>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="/bookstore/admin/books.php?action=<?= $action ?><?= $editBook ? '&id='.$editBook['id'] : '' ?>" enctype="multipart/form-data" novalidate>
                                <?php if ($editBook): ?>
                                    <input type="hidden" name="edit_id" value="<?= $editBook['id'] ?>">
                                <?php endif; ?>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Tên sách <span class="text-danger">*</span></label>
                                        <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['title'] ?? $editBook['title'] ?? '') ?>" placeholder="Nhập tên sách">
                                        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= $errors['title'] ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small">Tác giả <span class="text-danger">*</span></label>
                                        <input type="text" name="author" class="form-control <?= isset($errors['author']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['author'] ?? $editBook['author'] ?? '') ?>" placeholder="Tên tác giả">
                                        <?php if (isset($errors['author'])): ?><div class="invalid-feedback"><?= $errors['author'] ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold small">Giá (₫) <span class="text-danger">*</span></label>
                                        <input type="number" name="price" min="0" step="1000" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['price'] ?? $editBook['price'] ?? '') ?>" placeholder="0">
                                        <?php if (isset($errors['price'])): ?><div class="invalid-feedback"><?= $errors['price'] ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold small">Số lượng tồn kho <span class="text-danger">*</span></label>
                                        <input type="number" name="stock_quantity" min="0" class="form-control <?= isset($errors['stock']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['stock_quantity'] ?? $editBook['stock_quantity'] ?? '') ?>" placeholder="0">
                                        <?php if (isset($errors['stock'])): ?><div class="invalid-feedback"><?= $errors['stock'] ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold small">Thể loại <span class="text-danger">*</span></label>
                                        <select name="category_id" class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>">
                                            <option value="">-- Chọn --</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? $editBook['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback"><?= $errors['category_id'] ?></div><?php endif; ?>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">Mô tả chi tiết</label>
                                        <textarea name="description" rows="4" class="form-control" placeholder="Giới thiệu nội dung sách..."><?= htmlspecialchars($_POST['description'] ?? $editBook['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold small">Ảnh bìa <?= !$editBook ? '<span class="text-danger">*</span>' : '<span class="text-muted">(Để trống nếu muốn giữ ảnh cũ)</span>' ?></label>
                                        <input type="file" name="image" id="imageInput" accept="image/*" class="form-control <?= isset($errors['image']) ? 'is-invalid' : '' ?>">
                                        <?php if (isset($errors['image'])): ?><div class="invalid-feedback"><?= $errors['image'] ?></div><?php endif; ?>
                                        
                                        <div class="d-flex gap-4 mt-3">
                                            <?php if ($editBook && !empty($editBook['image'])): ?>
                                            <div>
                                                <p class="text-muted small fw-semibold mb-1">Ảnh hiện tại:</p>
                                                <img src="/bookstore/assets/images/books/<?= htmlspecialchars($editBook['image']) ?>" class="rounded border shadow-sm" style="width:100px;height:133px;object-fit:cover;">
                                            </div>
                                            <?php endif; ?>
                                            <div id="imgPreviewWrap">
                                                <p class="text-muted small fw-semibold mb-1 text-primary">Ảnh mới chọn:</p>
                                                <img id="imgPreview" src="" class="shadow-sm">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr class="my-4">
                                <div class="text-end">
                                    <a href="/bookstore/admin/books.php" class="btn btn-light me-2">Hủy bỏ</a>
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
                            Danh sách sách đang bán
                            <span class="badge bg-secondary ms-2"><?= $totalBooks ?> cuốn</span>
                        </h6>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <form method="GET" action="" class="d-flex gap-1">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Tìm tên sách..." value="<?= htmlspecialchars($search) ?>" style="width:200px;">
                            <button type="submit" class="btn btn-sm btn-dark"><i class="bi bi-search"></i></button>
                            <?php if ($search): ?><a href="/bookstore/admin/books.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a><?php endif; ?>
                        </form>
                        <a href="/bookstore/admin/books.php?action=add" class="btn btn-sm btn-warning fw-bold text-dark ms-2">
                            <i class="bi bi-plus-lg me-1"></i>Thêm sách mới
                        </a>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover admin-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 text-center" style="width:80px;">Ảnh</th>
                                    <th>Thông tin sách</th>
                                    <th>Thể loại</th>
                                    <th class="text-end">Giá bán</th>
                                    <th class="text-center">Kho</th>
                                    <th class="text-center pe-4" style="width:120px;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($books)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Không có dữ liệu.</td></tr>
                            <?php else: ?>
                                <?php foreach ($books as $book):
                                    $imgPath = '/bookstore/assets/images/books/' . $book['image'];
                                    $imgSrc  = (!empty($book['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath)) ? $imgPath : '/bookstore/assets/images/books/placeholder.png';
                                ?>
                                <tr>
                                    <td class="ps-4 text-center">
                                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Bìa" class="rounded shadow-sm" style="width:48px;height:64px;object-fit:cover;">
                                    </td>
                                    <td>
                                        <p class="fw-bold mb-1 text-dark"><?= htmlspecialchars($book['title']) ?></p>
                                        <p class="text-muted small mb-0"><i class="bi bi-pen me-1"></i><?= htmlspecialchars($book['author']) ?></p>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($book['category_name'] ?? 'N/A') ?></span></td>
                                    <td class="text-end fw-bold text-danger"><?= number_format($book['price'], 0, ',', '.') ?>₫</td>
                                    <td class="text-center">
                                        <?php if ($book['stock_quantity'] == 0): ?>
                                            <span class="badge bg-danger rounded-pill px-2">Hết hàng</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success rounded-pill px-2"><?= $book['stock_quantity'] ?> cuốn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <a href="/bookstore/admin/books.php?action=edit&id=<?= $book['id'] ?>" class="btn btn-sm btn-outline-primary" title="Sửa"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="/bookstore/admin/books.php?action=delete&id=<?= $book['id'] ?>" class="d-inline" onsubmit="return confirm('Xóa sách này khỏi hệ thống?');">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top bg-light">
                        <span class="text-muted small">Trang <?= $currentPage ?> / <?= $totalPages ?></span>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?action=list&page=<?= $currentPage - 1 ?>&search=<?= urlencode($search) ?>">Trước</a></li>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>"><a class="page-link" href="?action=list&page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a></li>
                            <?php endfor; ?>
                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?action=list&page=<?= $currentPage + 1 ?>&search=<?= urlencode($search) ?>">Sau</a></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
        document.getElementById('adminSidebar').classList.toggle('show');
    });

    document.getElementById('imageInput')?.addEventListener('change', function () {
        const file = this.files[0];
        const wrap = document.getElementById('imgPreviewWrap');
        const img  = document.getElementById('imgPreview');
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => { img.src = e.target.result; wrap.style.display = 'block'; };
            reader.readAsDataURL(file);
        } else { wrap.style.display = 'none'; img.src = ''; }
    });
</script>
</body>
</html>