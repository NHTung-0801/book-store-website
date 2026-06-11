<?php
// index.php

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
    /* CSS cho vùng cuộn Thể loại sặc sỡ */
    .category-scroll {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 10px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch; /* Hỗ trợ vuốt mượt trên mobile */
    }
    
    /* Làm đẹp thanh cuộn ngang */
    .category-scroll::-webkit-scrollbar {
        height: 6px;
    }
    .category-scroll::-webkit-scrollbar-thumb {
        background-color: #cecece;
        border-radius: 10px;
    }
    .category-scroll::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .category-item {
        flex: 0 0 auto;
        width: 130px; /* Chiều rộng cố định để các khối đều nhau */
    }
    
    .category-card {
        transition: all 0.3s ease;
        border: 2px solid transparent !important;
        cursor: pointer;
    }
    
    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.08) !important;
        border-color: #ffc107 !important;
    }
    
    /* Hiệu ứng nổi bật khi Thể loại đang được chọn */
    .category-card.active {
        border-color: #ffc107 !important;
        box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4) !important;
        background-color: #fff !important; 
    }
</style>

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

<?php if (!empty($categories)): ?>
<section class="mb-5">
    <div class="container">
        <h5 class="fw-bold mb-3" style="color: #0f3460;">
            <i class="bi bi-grid-3x3-gap-fill me-2 text-warning"></i>Khám phá Thể loại
        </h5>
        
        <div class="category-scroll gap-3 mt-4">
            
            <div class="category-item">
                <a href="/bookstore/index.php" class="text-decoration-none">
                    <div class="card h-100 rounded-4 text-center category-card <?= !$selectedCategoryId ? 'active' : 'shadow-sm' ?>" style="background-color: #f8f9fa;">
                        <div class="card-body p-3 d-flex flex-column align-items-center justify-content-center">
                            <img src="https://img.icons8.com/3d-fluency/94/books.png" alt="Tất cả" style="width: 48px; height: 48px; object-fit: contain; margin-bottom: 8px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.9rem;">Tất cả sách</h6>
                        </div>
                    </div>
                </a>
            </div>

            <?php foreach ($categories as $cat): 
                $style = getCategoryStyle($cat['id']);
                $isActive = ($selectedCategoryId === (int)$cat['id']);
            ?>
            <div class="category-item">
                <a href="/bookstore/index.php?category=<?= $cat['id'] ?>" class="text-decoration-none">
                    <div class="card h-100 rounded-4 text-center category-card <?= $isActive ? 'active' : 'shadow-sm' ?>" style="background-color: <?= $isActive ? '#fff' : $style['bg'] ?>;">
                        <div class="card-body p-3 d-flex flex-column align-items-center justify-content-center">
                            <img src="<?= $style['icon'] ?>" alt="<?= htmlspecialchars($cat['name']) ?>" style="width: 48px; height: 48px; object-fit: contain; margin-bottom: 8px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
                            <h6 class="fw-bold mb-0 text-dark text-truncate w-100" style="font-size: 0.9rem;">
                                <?= htmlspecialchars($cat['name']) ?>
                            </h6>
                        </div>
                    </div>
                </a>
            </div>
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
                    <div class="card h-100 border-0 shadow-sm book-card">

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

                            <?php if (!empty($book['category_name'])): ?>
                                <span class="badge bg-warning text-dark book-card__badge">
                                    <?= htmlspecialchars($book['category_name']) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($book['stock_quantity'] <= 0): ?>
                                <div class="book-card__overlay-soldout">
                                    <span>Hết hàng</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-body d-flex flex-column p-3">

                            <h6 class="card-title fw-bold mb-1 book-card__title">
                                <a href="/bookstore/product.php?id=<?= $book['id'] ?>"
                                   class="text-dark text-decoration-none stretched-link-title" style="color: #0f3460 !important;">
                                    <?= htmlspecialchars($book['title']) ?>
                                </a>
                            </h6>

                            <p class="text-muted small mb-2">
                                <i class="bi bi-person me-1 text-warning"></i>
                                <?= htmlspecialchars($book['author']) ?>
                            </p>

                            <div class="mt-auto">
                                <p class="fw-bold text-danger fs-5 mb-3">
                                    <?= number_format($book['price'], 0, ',', '.') ?>
                                    <span class="fs-6">₫</span>
                                </p>

                                <a href="/bookstore/product.php?id=<?= $book['id'] ?>"
                                   class="btn btn-warning btn-sm w-100 fw-semibold shadow-sm
                                          <?= $book['stock_quantity'] <= 0 ? 'disabled' : '' ?>">
                                    <i class="bi bi-eye me-1"></i>Xem chi tiết
                                </a>
                            </div>

                        </div></div></div><?php endforeach; ?>

            </div><?php endif; ?>

    </div></section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>