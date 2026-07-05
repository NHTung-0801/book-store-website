<?php
// includes/admin_footer.php
$currentYear = date('Y');
?>
    </div> 
    <footer class="py-3 bg-white border-top mt-auto">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center justify-content-between small">
                <div class="text-muted">
                    &copy; <?= $currentYear ?> <strong>NOVELTY Admin</strong>. Mọi quyền được bảo lưu.
                </div>
                <div>
                    <span class="text-muted">Phiên bản 1.0.0</span>
                </div>
            </div>
        </div>
    </footer>

</div> 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Toggle sidebar trên mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        if(sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                document.getElementById('adminSidebar').classList.toggle('show');
            });
        }

        // 2. Xác nhận xóa (SweetAlert2, fallback về confirm nếu không có Swal)
        const deleteButtons = document.querySelectorAll('.btn-delete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault(); // chặn hành vi mặc định, xử lý thủ công sau khi xác nhận

                const message = 'Hành động nguy hiểm: Bạn có chắc chắn muốn xóa dữ liệu này không? Quá trình này không thể hoàn tác!';

                function doAction() {
                    const form = button.closest('form');
                    if (form) {
                        form.submit();
                        return;
                    }
                    if (button.tagName.toLowerCase() === 'a' && button.href) {
                        window.location.href = button.href;
                        return;
                    }
                    // Nếu có data-href dùng làm fallback
                    const dataHref = button.getAttribute('data-href');
                    if (dataHref) {
                        window.location.href = dataHref;
                        return;
                    }
                    // Nếu không có gì để làm, thử kích hoạt click mặc định (không đảm bảo)
                    try { button.click(); } catch (err) {}
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Xác nhận',
                        text: message,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Có, xóa',
                        cancelButtonText: 'Hủy'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            doAction();
                        }
                    });
                } else {
                    // Fallback sang confirm() nếu SweetAlert2 chưa được nạp
                    if (confirm(message)) {
                        doAction();
                    }
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

        // 4. Xác nhận đăng xuất
        document.querySelectorAll('.logout-link').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault(); 
                const logoutUrl = this.getAttribute('href'); 

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Xác nhận đăng xuất',
                        text: "Bạn có chắc chắn muốn đăng xuất khỏi trang Quản trị không?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="bi bi-box-arrow-right me-1"></i> Có, Đăng xuất',
                        cancelButtonText: 'Hủy'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = logoutUrl;
                        }
                    });
                } else {
                    if (confirm("Bạn có chắc chắn muốn đăng xuất khỏi trang Quản trị không?")) {
                        window.location.href = logoutUrl;
                    }
                }
            });
        });
    });
</script>
</body>
</html>