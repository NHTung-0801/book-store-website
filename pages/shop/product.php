<?php
// product.php
$pageTitle = 'Chi tiết Sách | NOVELTY';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

// ── 1. LẤY VÀ VALIDATE THAM SỐ ID TỪ URL ────────────────────────────────────
$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$bookId || $bookId <= 0) {
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

if (!$book) {
    header('Location: /bookstore/index.php');
    exit;
}

// ── 3. LẤY THÊM SÁCH CÙNG THỂ LOẠI (gợi ý) ──────────────────────────────────
$stmtRelated = $pdo->prepare("
    SELECT id, title, author, price, image, stock_quantity
    FROM   books
    WHERE  category_id = ?
      AND  id != ?
    ORDER BY id DESC
    LIMIT 8
");
$stmtRelated->execute([$book['category_id'], $bookId]);
$relatedBooks = $stmtRelated->fetchAll();

// ── 4. XÁC ĐỊNH ĐƯỜNG DẪN ẢNH, FALLBACK NẾU KHÔNG CÓ ───────────────────────
$imgPath = '/bookstore/assets/images/books/' . $book['image'];
$imgSrc  = (!empty($book['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $imgPath))
            ? $imgPath
            : '/bookstore/assets/images/books/placeholder.png';

// ── 5. XỬ LÝ THÔNG BÁO SAU KHI THÊM GIỎ HÀNG ──────────────────────────────
$cartStatus = $_GET['status'] ?? '';
?>

<!-- ========== NỘI DUNG TRANG CHI TIẾT SÁCH ========== -->
<main class="container mx-auto px-4 pt-[76px] pb-16 min-h-screen" style="max-width: 1152px;">

    <!-- Thông báo thêm giỏ hàng -->
    <?php if ($cartStatus === 'added'): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-cart-check-fill me-2"></i>
            Đã thêm sách vào giỏ hàng thành công!
            <a href="/bookstore/pages/shop/cart.php" class="alert-link ms-2">Xem giỏ hàng →</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($cartStatus === 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Có lỗi xảy ra, vui lòng thử lại.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($cartStatus === 'login'): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-person-lock me-2"></i>
            Bạn cần <a href="/bookstore/pages/auth/login.php" class="alert-link">đăng nhập</a>
            để thêm sách vào giỏ hàng.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── IMMERSIVE SPLIT SCREEN LAYOUT ── -->
    <div class="row g-5 align-items-start">

        <!-- ══ CỘT TRÁI: STICKY VISUAL STAGE ══ -->
        <div class="col-md-5">
            <div class="sticky-md-top" style="top: 120px; z-index: 10;">
                <div class="book-glow-wrapper">
                    <img
                        src="<?= htmlspecialchars($imgSrc) ?>"
                        alt="Bìa sách: <?= htmlspecialchars($book['title']) ?>"
                        class="img-fluid w-100 book-cover-image"
                        style="object-fit: cover; aspect-ratio: 3/4;"
                    >
                </div>
            </div>
        </div>

        <!-- ══ CỘT PHẢI: EDITORIAL INFORMATION ══ -->
        <div class="col-md-7">
            
            <?php if (!empty($book['category_name'])): ?>
                <span style="display: inline-block; background-color: rgba(0,0,0,0.05); color: rgba(0,0,0,0.8); font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.75rem; border-radius: 9999px; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <?= htmlspecialchars($book['category_name']) ?>
                </span>
            <?php endif; ?>

            <h1 style="font-family: var(--font-heading); font-size: clamp(1.875rem, 4vw, 2.25rem); font-weight: 700; letter-spacing: -0.02em; color: #111111; margin-bottom: 0.5rem; line-height: 1.3;">
                <?= htmlspecialchars($book['title']) ?>
            </h1>
            
            <p style="font-size: 1rem; color: #4b5563; margin-bottom: 0.75rem; font-weight: 500;">
                Tác giả: <?= htmlspecialchars($book['author']) ?>
            </p>

            <div class="d-flex align-items-center mb-2" style="gap: 1rem;">
                <div style="font-size: 1.875rem; font-weight: 800; color: #FF4500;">
                    <?= number_format($book['price'], 0, ',', '.') ?>₫
                </div>
                
                <?php if ($book['stock_quantity'] > 0): ?>
                    <div style="border: 1px solid #198754; color: #198754; background-color: rgba(25, 135, 84, 0.1); padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                        <span class="pulse-dot-green"></span> IN STOCK
                    </div>
                <?php else: ?>
                    <div style="border: 1px solid #dc3545; color: #dc3545; background-color: rgba(220, 53, 69, 0.1); padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.875rem;">
                        OUT OF STOCK
                    </div>
                <?php endif; ?>
            </div>

            <hr style="margin: 1rem 0; border: none; border-top: 1px solid rgba(0,0,0,0.05);">

            <?php if (!empty($book['description'])): ?>
            <div class="editorial-description mb-3">
                <p><?= nl2br(htmlspecialchars($book['description'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- ── INTERACTIVE BUY BOX ── -->
            <?php if ($book['stock_quantity'] > 0): ?>
            <form method="POST" action="/bookstore/actions/add_to_cart.php" style="background-color: rgba(248, 246, 240, 0.6); border: 1px solid rgba(0,0,0,0.05); border-radius: 1rem; padding: 1rem; margin: 1rem 0; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);">
                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">

                <div class="d-flex flex-column flex-sm-row align-items-sm-center" style="gap: 1rem;">
                    <!-- Pill Quantity Selector -->
                    <div style="display: inline-flex; align-items: center; border: 1px solid rgba(0,0,0,0.15); background-color: #fff; border-radius: 9999px; padding: 0.25rem; box-shadow: inset 0 2px 4px 0 rgba(0,0,0,0.06);">
                        <button type="button" onclick="updateQty(-1)" aria-label="Giảm" style="width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center; border-radius: 9999px; border: none; background: transparent; cursor: pointer; font-weight: bold; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(0,0,0,0.05)'" onmouseout="this.style.backgroundColor='transparent'">-</button>
                        
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= $book['stock_quantity'] ?>" readonly style="width: 3rem; text-align: center; font-weight: 600; font-size: 0.875rem; background: transparent; border: none; outline: none; appearance: none;">
                        
                        <button type="button" onclick="updateQty(1)" aria-label="Tăng" style="width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center; border-radius: 9999px; border: none; background: transparent; cursor: pointer; font-weight: bold; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(0,0,0,0.05)'" onmouseout="this.style.backgroundColor='transparent'">+</button>
                    </div>

                    <!-- Add to Cart Button -->
                    <style>
                        .btn-shimmer-pro {
                            position: relative;
                            overflow: hidden;
                            background-color: #111111;
                            color: #ffffff;
                            font-weight: 600;
                            padding: 0.875rem 2rem;
                            border-radius: 9999px;
                            border: none;
                            cursor: pointer;
                            font-size: 0.875rem;
                            letter-spacing: 0.025em;
                            text-transform: uppercase;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 0.5rem;
                            transition: all 0.3s ease;
                            flex: 1;
                        }
                        .btn-shimmer-pro:hover {
                            background-color: #000000;
                            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                        }
                        .btn-shimmer-pro:active {
                            transform: scale(0.98);
                        }
                        .btn-shimmer-pro::after {
                            content: "";
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 50%;
                            height: 100%;
                            background-color: rgba(255, 255, 255, 0.15);
                            transform: skewX(-20deg) translateX(-150%);
                            transition: transform 1s cubic-bezier(0.4, 0, 0.2, 1);
                        }
                        .btn-shimmer-pro:hover::after {
                            transform: skewX(-20deg) translateX(300%);
                        }
                        .btn-shimmer-pro i {
                            transition: transform 0.3s ease;
                        }
                        .btn-shimmer-pro:hover i {
                            transform: translateY(-2px);
                        }
                    </style>
                    <button type="submit" class="btn-shimmer-pro">
                        <i class="bi bi-cart2 fs-5"></i> THÊM VÀO GIỎ HÀNG
                    </button>
                </div>
                
                <p class="text-muted small mt-3 fw-bold mb-0">
                    * Đã bao gồm thuế & phí giao hàng tiêu chuẩn.
                </p>
            </form>
            <?php else: ?>
                <div class="alert alert-secondary d-flex align-items-center gap-2 border-0 bg-light p-4 rounded-4">
                    <i class="bi bi-bell-fill fs-5"></i>
                    <span class="fw-bold">Tác phẩm này đang tạm hết hàng. Chúng tôi sẽ sớm bổ sung!</span>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Script xử lý Magnetic Button & Quantity -->
    <script>
        // Quantity Logic
        function updateQty(change) {
            const input = document.getElementById('quantity');
            let val = parseInt(input.value) + change;
            const max = parseInt(input.getAttribute('max'));
            if (val >= 1 && val <= max) {
                input.value = val;
                // Scale animation
                input.style.transform = 'scale(1.2)';
                setTimeout(() => { input.style.transform = 'scale(1)'; }, 150);
            }
        }

        // Magnetic Button Logic
        const btn = document.getElementById('magnetic-btn');
        if (btn) {
            btn.addEventListener('mousemove', function(e) {
                const rect = btn.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                
                btn.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`;
                btn.querySelector('.btn-text').style.transform = `translate(${x * 0.1}px, ${y * 0.1}px)`;
            });

            btn.addEventListener('mouseleave', function() {
                btn.style.transform = 'translate(0px, 0px)';
                btn.querySelector('.btn-text').style.transform = 'translate(0px, 0px)';
            });
        }
    </script>

        </div><!-- /.col cột phải -->
    </div><!-- /.row -->

<!-- ── SÁCH CÙNG THỂ LOẠI ── -->
<section style="border-top: 1px solid rgba(0,0,0,0.1); padding-top: 2rem; margin-top: 2.5rem;">

    <!-- Tiêu đề — luôn hiển thị dù có sách hay không -->
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em; color: #111111; margin-bottom: 0.5rem;">
                SÁCH CÙNG THỂ LOẠI
            </h2>
            <p class="text-muted small mb-0">
                Các cuốn sách thuộc thể loại
                <a href="/bookstore/index.php?category=<?= $book['category_id'] ?>"
                   class="text-decoration-underline text-dark fw-medium">
                    <?= htmlspecialchars($book['category_name']) ?>
                </a>
            </p>
        </div>
        <?php if (!empty($relatedBooks)): ?>
            <!-- Nút xem tất cả — chỉ hiện khi có sách, desktop -->
            <a href="/bookstore/index.php?category=<?= $book['category_id'] ?>"
               style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 9999px; border: 1px solid rgba(0,0,0,0.1); color: #111; font-weight: 600; font-size: 0.875rem; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.background='transparent'">
                Xem tất cả
                <i class="bi bi-arrow-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($relatedBooks)): ?>

        <!-- Grid sách liên quan — 4 cột desktop, 2 cột mobile -->
        <div class="row row-cols-2 row-cols-md-4 g-4">
            <?php foreach ($relatedBooks as $rel):
                $relImg = '/bookstore/assets/images/books/' . $rel['image'];
                $relSrc = (!empty($rel['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $relImg))
                           ? $relImg
                           : '/bookstore/assets/images/books/placeholder.png';
            ?>
            <div class="col">
                <div class="gallery-card">
                    <div class="gallery-card__img-wrap">
                        <a href="/bookstore/pages/shop/product.php?id=<?= $rel['id'] ?>">
                            <img src="<?= htmlspecialchars($relSrc) ?>" alt="<?= htmlspecialchars($rel['title']) ?>" class="gallery-card__img" loading="lazy">
                        </a>
                        
                        <?php if ($rel['stock_quantity'] <= 0): ?>
                            <div class="gallery-card__soldout"><span>Hết hàng</span></div>
                        <?php endif; ?>
                        
                        <?php if ($rel['stock_quantity'] > 0): ?>
                        <form method="POST" action="/bookstore/actions/add_to_cart.php" class="gallery-card__form">
                            <input type="hidden" name="book_id" value="<?= $rel['id'] ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="gallery-card__btn-add" title="Thêm vào giỏ">
                                <i class="bi bi-cart-plus"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <div class="gallery-card__info">
                        <h3 class="gallery-card__title">
                            <a href="/bookstore/pages/shop/product.php?id=<?= $rel['id'] ?>">
                                <?= htmlspecialchars($rel['title']) ?>
                            </a>
                        </h3>
                        <p class="gallery-card__author"><?= htmlspecialchars($rel['author']) ?></p>
                        
                        <span class="gallery-card__category">CÙNG THỂ LOẠI</span>

                        <div class="gallery-card__price">
                            <?= number_format($rel['price'], 0, ',', '.') ?>₫
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div><!-- /.row -->

        <!-- Nút xem tất cả — mobile -->
        <div class="text-center mt-4 d-md-none">
            <a href="/bookstore/index.php?category=<?= $book['category_id'] ?>"
               class="btn btn-outline-warning fw-semibold px-4">
                <i class="bi bi-grid-3x3-gap me-1"></i>
                Xem tất cả thể loại "<?= htmlspecialchars($book['category_name']) ?>"
            </a>
        </div>

    <?php else: ?>

        <!-- Trạng thái chưa có sách cùng thể loại -->
        <div style="border: 2px dashed rgba(0,0,0,0.1); border-radius: 1rem; padding: 2rem; text-align: center; max-width: 36rem; margin: 1.5rem auto; background-color: rgba(255,255,255,0.4);">
            <div class="mx-auto mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #f3f4f6; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-book text-muted" style="font-size: 1.5rem;"></i>
            </div>
            <h6 style="font-weight: 700; color: #111; margin-bottom: 0.25rem;">
                Sách cùng thể loại sẽ được cập nhật sau
            </h6>
            <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 1.5rem;">
                Chúng tôi đang bổ sung thêm sách thuộc thể loại
                <strong style="color: #111;">
                    <?= htmlspecialchars($book['category_name']) ?>
                </strong>.
                Hãy quay lại sau nhé!
            </p>
            <a href="/bookstore/index.php"
               style="display: inline-flex; align-items: center; gap: 0.5rem; background-color: #111111; color: #ffffff; font-weight: 500; padding: 0.75rem 1.5rem; border-radius: 9999px; font-size: 0.875rem; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.3s;" onmouseover="this.style.background='#1f2937'; this.querySelector('.arrow-icon').style.transform='translateX(-4px)'" onmouseout="this.style.background='#111111'; this.querySelector('.arrow-icon').style.transform='translateX(0)'">
                <i class="bi bi-arrow-left arrow-icon" style="transition: transform 0.3s;"></i> VỀ TRANG CHỦ
            </a>
        </div>

    <?php endif; ?>

</section>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>