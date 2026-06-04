<?php
// index.php

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// ── LẤY 12 CUỐN SÁCH MỚI NHẤT ────────────────────────────────────────────────
// JOIN với categories để lấy tên thể loại hiển thị trên card
$stmt = $pdo->prepare("
    SELECT b.id, b.title, b.author, b.price, b.image,
           b.stock_quantity, c.name AS category_name
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    ORDER BY b.id DESC
    LIMIT 12
");
$stmt->execute();
$books = $stmt->fetchAll();

// ── LẤY DANH SÁCH THỂ LOẠI CHO THANH LỌC ────────────────────────────────────
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
?>

<!-- ========== BANNER TRANG CHỦ ========== -->
<section class="hero-banner bg-dark text-white py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge bg-warning text-dark mb-3 px-3 py-2 fs-6">
                    🎉 Chào mừng đến với Book Store
                </span>
                <h1 class="display-5 fw-bold lh-sm mb-3">
                    Khám phá thế giới <br>
                    <span class="text-warning">tri thức bất tận</span>
                </h1>
                <p class="lead text-secondary mb-4">
                    Hàng nghìn đầu sách chất lượng — văn học, khoa học, kỹ năng sống
                    và nhiều thể loại khác đang chờ bạn khám phá.
                </p>
                <a href="#book-list" class="btn btn-warning btn-lg fw-bold px-4">
                    <i class="bi bi-book me-2"></i>Xem sách ngay
                </a>
            </div>
            <div class="col-lg-5 text-center d-none d-lg-block">
                <i class="bi bi-book-half text-warning" style="font-size: 10rem; opacity: .15;"></i>
            </div>
        </div>
    </div>
</section>

<!-- ========== DANH MỤC THỂ LOẠI ========== -->
<?php if (!empty($categories)): ?>
<section class="mb-5">
    <div class="container">
        <h5 class="fw-bold mb-3">
            <i class="bi bi-grid-3x3-gap me-2 text-warning"></i>Thể loại
        </h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="/bookstore/index.php"
               class="btn btn-sm btn-warning fw-semibold">
                Tất cả
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="/bookstore/index.php?category=<?= $cat['id'] ?>"
                   class="btn btn-sm btn-outline-secondary">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ========== DANH SÁCH SÁCH ========== -->
<section id="book-list" class="mb-5">
    <div class="container">

        <!-- Tiêu đề section -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="fw-bold mb-0">
                <i class="bi bi-stars me-2 text-warning"></i>Sách mới nhất
            </h4>
            <span class="text-muted small">
                Hiển thị <?= count($books) ?> cuốn sách
            </span>
        </div>

        <?php if (empty($books)): ?>
            <!-- Trạng thái rỗng — chưa có sách nào trong DB -->
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-3">Chưa có sách nào. Vui lòng quay lại sau!</p>
            </div>

        <?php else: ?>
            <!-- Grid hiển thị sách dạng card -->
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">

                <?php foreach ($books as $book): ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm book-card">

                        <!-- Ảnh bìa sách -->
                        <div class="book-card__img-wrap">
                            <?php
                                // Kiểm tra ảnh tồn tại, fallback về ảnh placeholder nếu không có
                                $imgPath = '/bookstore/assets/images/books/' . $book['image'];
                                $imgSrc  = (!empty($book['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
                                            ? $imgPath
                                            : '/bookstore/assets/images/books/placeholder.png';
                            ?>
                            <a href="/bookstore/product.php?id=<?= $book['id'] ?>">
                                <img
                                    src="<?= htmlspecialchars($imgSrc) ?>"
                                    alt="Bìa sách: <?= htmlspecialchars($book['title']) ?>"
                                    class="card-img-top book-card__img"
                                    loading="lazy"
                                >
                            </a>

                            <!-- Badge thể loại (hiển thị góc trên trái ảnh) -->
                            <?php if (!empty($book['category_name'])): ?>
                                <span class="badge bg-warning text-dark book-card__badge">
                                    <?= htmlspecialchars($book['category_name']) ?>
                                </span>
                            <?php endif; ?>

                            <!-- Badge hết hàng nếu stock = 0 -->
                            <?php if ($book['stock_quantity'] <= 0): ?>
                                <div class="book-card__overlay-soldout">
                                    <span>Hết hàng</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Nội dung card -->
                        <div class="card-body d-flex flex-column p-3">

                            <!-- Tên sách -->
                            <h6 class="card-title fw-bold mb-1 book-card__title">
                                <a href="/bookstore/product.php?id=<?= $book['id'] ?>"
                                   class="text-dark text-decoration-none stretched-link-title">
                                    <?= htmlspecialchars($book['title']) ?>
                                </a>
                            </h6>

                            <!-- Tác giả -->
                            <p class="text-muted small mb-2">
                                <i class="bi bi-person me-1"></i>
                                <?= htmlspecialchars($book['author']) ?>
                            </p>

                            <!-- Giá — đẩy xuống cuối card bằng mt-auto -->
                            <div class="mt-auto">
                                <p class="fw-bold text-danger fs-5 mb-3">
                                    <?= number_format($book['price'], 0, ',', '.') ?>
                                    <span class="fs-6">₫</span>
                                </p>

                                <!-- Nút Xem chi tiết -->
                                <a href="/bookstore/product.php?id=<?= $book['id'] ?>"
                                   class="btn btn-warning btn-sm w-100 fw-semibold
                                          <?= $book['stock_quantity'] <= 0 ? 'disabled' : '' ?>">
                                    <i class="bi bi-eye me-1"></i>Xem chi tiết
                                </a>
                            </div>

                        </div><!-- /.card-body -->
                    </div><!-- /.card -->
                </div><!-- /.col -->
                <?php endforeach; ?>

            </div><!-- /.row -->
        <?php endif; ?>

    </div><!-- /.container -->
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>