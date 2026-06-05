<?php
// includes/admin_footer.php
$currentYear = date('Y');
?>
    </div><footer class="py-3 bg-light border-top mt-auto">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center justify-content-between small">
                <div class="text-muted">
                    &copy; <?= $currentYear ?> <strong>Book Store Admin</strong>. Mọi quyền được bảo lưu.
                </div>
                <div>
                    <span class="text-muted">Phiên bản 1.0.0</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // 1. Script xác nhận trước khi xóa (Rất quan trọng cho các trang CRUD)
            // Cách dùng: Chỉ cần thêm class="btn-delete" vào bất kỳ thẻ <a> hoặc <button> xóa nào
            const deleteButtons = document.querySelectorAll('.btn-delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Hành động nguy hiểm: Bạn có chắc chắn muốn xóa dữ liệu này không? Quá trình này không thể hoàn tác!')) {
                        // Nếu bấm Cancel, chặn sự kiện chuyển trang/submit form
                        e.preventDefault();
                    }
                });
            });

            // 2. Tự động ẩn các thông báo lỗi/thành công (Alert) sau 3.5 giây để giao diện gọn gàng
            const autoCloseAlerts = document.querySelectorAll('.alert-dismissible');
            if (autoCloseAlerts.length > 0) {
                setTimeout(() => {
                    autoCloseAlerts.forEach(alert => {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    });
                }, 3500);
            }
        });
    </script>
</body>
</html>