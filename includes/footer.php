<?php
// includes/footer.php
// Lấy năm hiện tại tự động — không cần cập nhật thủ công hàng năm
$currentYear = date('Y');
?>

<!-- ========== FOOTER ========== -->
<footer class="bg-dark text-light mt-5 pt-5 pb-4">
    <div class="container">
        <div class="row gy-4">

            <!-- Cột 1: Thương hiệu + mô tả ngắn -->
            <div class="col-lg-4 col-md-6">
                <h5 class="fw-bold mb-3">
                    <i class="bi bi-book-half me-2 text-warning"></i>Book Store
                </h5>
                <p class="text-secondary small">
                    Nơi quy tụ hàng nghìn đầu sách chất lượng — từ văn học, khoa học
                    đến kỹ năng sống. Mang tri thức đến gần hơn với bạn đọc Việt Nam.
                </p>
            </div>

            <!-- Cột 2: Liên kết nhanh -->
            <div class="col-lg-2 col-md-6">
                <h6 class="fw-semibold text-uppercase text-warning mb-3">Liên kết</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2">
                        <a href="/bookstore/index.php"
                           class="text-secondary text-decoration-none footer-link">
                            <i class="bi bi-chevron-right me-1"></i>Trang chủ
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="/bookstore/cart.php"
                           class="text-secondary text-decoration-none footer-link">
                            <i class="bi bi-chevron-right me-1"></i>Giỏ hàng
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="/bookstore/my_orders.php"
                           class="text-secondary text-decoration-none footer-link">
                            <i class="bi bi-chevron-right me-1"></i>Đơn hàng
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="/bookstore/profile.php"
                           class="text-secondary text-decoration-none footer-link">
                            <i class="bi bi-chevron-right me-1"></i>Tài khoản
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Cột 3: Thông tin liên hệ -->
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-semibold text-uppercase text-warning mb-3">Liên hệ</h6>
                <ul class="list-unstyled small text-secondary">
                    <li class="mb-2">
                        <i class="bi bi-geo-alt-fill me-2 text-warning"></i>
                        123 Đường Sách, Q.1, TP.HCM
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-telephone-fill me-2 text-warning"></i>
                        <a href="tel:+84901234567"
                           class="text-secondary text-decoration-none footer-link">
                            0901 234 567
                        </a>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-envelope-fill me-2 text-warning"></i>
                        <a href="mailto:support@bookstore.vn"
                           class="text-secondary text-decoration-none footer-link">
                            support@bookstore.vn
                        </a>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-clock-fill me-2 text-warning"></i>
                        Thứ 2 – Thứ 7: 8:00 – 21:00
                    </li>
                </ul>
            </div>

            <!-- Cột 4: Mạng xã hội -->
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-semibold text-uppercase text-warning mb-3">Theo dõi chúng tôi</h6>
                <div class="d-flex gap-2">
                    <a href="#" class="btn btn-outline-secondary btn-sm social-btn"
                       title="Facebook" aria-label="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="#" class="btn btn-outline-secondary btn-sm social-btn"
                       title="Instagram" aria-label="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="#" class="btn btn-outline-secondary btn-sm social-btn"
                       title="YouTube" aria-label="YouTube">
                        <i class="bi bi-youtube"></i>
                    </a>
                    <a href="#" class="btn btn-outline-secondary btn-sm social-btn"
                       title="TikTok" aria-label="TikTok">
                        <i class="bi bi-tiktok"></i>
                    </a>
                </div>
                <p class="text-secondary small mt-3">
                    Theo dõi để cập nhật sách mới<br>và khuyến mãi hấp dẫn mỗi tuần!
                </p>
            </div>

        </div><!-- /.row -->

        <!-- Đường kẻ phân cách -->
        <hr class="border-secondary mt-4 mb-3">

        <!-- Bản quyền -->
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="small text-secondary mb-0">
                    &copy; <?= $currentYear ?> <strong class="text-light">Book Store</strong>.
                    Tất cả quyền được bảo lưu.
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <p class="small text-secondary mb-0">
                    Thiết kế với <i class="bi bi-heart-fill text-danger mx-1"></i>
                    bởi <strong class="text-light">Book Store Team</strong>
                </p>
            </div>
        </div>

    </div><!-- /.container -->
</footer>
<!-- ========== /FOOTER ========== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>
</html>