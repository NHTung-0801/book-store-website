<?php
// includes/admin_footer.php
$currentYear = date('Y');
?>
    </div> <footer class="py-3 bg-white border-top mt-auto">
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

</div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Toggle sidebar trên mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        if(sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                document.getElementById('adminSidebar').classList.toggle('show');
            });
        }

        // 2. Xác nhận xóa
        const deleteButtons = document.querySelectorAll('.btn-delete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Hành động nguy hiểm: Bạn có chắc chắn muốn xóa dữ liệu này không? Quá trình này không thể hoàn tác!')) {
                    e.preventDefault();
                }
            });
        });

        // 3. Tự động đóng Alert
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