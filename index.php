<?php
// index.php
$pageTitle = 'Trang chủ | NOVELTY - Thế giới Sách & Tri thức';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

// ── 1. KIỂM TRA THAM SỐ LỌC THỂ LOẠI TỪ URL ──────────────────────────────────
$selectedCategoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);

// ── 2. LẤY SÁCH (CÓ XỬ LÝ ĐIỀU KIỆN LỌC) ─────────────────────────────────────
// Xây dựng câu SQL động: Nếu có category thì thêm WHERE, nếu không thì lấy tất cả
$sql = "
    SELECT b.id, b.title, b.author, b.price, b.image,
           b.stock_quantity, c.name AS category_name
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
";

$params = [];
// Nếu người dùng chọn 1 thể loại cụ thể
if ($selectedCategoryId) {
    $sql .= " WHERE b.category_id = ? ";
    $params[] = $selectedCategoryId;
}

$sql .= " ORDER BY b.id DESC LIMIT 12";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();

// ── 3. LẤY DANH SÁCH THỂ LOẠI CHO THANH LỌC ─────────────────────────────────
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// ── HÀM HELPER TẠO MÀU VÀ ICON CHO THỂ LOẠI (Làm sặc sỡ giao diện) ─────────
function getCategoryStyle($id) {
    // Danh sách các icon 3D Fluency cực kỳ sinh động
    $icons = [
        'book', 'idea', 'rocket', 'heart-with-pulse', 'briefcase',
        'globe', 'color-palette', 'microscope', 'music-record', 'trophy'
    ];
    // Danh sách các màu nền pastel dịu nhẹ tương ứng
    $colors = [
        '#e3f2fd', '#fce4ec', '#f3e5f5', '#e8f5e9', '#fff8e1',
        '#fff3e0', '#e0f7fa', '#fbe9e7', '#efebe9', '#eceff1'
    ];
    
    $index = $id % 10; // Thuật toán chia lấy dư để ID nào cũng có màu/icon cố định
    
    return [
        'icon' => "https://img.icons8.com/3d-fluency/94/{$icons[$index]}.png",
        'bg'   => $colors[$index]
    ];
}
?>

<style>
    /* ========== HERO BOOK 3D & GLOW ========== */
    .featured-book-stage {
        position: relative;
        z-index: 1;
        animation: floating-book 4s ease-in-out infinite;
    }

    .featured-book-stage::before {
        content: '';
        position: absolute;
        top: -1rem;
        left: -1rem;
        right: -1rem;
        bottom: -1rem;
        background: linear-gradient(to right, rgba(250, 204, 21, 0.4), rgba(249, 115, 22, 0.4));
        filter: blur(30px);
        z-index: -1;
        border-radius: inherit;
    }

    @keyframes floating-book {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-8px); }
        100% { transform: translateY(0px); }
    }
</style>

<!-- ========== EDITORIAL HERO SECTION ========== -->
<section class="editorial-hero mb-0 pt-[76px]">
    <div class="container">
        <div class="bento-grid">
            
            <!-- Left Block: Kinetic Typography -->
            <div class="bento-left">
                <h1 class="hero-title">
                    Khám phá <br> thế giới <br>
                    <span class="highlight-text">tri thức</span> <br>
                    bất tận.
                </h1>
                <p class="hero-desc mt-4">
                    NOVELTY — Không gian triển lãm nghệ thuật của ngôn từ. Hàng nghìn đầu sách chất lượng — văn học, khoa học, và nghệ thuật sống đang chờ bạn lật giở.
                </p>
                <a href="#book-list" class="btn-neo-brutalist mt-4">
                    Khám phá ngay <i class="bi bi-arrow-down-right ms-2 fs-5"></i>
                </a>
            </div>

            <!-- Right Block: 3D Stage -->
            <div class="bento-right d-none d-md-flex">
                <div class="featured-book-stage" data-tilt data-tilt-glare data-tilt-max-glare="0.5" data-tilt-scale="1.05" data-tilt-speed="400">
                    <?php 
                        // Lấy ngẫu nhiên một ảnh sách hoặc dùng bìa mới nhất làm Featured
                        $featuredImg = (!empty($books) && !empty($books[0]['image'])) 
                            ? '/bookstore/assets/images/books/' . $books[0]['image'] 
                            : '/bookstore/assets/images/books/placeholder.png'; 
                    ?>
                    <img src="<?= htmlspecialchars($featuredImg) ?>" alt="Featured Book" class="featured-book-img">
                </div>
                <div class="stage-shadow"></div>
            </div>

        </div>
    </div>
</section>

<!-- ========== INFINITE MARQUEE ========== -->
<div class="marquee-container mt-5 mb-5">
    <div class="marquee-content">
        <!-- Khối 1 -->
        <span>⚡ NOVELTY — KHÔNG GIAN TRI THỨC BẤT TẬN</span><span class="separator">—</span>
        <span>📚 HƠN 10,000 ĐẦU SÁCH CHỌN LỌC</span><span class="separator">—</span>
        <span>🎨 TRẢI NGHIỆM ĐỌC ĐỈNH CAO</span><span class="separator">—</span>
        <!-- Khối 2 -->
        <span>⚡ NOVELTY — KHÔNG GIAN TRI THỨC BẤT TẬN</span><span class="separator">—</span>
        <span>📚 HƠN 10,000 ĐẦU SÁCH CHỌN LỌC</span><span class="separator">—</span>
        <span>🎨 TRẢI NGHIỆM ĐỌC ĐỈNH CAO</span><span class="separator">—</span>
        <!-- Khối 3 (Để animation translateX(-50%) mượt mà) -->
        <span>⚡ NOVELTY — KHÔNG GIAN TRI THỨC BẤT TẬN</span><span class="separator">—</span>
        <span>📚 HƠN 10,000 ĐẦU SÁCH CHỌN LỌC</span><span class="separator">—</span>
        <span>🎨 TRẢI NGHIỆM ĐỌC ĐỈNH CAO</span><span class="separator">—</span>
        <!-- Khối 4 -->
        <span>⚡ NOVELTY — KHÔNG GIAN TRI THỨC BẤT TẬN</span><span class="separator">—</span>
        <span>📚 HƠN 10,000 ĐẦU SÁCH CHỌN LỌC</span><span class="separator">—</span>
        <span>🎨 TRẢI NGHIỆM ĐỌC ĐỈNH CAO</span><span class="separator">—</span>
    </div>
</div>

<!-- Vanilla Tilt JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-tilt/1.8.1/vanilla-tilt.min.js" integrity="sha512-wC/cunGGDjXSl9OHsu004bNJ3s3C9cO0kQvKx8E3o85X2F/H1K3jY/uBwF/nUulh/4P125o8bI19wY6wMwXnQA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<?php if (!empty($categories)): ?>
<section class="mb-5">
    <div class="container">
        <h5 class="fw-bold mb-3" style="color: #0f3460;">
            <i class="bi bi-grid-3x3-gap-fill me-2 text-warning"></i>Khám phá Thể loại
        </h5>
        
        <div class="category-pill-container mt-2">
            
            <a href="/bookstore/index.php" class="category-pill <?= !$selectedCategoryId ? 'active' : '' ?>">
                <img src="https://img.icons8.com/3d-fluency/94/books.png" alt="Tất cả">
                <span>Tất cả sách</span>
            </a>

            <?php foreach ($categories as $cat): 
                $style = getCategoryStyle($cat['id']);
                $isActive = ($selectedCategoryId === (int)$cat['id']);
            ?>
            <a href="/bookstore/index.php?category=<?= $cat['id'] ?>" class="category-pill <?= $isActive ? 'active' : '' ?>">
                <img src="<?= $style['icon'] ?>" alt="<?= htmlspecialchars($cat['name']) ?>">
                <span><?= htmlspecialchars($cat['name']) ?></span>
            </a>
            <?php endforeach; ?>
            
        </div>
    </div>
</section>
<?php endif; ?>

<section id="book-list" class="mb-5">
    <div class="container">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="fw-bold mb-0" style="color: #0f3460;">
                <i class="bi bi-stars me-2 text-warning"></i>
                <?= $selectedCategoryId ? 'Kết quả lọc' : 'Sách mới nhất' ?>
            </h4>
            <span class="text-muted small">
                Hiển thị <?= count($books) ?> cuốn sách
            </span>
        </div>

        <?php if (empty($books)): ?>
            <div class="text-center py-5">
                <img src="https://img.icons8.com/3d-fluency/94/box-important.png" style="width: 80px; filter: grayscale(1); opacity: 0.6;" class="mb-3">
                <p class="text-muted mt-2">Chưa có sách nào thuộc thể loại này. Vui lòng thử thể loại khác!</p>
            </div>

        <?php else: ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">

                <?php foreach ($books as $book): ?>
                <div class="col">
                    <div class="gallery-card">
                        <div class="gallery-card__img-wrap">
                            <a href="/bookstore/pages/shop/product.php?id=<?= $book['id'] ?>">
                                <?php
                                    $imgPath = '/bookstore/assets/images/books/' . $book['image'];
                                    $imgSrc  = (!empty($book['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
                                                ? $imgPath
                                                : '/bookstore/assets/images/books/placeholder.png';
                                ?>
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="gallery-card__img" loading="lazy">
                            </a>
                            
                            <?php if ($book['stock_quantity'] <= 0): ?>
                                <div class="gallery-card__soldout"><span>Hết hàng</span></div>
                            <?php endif; ?>
                            
                            <!-- Nút Action Ẩn đầy nghệ thuật -->
                            <?php if ($book['stock_quantity'] > 0): ?>
                            <form method="POST" action="/bookstore/actions/add_to_cart.php" class="gallery-card__form">
                                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="gallery-card__btn-add" title="Thêm vào giỏ">
                                    <i class="bi bi-cart-plus"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <div class="gallery-card__info">
                            <h3 class="gallery-card__title">
                                <a href="/bookstore/pages/shop/product.php?id=<?= $book['id'] ?>">
                                    <?= htmlspecialchars($book['title']) ?>
                                </a>
                            </h3>
                            <p class="gallery-card__author"><?= htmlspecialchars($book['author']) ?></p>
                            
                            <?php if (!empty($book['category_name'])): ?>
                                <span class="gallery-card__category"><?= htmlspecialchars($book['category_name']) ?></span>
                            <?php endif; ?>

                            <div class="gallery-card__price">
                                <?= number_format($book['price'], 0, ',', '.') ?>₫
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
            <?php endif; ?>

    </div></section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>