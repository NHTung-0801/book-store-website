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
$editBook   = null; // Chứa dữ liệu sách đang được sửa (nếu có)

// ── ĐƯỜNG DẪN THƯ MỤC LƯU ẢNH ────────────────────────────────────────────────
define('UPLOAD_DIR',     __DIR__ . '/../assets/images/books/');
define('UPLOAD_MAX_MB',  5);
define('ALLOWED_TYPES',  ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);

// ── HÀM UPLOAD ẢNH BÌA SÁCH ──────────────────────────────────────────────────
// Trả về tên file đã lưu, hoặc ném Exception nếu lỗi
function handleImageUpload(array $file): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Lỗi tải file ảnh lên (mã: ' . $file['error'] . ').');
    }
    if ($file['size'] > UPLOAD_MAX_MB * 1024 * 1024) {
        throw new Exception('Ảnh vượt quá ' . UPLOAD_MAX_MB . 'MB.');
    }
    // Kiểm tra MIME type thực sự của file (không tin vào $_FILES['type'])
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES)) {
        throw new Exception('Chỉ chấp nhận ảnh JPG, PNG, WEBP, GIF.');
    }
    // Tạo tên file duy nhất tránh trùng lặp: timestamp_random.ext
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
    $dest     = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('Không thể lưu file. Kiểm tra quyền thư mục.');
    }
    return $filename;
}

// ── LẤY DANH SÁCH THỂ LOẠI CHO SELECT ───────────────────────────────────────
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// ════════════════════════════════════════════════════════════
// XỬ LÝ CÁC ACTION
// ════════════════════════════════════════════════════════════

$action = $_GET['action'] ?? '';
$editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ── ACTION: LOAD FORM SỬA SÁCH ───────────────────────────────────────────────
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editBook = $stmt->fetch();
    if (!$editBook) {
        header('Location: /bookstore/admin/books.php?msg=notfound');
        exit;
    }
}

// ── ACTION: XÓA SÁCH ─────────────────────────────────────────────────────────
if ($action === 'delete' && $editId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy tên ảnh trước khi xóa để xóa file vật lý
    $stmt = $pdo->prepare("SELECT image FROM books WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $bookToDelete = $stmt->fetch();

    $stmtDel = $pdo->prepare("DELETE FROM books WHERE id = ?");
    $stmtDel->execute([$editId]);

    // Xóa file ảnh vật lý nếu tồn tại
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {

    // 1. LẤY VÀ LÀM SẠCH DỮ LIỆU ──────────────────────────────────────────
    $title          = trim($_POST['title']          ?? '');
    $author         = trim($_POST['author']         ?? '');
    $price          = trim($_POST['price']          ?? '');
    $stock          = trim($_POST['stock_quantity'] ?? '');
    $description    = trim($_POST['description']    ?? '');
    $categoryId     = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $postEditId     = filter_input(INPUT_POST, 'edit_id',     FILTER_VALIDATE_INT);

    // 2. VALIDATE ───────────────────────────────────────────────────────────
    if (empty($title)) {
        $errors['title'] = 'Tên sách không được để trống.';
    } elseif (mb_strlen($title) > 255) {
        $errors['title'] = 'Tên sách tối đa 255 ký tự.';
    }
    if (empty($author)) {
        $errors['author'] = 'Tên tác giả không được để trống.';
    }
    if (!is_numeric($price) || (float)$price < 0) {
        $errors['price'] = 'Giá phải là số không âm.';
    }
    if (!is_numeric($stock) || (int)$stock < 0) {
        $errors['stock'] = 'Số lượng tồn kho phải là số không âm.';
    }
    if (!$categoryId) {
        $errors['category_id'] = 'Vui lòng chọn thể loại.';
    }

    // 3. XỬ LÝ UPLOAD ẢNH ──────────────────────────────────────────────────
    $newFilename = null; // null = không upload ảnh mới

    if (!empty($_FILES['image']['name'])) {
        try {
            $newFilename = handleImageUpload($_FILES['image']);
        } catch (Exception $e) {
            $errors['image'] = $e->getMessage();
        }
    } elseif (!$postEditId) {
        // Thêm mới mà không có ảnh → bắt buộc phải có
        $errors['image'] = 'Vui lòng chọn ảnh bìa sách.';
    }

    // 4. LƯU VÀO DATABASE NẾU KHÔNG CÓ LỖI ──────────────────────────────
    if (empty($errors)) {

        if ($postEditId) {
            // ── CẬP NHẬT SÁCH ──────────────────────────────────────────────
            // Nếu có ảnh mới → xóa ảnh cũ, dùng ảnh mới
            // Nếu không có ảnh mới → giữ nguyên ảnh cũ
            if ($newFilename) {
                // Lấy ảnh cũ để xóa file vật lý
                $stmtOld = $pdo->prepare("SELECT image FROM books WHERE id = ?");
                $stmtOld->execute([$postEditId]);
                $oldImage = $stmtOld->fetchColumn();
                if (!empty($oldImage) && file_exists(UPLOAD_DIR . $oldImage)) {
                    unlink(UPLOAD_DIR . $oldImage);
                }

                $stmtUpdate = $pdo->prepare("
                    UPDATE books
                    SET title = ?, author = ?, price = ?, stock_quantity = ?,
                        description = ?, category_id = ?, image = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $title, $author, (float)$price, (int)$stock,
                    $description, $categoryId, $newFilename, $postEditId
                ]);
            } else {
                // Giữ nguyên ảnh cũ, chỉ cập nhật các trường khác
                $stmtUpdate = $pdo->prepare("
                    UPDATE books
                    SET title = ?, author = ?, price = ?, stock_quantity = ?,
                        description = ?, category_id = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $title, $author, (float)$price, (int)$stock,
                    $description, $categoryId, $postEditId
                ]);
            }
            header('Location: /bookstore/admin/books.php?msg=updated');
            exit;

        } else {
            // ── THÊM MỚI SÁCH ──────────────────────────────────────────────
            $stmtInsert = $pdo->prepare("
                INSERT INTO books (title, author, price, stock_quantity,
                                   description, category_id, image)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([
                $title, $author, (float)$price, (int)$stock,
                $description, $categoryId, $newFilename
            ]);
            header('Location: /bookstore/admin/books.php?msg=added');
            exit;
        }
    }

    // Có lỗi khi edit → khôi phục lại $editBook để giữ form
    if ($postEditId) {
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
        $stmt->execute([$postEditId]);
        $editBook = $stmt->fetch();
    }
}

// ── QUERY DANH SÁCH SÁCH (có phân trang) ─────────────────────────────────────
$perPage     = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// Từ khóa tìm kiếm
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmtTotal = $pdo->prepare("
        SELECT COUNT(*) FROM books
        WHERE title LIKE ? OR author LIKE ?
    ");
    $stmtTotal->execute(["%$search%", "%$search%"]);
} else {
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM books");
}
$totalBooks  = (int) $stmtTotal->fetchColumn();
$totalPages  = (int) ceil($totalBooks / $perPage);

if ($search !== '') {
    $stmtBooks = $pdo->prepare("
        SELECT  b.id, b.title, b.author, b.price,
                b.stock_quantity, b.image, c.name AS category_name
        FROM    books b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE   b.title LIKE ? OR b.author LIKE ?
        ORDER BY b.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmtBooks->execute(["%$search%", "%$search%", $perPage, $offset]);
} else {
    $stmtBooks = $pdo->prepare("
        SELECT  b.id, b.title, b.author, b.price,
                b.stock_quantity, b.image, c.name AS category_name
        FROM    books b
        LEFT JOIN categories c ON b.category_id = c.id
        ORDER BY b.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmtBooks->execute([$perPage, $offset]);
}
$books = $stmtBooks->fetchAll();

// ── ĐỌC THÔNG BÁO MSG TỪ REDIRECT ───────────────────────────────────────────
$msgMap = [
    'added'    => ['type' => 'success', 'text' => 'Thêm sách mới thành công!'],
    'updated'  => ['type' => 'success', 'text' => 'Cập nhật sách thành công!'],
    'deleted'  => ['type' => 'warning', 'text' => 'Đã xóa sách khỏi hệ thống.'],
    'notfound' => ['type' => 'danger',  'text' => 'Không tìm thấy sách cần sửa.'],
];
$msg = $msgMap[$_GET['msg'] ?? ''] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sách — Book Store Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">
    <link href="/bookstore/assets/css/style.css" rel="stylesheet">
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

        /* Preview ảnh khi chọn file */
        #imgPreviewWrap { display: none; }
        #imgPreview {
            width: 100px; height: 133px;
            object-fit: cover; border-radius: 8px;
        }
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
        <a href="/bookstore/admin/books.php" class="nav-link active">
            <i class="bi bi-book"></i>Quản lý sách
        </a>
        <a href="/bookstore/admin/categories.php" class="nav-link">
            <i class="bi bi-tags"></i>Thể loại
        </a>
        <a href="/bookstore/admin/orders.php" class="nav-link">
            <i class="bi bi-bag-check"></i>Đơn hàng
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
            <i class="bi bi-book me-2 text-warning"></i>Quản lý sách
        </h5>
        <span class="text-muted small">
            <?= htmlspecialchars($_SESSION['fullname']) ?> · Quản trị viên
        </span>
    </div>

    <div class="p-4">

        <!-- Thông báo redirect -->
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?= $msg['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- ══ CỘT TRÁI: FORM THÊM / SỬA ══ -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-dark text-white fw-bold py-3">
                        <i class="bi bi-<?= $editBook ? 'pencil-square' : 'plus-circle' ?> me-2 text-warning"></i>
                        <?= $editBook ? 'Cập nhật sách' : 'Thêm sách mới' ?>
                    </div>
                    <div class="card-body p-4">

                        <form method="POST"
                              action="/bookstore/admin/books.php<?= $editBook ? '?action=edit&id=' . $editBook['id'] : '' ?>"
                              enctype="multipart/form-data"
                              novalidate>

                            <!-- Hidden: truyền edit_id khi đang sửa -->
                            <?php if ($editBook): ?>
                                <input type="hidden" name="edit_id"
                                       value="<?= $editBook['id'] ?>">
                            <?php endif; ?>

                            <!-- Tên sách -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">
                                    Tên sách <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="title"
                                       class="form-control form-control-sm
                                              <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                                       value="<?= htmlspecialchars($_POST['title'] ?? $editBook['title'] ?? '') ?>"
                                       placeholder="Tên đầy đủ của sách">
                                <?php if (isset($errors['title'])): ?>
                                    <div class="invalid-feedback"><?= $errors['title'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Tác giả -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">
                                    Tác giả <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="author"
                                       class="form-control form-control-sm
                                              <?= isset($errors['author']) ? 'is-invalid' : '' ?>"
                                       value="<?= htmlspecialchars($_POST['author'] ?? $editBook['author'] ?? '') ?>"
                                       placeholder="Tên tác giả">
                                <?php if (isset($errors['author'])): ?>
                                    <div class="invalid-feedback"><?= $errors['author'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Giá + Tồn kho (2 cột) -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold small">
                                        Giá (₫) <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" name="price" min="0" step="1000"
                                           class="form-control form-control-sm
                                                  <?= isset($errors['price']) ? 'is-invalid' : '' ?>"
                                           value="<?= htmlspecialchars($_POST['price'] ?? $editBook['price'] ?? '') ?>"
                                           placeholder="0">
                                    <?php if (isset($errors['price'])): ?>
                                        <div class="invalid-feedback"><?= $errors['price'] ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold small">
                                        Tồn kho <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" name="stock_quantity" min="0"
                                           class="form-control form-control-sm
                                                  <?= isset($errors['stock']) ? 'is-invalid' : '' ?>"
                                           value="<?= htmlspecialchars($_POST['stock_quantity'] ?? $editBook['stock_quantity'] ?? '') ?>"
                                           placeholder="0">
                                    <?php if (isset($errors['stock'])): ?>
                                        <div class="invalid-feedback"><?= $errors['stock'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Thể loại -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">
                                    Thể loại <span class="text-danger">*</span>
                                </label>
                                <select name="category_id"
                                        class="form-select form-select-sm
                                               <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>">
                                    <option value="">-- Chọn thể loại --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"
                                            <?= (($_POST['category_id'] ?? $editBook['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['category_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['category_id'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Mô tả -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Mô tả</label>
                                <textarea name="description" rows="3"
                                          class="form-control form-control-sm"
                                          placeholder="Giới thiệu ngắn về nội dung sách..."
                                ><?= htmlspecialchars($_POST['description'] ?? $editBook['description'] ?? '') ?></textarea>
                            </div>

                            <!-- Upload ảnh bìa -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold small">
                                    Ảnh bìa
                                    <?= !$editBook ? '<span class="text-danger">*</span>' : '' ?>
                                    <?= $editBook ? '<span class="text-muted">(để trống = giữ ảnh cũ)</span>' : '' ?>
                                </label>

                                <!-- Hiện ảnh cũ khi đang edit -->
                                <?php if ($editBook && !empty($editBook['image'])): ?>
                                <div class="mb-2">
                                    <img src="/bookstore/assets/images/books/<?= htmlspecialchars($editBook['image']) ?>"
                                         alt="Ảnh hiện tại"
                                         class="rounded border"
                                         style="width:80px;height:107px;object-fit:cover;">
                                    <p class="text-muted small mt-1 mb-0">Ảnh hiện tại</p>
                                </div>
                                <?php endif; ?>

                                <input type="file" name="image" id="imageInput"
                                       accept="image/*"
                                       class="form-control form-control-sm
                                              <?= isset($errors['image']) ? 'is-invalid' : '' ?>">
                                <?php if (isset($errors['image'])): ?>
                                    <div class="invalid-feedback"><?= $errors['image'] ?></div>
                                <?php endif; ?>
                                <div class="text-muted small mt-1">JPG, PNG, WEBP, GIF — tối đa 5MB</div>

                                <!-- Preview ảnh mới chọn -->
                                <div id="imgPreviewWrap" class="mt-2">
                                    <img id="imgPreview" src="" alt="Preview ảnh mới">
                                    <p class="text-muted small mt-1 mb-0">Ảnh mới</p>
                                </div>
                            </div>

                            <!-- Nút submit -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning fw-bold">
                                    <i class="bi bi-<?= $editBook ? 'check-circle' : 'plus-circle' ?> me-2"></i>
                                    <?= $editBook ? 'Cập nhật sách' : 'Thêm sách' ?>
                                </button>
                                <?php if ($editBook): ?>
                                    <!-- Nút hủy sửa → quay về form thêm mới -->
                                    <a href="/bookstore/admin/books.php"
                                       class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-2"></i>Hủy chỉnh sửa
                                    </a>
                                <?php endif; ?>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

            <!-- ══ CỘT PHẢI: BẢNG DANH SÁCH SÁCH ══ -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h6 class="fw-bold mb-0">
                                Danh sách sách
                                <span class="badge bg-secondary ms-1"><?= $totalBooks ?></span>
                            </h6>
                            <!-- Form tìm kiếm -->
                            <form method="GET" action=""
                                  class="d-flex gap-2" style="max-width: 300px;">
                                <input type="text" name="search"
                                       class="form-control form-control-sm"
                                       placeholder="Tìm tên sách, tác giả..."
                                       value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-sm btn-warning">
                                    <i class="bi bi-search"></i>
                                </button>
                                <?php if ($search): ?>
                                    <a href="/bookstore/admin/books.php"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-x"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table admin-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4" style="width:60px;">Ảnh</th>
                                        <th>Tên sách / Tác giả</th>
                                        <th>Thể loại</th>
                                        <th class="text-end">Giá</th>
                                        <th class="text-center">Tồn kho</th>
                                        <th class="text-center pe-4" style="width:110px;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($books)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            <?= $search ? 'Không tìm thấy sách nào phù hợp.' : 'Chưa có sách nào.' ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($books as $book):
                                        $imgPath = '/bookstore/assets/images/books/' . $book['image'];
                                        $imgSrc  = (!empty($book['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
                                                    ? $imgPath
                                                    : '/bookstore/assets/images/books/placeholder.png';
                                    ?>
                                    <tr class="<?= ($editBook && $editBook['id'] == $book['id']) ? 'table-warning' : '' ?>">
                                        <!-- Ảnh bìa -->
                                        <td class="ps-4">
                                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                                 alt="<?= htmlspecialchars($book['title']) ?>"
                                                 class="rounded"
                                                 style="width:42px;height:56px;object-fit:cover;">
                                        </td>
                                        <!-- Tên + tác giả -->
                                        <td>
                                            <p class="fw-semibold small mb-0">
                                                <?= htmlspecialchars($book['title']) ?>
                                            </p>
                                            <p class="text-muted mb-0" style="font-size:.78rem;">
                                                <?= htmlspecialchars($book['author']) ?>
                                            </p>
                                        </td>
                                        <!-- Thể loại -->
                                        <td>
                                            <span class="badge bg-warning-subtle text-warning border border-warning small">
                                                <?= htmlspecialchars($book['category_name'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <!-- Giá -->
                                        <td class="text-end fw-bold text-danger small">
                                            <?= number_format($book['price'], 0, ',', '.') ?>₫
                                        </td>
                                        <!-- Tồn kho -->
                                        <td class="text-center">
                                            <?php if ($book['stock_quantity'] == 0): ?>
                                                <span class="badge bg-danger">Hết hàng</span>
                                            <?php elseif ($book['stock_quantity'] <= 5): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <?= $book['stock_quantity'] ?> còn
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success">
                                                    <?= $book['stock_quantity'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Thao tác -->
                                        <td class="text-center pe-4">
                                            <!-- Nút Sửa -->
                                            <a href="/bookstore/admin/books.php?action=edit&id=<?= $book['id'] ?>"
                                               class="btn btn-sm btn-outline-primary me-1"
                                               title="Sửa sách">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <!-- Nút Xóa (dùng form POST) -->
                                            <form method="POST"
                                                  action="/bookstore/admin/books.php?action=delete&id=<?= $book['id'] ?>"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Xóa sách «<?= addslashes($book['title']) ?>»? Hành động này không thể hoàn tác!')">
                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Xóa sách">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Phân trang -->
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                            <p class="text-muted small mb-0">
                                Hiển thị <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalBooks) ?>
                                trong <?= $totalBooks ?> sách
                            </p>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <!-- Trang trước -->
                                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link"
                                           href="?page=<?= $currentPage - 1 ?>&search=<?= urlencode($search) ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <!-- Các trang -->
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                            <a class="page-link"
                                               href="?page=<?= $p ?>&search=<?= urlencode($search) ?>">
                                                <?= $p ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    <!-- Trang sau -->
                                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link"
                                           href="?page=<?= $currentPage + 1 ?>&search=<?= urlencode($search) ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>

                    </div><!-- /.card-body -->
                </div><!-- /.card -->
            </div>

        </div><!-- /.row -->
    </div><!-- /.p-4 -->
</div><!-- /.admin-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmArFmcZZm7MFEBp3VLFHnFX8oH"
        crossorigin="anonymous"></script>
<script>
    // Preview ảnh trước khi upload
    document.getElementById('imageInput').addEventListener('change', function () {
        const file = this.files[0];
        const wrap = document.getElementById('imgPreviewWrap');
        const img  = document.getElementById('imgPreview');

        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                img.src       = e.target.result;
                wrap.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            wrap.style.display = 'none';
            img.src = '';
        }
    });
</script>
</body>
</html>