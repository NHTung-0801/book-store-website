<?php
// admin/categories.php

// ── 1. KIỂM TRA PHÂN QUYỀN ADMIN VÀ XỬ LÝ LOGIC PHP ĐẦU TIÊN ─────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header('Location: /bookstore/index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$errors   = [];
$editCat  = null; // Dữ liệu thể loại đang được sửa (nếu có)

// Lấy action hiện tại (mặc định là 'list' - hiển thị danh sách)
$action = $_GET['action'] ?? 'list';
$editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ── ACTION: XÓA THỂ LOẠI
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

// ── ACTION: THÊM MỚI HOẶC CẬP NHẬT (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
    // Lấy và làm sạch dữ liệu
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $postEditId  = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);

    // Validate
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

    // Lưu vào DATABASE nếu không có lỗi
    if (empty($errors)) {
        if ($postEditId) {
            $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?")
                ->execute([$name, $description, $postEditId]);
            header('Location: /bookstore/admin/categories.php?msg=updated');
        } else {
            $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)")
                ->execute([$name, $description]);
            header('Location: /bookstore/admin/categories.php?msg=added');
        }
        exit;
    }
}

// ── ACTION: LẤY DỮ LIỆU ĐỂ HIỂN THỊ FORM SỬA
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editCat = $stmt->fetch();
    if (!$editCat) {
        header('Location: /bookstore/admin/categories.php?msg=notfound');
        exit;
    }
}

// ── LẤY DANH SÁCH THỂ LOẠI KÈM SỐ LƯỢNG SÁCH
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

// ── MAP THÔNG BÁO REDIRECT
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

// ── 2. GỌI HEADER ADMIN ──────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/admin_header.php';
?>

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
                                    <form method="POST" action="/bookstore/admin/categories.php?action=delete&id=<?= $cat['id'] ?>" class="d-inline">
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-delete" title="Xóa"><i class="bi bi-trash3"></i></button>
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

<?php
// ── 4. GỌI FOOTER ADMIN ──────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/admin_footer.php';
?>