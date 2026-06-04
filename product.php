<?php
// product.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// ── 1. LẤY VÀ VALIDATE THAM SỐ ID TỪ URL ────────────────────────────────────
// filter_input + FILTER_VALIDATE_INT chống tấn công truyền id dạng chuỗi/âm
$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$bookId || $bookId <= 0) {
    // ID không hợp lệ → chuyển về trang chủ
    header('Location: /bookstore/index.php');
    exit;
}

// ── 2. TRUY VẤN CHI TIẾT SÁCH, JOIN CATEGORIES ───────────────────────────────
$stmt = $pdo->prepare("
    SELECT  b.id,
            b.title,
            b.author,
            b.price,
            b.image,
            b.description,
            b.stock_quantity,
            c.name  AS category_name,
            c.id    AS category_id
    FROM  books b
    LEFT JOIN categories c ON b.category_id = c.id
    WHERE b.id = ?
    LIMIT 1
");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

// Không tìm thấy sách → 404 đơn giản rồi redirect
if (!$book) {
    header('Location: /bookstore/index.php');
    exit;
}

// ── 3. LẤY THÊM SÁCH CÙNG THỂ LOẠI (gợi ý) ──────────────────────────────────
$stmtRelated = $pdo->prepare("
    SELECT id, title, author, price, image
    FROM   books
    WHERE  category_id = ?
      AND  id != ?
    ORDER BY id DESC
    LIMIT 4
");
$stmtRelated->execute([$book['category_id'], $bookId]);
$relatedBooks = $stmtRelated->fetchAll();

// ── 4. XÁC ĐỊNH ĐƯỜNG DẪN ẢNH, FALLBACK NẾU KHÔNG CÓ ───────────────────────
$imgPath = '/bookstore/assets/images/books/' . $book['image'];
$imgSrc  = (!empty($book['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
            ? $imgPath
            : '/bookstore/assets/images/books/placeholder.png';

// ── 5. XỬ LÝ THÔNG BÁO SAU KHI THÊM GIỎ HÀNG (từ actions/add_to_cart.php) ──
// add_to_cart.php sẽ redirect về product.php?id=X&status=added hoặc &status=error
$cartStatus = $_GET['status'] ?? '';
?>

<!-- ========== NỘI DUNG TRANG CHI TIẾT SÁCH ========== -->
<main class="container my-5">

    <!-- Breadcrumb điều hướng -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/bookstore/index.php" class="text-warning text-decoration-none">
                    <i class="bi bi-house-door me-1"></i>Trang chủ
                </a>
            </li>
            <?php if (!empty($book['category_name'])): ?>
            <li class="breadcrumb-item">
                <a href="/bookstore/index.php?category=<?= $book['category_id'] ?>"
                   class="text-warning text-decoration-none">
                    <?= htmlspecialchars($book['category_name']) ?>
                </a>
            </li>
            <?php endif; ?>
            <li class="breadcrumb-item active text-muted" aria-current="page">
                <?= htmlspecialchars($book['title']) ?>
            </li>
        </ol>
    </nav>

    <!-- Thông báo thêm giỏ hàng thành công / thất bại -->
    <?php if ($cartStatus === 'added'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-cart-check-fill me-2"></i>
            Đã thêm sách vào giỏ hàng thành công!
            <a href="/bookstore/cart.php" class="alert-link ms-2">Xem giỏ hàng →</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($cartStatus === 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Có lỗi xảy ra, vui lòng thử lại.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($cartStatus === 'login'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-person-lock me-2"></i>
            Bạn cần <a href="/bookstore/login.php" class="alert-link">đăng nhập</a>
            để thêm sách vào giỏ hàng.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── LAYOUT 2 CỘT CHÍNH ── -->
    <div class="row g-5">

        <!-- ══ CỘT TRÁI: ẢNH BÌA SÁCH ══ -->
        <div class="col-lg-4 col-md-5">
            <div class="product-img-wrap shadow rounded-3 overflow-hidden">
                <img
                    src="<?= htmlspecialchars($imgSrc) ?>"
                    alt="Bìa sách: <?= htmlspecialchars($book['title']) ?>"
                    class="img-fluid w-100 product-img"
                >
            </div>

            <!-- Thể loại bên dưới ảnh -->
            <?php if (!empty($book['category_name'])): ?>
            <div class="mt-3 text-center">
                <span class="badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill">
                    <i class="bi bi-tag me-1"></i>
                    <?= htmlspecialchars($book['category_name']) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- ══ CỘT PHẢI: THÔNG TIN CHI TIẾT + FORM ══ -->
        <div class="col-lg-8 col-md-7">

            <!-- Tên sách -->
            <h2 class="fw-bold mb-1"><?= htmlspecialchars($book['title']) ?></h2>

            <!-- Tác giả -->
            <p class="text-muted mb-3">
                <i class="bi bi-person-circle me-1"></i>
                Tác giả: <strong><?= htmlspecialchars($book['author']) ?></strong>
            </p>

            <!-- Giá bán -->
            <div class="product-price mb-3">
                <span class="fs-2 fw-bold text-danger">
                    <?= number_format($book['price'], 0, ',', '.') ?>₫
                </span>
            </div>

            <!-- Trạng thái tồn kho -->
            <div class="mb-3">
                <?php if ($book['stock_quantity'] > 10): ?>
                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                        <i class="bi bi-check-circle me-1"></i>
                        Còn hàng (<?= $book['stock_quantity'] ?> cuốn)
                    </span>
                <?php elseif ($book['stock_quantity'] > 0): ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Sắp hết hàng (còn <?= $book['stock_quantity'] ?> cuốn)
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2">
                        <i class="bi bi-x-circle me-1"></i>
                        Hết hàng
                    </span>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <!-- Mô tả sách -->
            <?php if (!empty($book['description'])): ?>
            <div class="mb-4">
                <h6 class="fw-bold text-uppercase text-muted small mb-2 ls-1">
                    <i class="bi bi-card-text me-1"></i>Mô tả sách
                </h6>
                <div class="product-description text-secondary lh-lg">
                    <!-- nl2br chuyển xuống dòng trong DB thành <br> -->
                    <?= nl2br(htmlspecialchars($book['description'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── FORM THÊM VÀO GIỎ HÀNG ── -->
            <?php if ($book['stock_quantity'] > 0): ?>
            <form method="POST"
                  action="/bookstore/actions/add_to_cart.php"
                  class="add-to-cart-form">

                <!-- Truyền book_id vào hidden input -->
                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">

                <div class="d-flex align-items-center gap-3 flex-wrap">

                    <!-- Input số lượng -->
                    <div class="quantity-control d-flex align-items-center border rounded-3 overflow-hidden">
                        <!-- Nút giảm số lượng -->
                        <button type="button" class="btn btn-light px-3 py-2 qty-btn"
                                data-action="decrease" aria-label="Giảm số lượng">
                            <i class="bi bi-dash-lg"></i>
                        </button>

                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            class="form-control border-0 text-center fw-bold qty-input"
                            value="1"
                            min="1"
                            max="<?= $book['stock_quantity'] ?>"
                            style="width: 60px;"
                            aria-label="Số lượng"
                        >

                        <!-- Nút tăng số lượng -->
                        <button type="button" class="btn btn-light px-3 py-2 qty-btn"
                                data-action="increase"
                                data-max="<?= $book['stock_quantity'] ?>"
                                aria-label="Tăng số lượng">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>

                    <!-- Nút thêm vào giỏ hàng -->
                    <button type="submit" class="btn btn-warning btn-lg fw-bold px-4 flex-grow-1">
                        <i class="bi bi-cart-plus me-2"></i>Thêm vào giỏ hàng
                    </button>

                </div>

                <!-- Gợi ý tối đa có thể đặt -->
                <p class="text-muted small mt-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Tối đa <?= $book['stock_quantity'] ?> cuốn mỗi đơn hàng
                </p>

            </form>

            <?php else: ?>
                <!-- Sách hết hàng — ẩn form, hiển thị thông báo -->
                <div class="alert alert-secondary d-flex align-items-center gap-2">
                    <i class="bi bi-bell fs-5"></i>
                    <span>Sách tạm thời hết hàng. Vui lòng quay lại sau!</span>
                </div>
            <?php endif; ?>

        </div><!-- /.col cột phải -->
    </div><!-- /.row -->

    <!-- ── SÁCH CÙNG THỂ LOẠI ── -->
    <?php if (!empty($relatedBooks)): ?>
    <section class="mt-5 pt-4 border-top">
        <h5 class="fw-bold mb-4">
            <i class="bi bi-grid me-2 text-warning"></i>
            Sách cùng thể loại
        </h5>
        <div class="row row-cols-2 row-cols-md-4 g-3">
            <?php foreach ($relatedBooks as $rel): ?>
            <?php
                $relImg = '/bookstore/assets/images/books/' . $rel['image'];
                $relSrc = (!empty($rel['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $relImg))
                           ? $relImg
                           : '/bookstore/assets/images/books/placeholder.png';
            ?>
            <div class="col">
                <div class="card h-100 border-0 shadow-sm book-card">
                    <div class="book-card__img-wrap">
                        <a href="/bookstore/product.php?id=<?= $rel['id'] ?>">
                            <img src="<?= htmlspecialchars($relSrc) ?>"
                                 alt="<?= htmlspecialchars($rel['title']) ?>"
                                 class="card-img-top book-card__img"
                                 loading="lazy">
                        </a>
                    </div>
                    <div class="card-body p-2">
                        <p class="fw-semibold small mb-1 book-card__title">
                            <?= htmlspecialchars($rel['title']) ?>
                        </p>
                        <p class="text-muted x-small mb-1" style="font-size:.8rem">
                            <?= htmlspecialchars($rel['author']) ?>
                        </p>
                        <p class="text-danger fw-bold small mb-2">
                            <?= number_format($rel['price'], 0, ',', '.') ?>₫
                        </p>
                        <a href="/bookstore/product.php?id=<?= $rel['id'] ?>"
                           class="btn btn-outline-warning btn-sm w-100">
                            Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?><?php
// product.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// ── 1. LẤY VÀ VALIDATE THAM SỐ ID TỪ URL ────────────────────────────────────
// filter_input + FILTER_VALIDATE_INT chống tấn công truyền id dạng chuỗi/âm
$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$bookId || $bookId <= 0) {
    // ID không hợp lệ → chuyển về trang chủ
    header('Location: /bookstore/index.php');
    exit;
}

// ── 2. TRUY VẤN CHI TIẾT SÁCH, JOIN CATEGORIES ───────────────────────────────
$stmt = $pdo->prepare("
    SELECT  b.id,
            b.title,
            b.author,
            b.price,
            b.image,
            b.description,
            b.stock_quantity,
            c.name  AS category_name,
            c.id    AS category_id
    FROM  books b
    LEFT JOIN categories c ON b.category_id = c.id
    WHERE b.id = ?
    LIMIT 1
");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

// Không tìm thấy sách → 404 đơn giản rồi redirect
if (!$book) {
    header('Location: /bookstore/index.php');
    exit;
}

// ── 3. LẤY THÊM SÁCH CÙNG THỂ LOẠI (gợi ý) ──────────────────────────────────
$stmtRelated = $pdo->prepare("
    SELECT id, title, author, price, image
    FROM   books
    WHERE  category_id = ?
      AND  id != ?
    ORDER BY id DESC
    LIMIT 4
");
$stmtRelated->execute([$book['category_id'], $bookId]);
$relatedBooks = $stmtRelated->fetchAll();

// ── 4. XÁC ĐỊNH ĐƯỜNG DẪN ẢNH, FALLBACK NẾU KHÔNG CÓ ───────────────────────
$imgPath = '/bookstore/assets/images/books/' . $book['image'];
$imgSrc  = (!empty($book['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
            ? $imgPath
            : '/bookstore/assets/images/books/placeholder.png';

// ── 5. XỬ LÝ THÔNG BÁO SAU KHI THÊM GIỎ HÀNG (từ actions/add_to_cart.php) ──
// add_to_cart.php sẽ redirect về product.php?id=X&status=added hoặc &status=error
$cartStatus = $_GET['status'] ?? '';
?>

<!-- ========== NỘI DUNG TRANG CHI TIẾT SÁCH ========== -->
<main class="container my-5">

    <!-- Breadcrumb điều hướng -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="/bookstore/index.php" class="text-warning text-decoration-none">
                    <i class="bi bi-house-door me-1"></i>Trang chủ
                </a>
            </li>
            <?php if (!empty($book['category_name'])): ?>
            <li class="breadcrumb-item">
                <a href="/bookstore/index.php?category=<?= $book['category_id'] ?>"
                   class="text-warning text-decoration-none">
                    <?= htmlspecialchars($book['category_name']) ?>
                </a>
            </li>
            <?php endif; ?>
            <li class="breadcrumb-item active text-muted" aria-current="page">
                <?= htmlspecialchars($book['title']) ?>
            </li>
        </ol>
    </nav>

    <!-- Thông báo thêm giỏ hàng thành công / thất bại -->
    <?php if ($cartStatus === 'added'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-cart-check-fill me-2"></i>
            Đã thêm sách vào giỏ hàng thành công!
            <a href="/bookstore/cart.php" class="alert-link ms-2">Xem giỏ hàng →</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($cartStatus === 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Có lỗi xảy ra, vui lòng thử lại.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($cartStatus === 'login'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-person-lock me-2"></i>
            Bạn cần <a href="/bookstore/login.php" class="alert-link">đăng nhập</a>
            để thêm sách vào giỏ hàng.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── LAYOUT 2 CỘT CHÍNH ── -->
    <div class="row g-5">

        <!-- ══ CỘT TRÁI: ẢNH BÌA SÁCH ══ -->
        <div class="col-lg-4 col-md-5">
            <div class="product-img-wrap shadow rounded-3 overflow-hidden">
                <img
                    src="<?= htmlspecialchars($imgSrc) ?>"
                    alt="Bìa sách: <?= htmlspecialchars($book['title']) ?>"
                    class="img-fluid w-100 product-img"
                >
            </div>

            <!-- Thể loại bên dưới ảnh -->
            <?php if (!empty($book['category_name'])): ?>
            <div class="mt-3 text-center">
                <span class="badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill">
                    <i class="bi bi-tag me-1"></i>
                    <?= htmlspecialchars($book['category_name']) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- ══ CỘT PHẢI: THÔNG TIN CHI TIẾT + FORM ══ -->
        <div class="col-lg-8 col-md-7">

            <!-- Tên sách -->
            <h2 class="fw-bold mb-1"><?= htmlspecialchars($book['title']) ?></h2>

            <!-- Tác giả -->
            <p class="text-muted mb-3">
                <i class="bi bi-person-circle me-1"></i>
                Tác giả: <strong><?= htmlspecialchars($book['author']) ?></strong>
            </p>

            <!-- Giá bán -->
            <div class="product-price mb-3">
                <span class="fs-2 fw-bold text-danger">
                    <?= number_format($book['price'], 0, ',', '.') ?>₫
                </span>
            </div>

            <!-- Trạng thái tồn kho -->
            <div class="mb-3">
                <?php if ($book['stock_quantity'] > 10): ?>
                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                        <i class="bi bi-check-circle me-1"></i>
                        Còn hàng (<?= $book['stock_quantity'] ?> cuốn)
                    </span>
                <?php elseif ($book['stock_quantity'] > 0): ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        Sắp hết hàng (còn <?= $book['stock_quantity'] ?> cuốn)
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2">
                        <i class="bi bi-x-circle me-1"></i>
                        Hết hàng
                    </span>
                <?php endif; ?>
            </div>

            <hr class="my-4">

            <!-- Mô tả sách -->
            <?php if (!empty($book['description'])): ?>
            <div class="mb-4">
                <h6 class="fw-bold text-uppercase text-muted small mb-2 ls-1">
                    <i class="bi bi-card-text me-1"></i>Mô tả sách
                </h6>
                <div class="product-description text-secondary lh-lg">
                    <!-- nl2br chuyển xuống dòng trong DB thành <br> -->
                    <?= nl2br(htmlspecialchars($book['description'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── FORM THÊM VÀO GIỎ HÀNG ── -->
            <?php if ($book['stock_quantity'] > 0): ?>
            <form method="POST"
                  action="/bookstore/actions/add_to_cart.php"
                  class="add-to-cart-form">

                <!-- Truyền book_id vào hidden input -->
                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">

                <div class="d-flex align-items-center gap-3 flex-wrap">

                    <!-- Input số lượng -->
                    <div class="quantity-control d-flex align-items-center border rounded-3 overflow-hidden">
                        <!-- Nút giảm số lượng -->
                        <button type="button" class="btn btn-light px-3 py-2 qty-btn"
                                data-action="decrease" aria-label="Giảm số lượng">
                            <i class="bi bi-dash-lg"></i>
                        </button>

                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            class="form-control border-0 text-center fw-bold qty-input"
                            value="1"
                            min="1"
                            max="<?= $book['stock_quantity'] ?>"
                            style="width: 60px;"
                            aria-label="Số lượng"
                        >

                        <!-- Nút tăng số lượng -->
                        <button type="button" class="btn btn-light px-3 py-2 qty-btn"
                                data-action="increase"
                                data-max="<?= $book['stock_quantity'] ?>"
                                aria-label="Tăng số lượng">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>

                    <!-- Nút thêm vào giỏ hàng -->
                    <button type="submit" class="btn btn-warning btn-lg fw-bold px-4 flex-grow-1">
                        <i class="bi bi-cart-plus me-2"></i>Thêm vào giỏ hàng
                    </button>

                </div>

                <!-- Gợi ý tối đa có thể đặt -->
                <p class="text-muted small mt-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Tối đa <?= $book['stock_quantity'] ?> cuốn mỗi đơn hàng
                </p>

            </form>

            <?php else: ?>
                <!-- Sách hết hàng — ẩn form, hiển thị thông báo -->
                <div class="alert alert-secondary d-flex align-items-center gap-2">
                    <i class="bi bi-bell fs-5"></i>
                    <span>Sách tạm thời hết hàng. Vui lòng quay lại sau!</span>
                </div>
            <?php endif; ?>

        </div><!-- /.col cột phải -->
    </div><!-- /.row -->

    <!-- ── SÁCH CÙNG THỂ LOẠI ── -->
    <?php if (!empty($relatedBooks)): ?>
    <section class="mt-5 pt-4 border-top">
        <h5 class="fw-bold mb-4">
            <i class="bi bi-grid me-2 text-warning"></i>
            Sách cùng thể loại
        </h5>
        <div class="row row-cols-2 row-cols-md-4 g-3">
            <?php foreach ($relatedBooks as $rel): ?>
            <?php
                $relImg = '/bookstore/assets/images/books/' . $rel['image'];
                $relSrc = (!empty($rel['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $relImg))
                           ? $relImg
                           : '/bookstore/assets/images/books/placeholder.png';
            ?>
            <div class="col">
                <div class="card h-100 border-0 shadow-sm book-card">
                    <div class="book-card__img-wrap">
                        <a href="/bookstore/product.php?id=<?= $rel['id'] ?>">
                            <img src="<?= htmlspecialchars($relSrc) ?>"
                                 alt="<?= htmlspecialchars($rel['title']) ?>"
                                 class="card-img-top book-card__img"
                                 loading="lazy">
                        </a>
                    </div>
                    <div class="card-body p-2">
                        <p class="fw-semibold small mb-1 book-card__title">
                            <?= htmlspecialchars($rel['title']) ?>
                        </p>
                        <p class="text-muted x-small mb-1" style="font-size:.8rem">
                            <?= htmlspecialchars($rel['author']) ?>
                        </p>
                        <p class="text-danger fw-bold small mb-2">
                            <?= number_format($rel['price'], 0, ',', '.') ?>₫
                        </p>
                        <a href="/bookstore/product.php?id=<?= $rel['id'] ?>"
                           class="btn btn-outline-warning btn-sm w-100">
                            Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>