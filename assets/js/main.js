// main.js — Toggle hiện/ẩn mật khẩu
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function () {
        const targetId = this.getAttribute('data-target');
        const input    = document.getElementById(targetId);
        const icon     = this.querySelector('i');

        if (input.type === 'password') {
            input.type  = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type  = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
});

// ── QUANTITY CONTROL (trang product.php) ─────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const input  = this.closest('.quantity-control').querySelector('.qty-input');
            const max    = parseInt(input.getAttribute('max')) || 99;
            let   val    = parseInt(input.value) || 1;

            if (this.dataset.action === 'increase') {
                if (val < max) input.value = val + 1;
            } else {
                if (val > 1) input.value = val - 1;
            }
        });
    });

    // Ngăn người dùng nhập vượt quá giới hạn tồn kho
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function () {
            const max = parseInt(this.getAttribute('max')) || 99;
            const min = 1;
            if (parseInt(this.value) > max) this.value = max;
            if (parseInt(this.value) < min || isNaN(parseInt(this.value))) this.value = min;
        });
    });
});

// ── CART: AUTO-SUBMIT KHI THAY ĐỔI SỐ LƯỢNG ─────────────
document.addEventListener('DOMContentLoaded', function () {
    // Submit form update khi người dùng thay đổi input số lượng
    document.querySelectorAll('.cart-qty-input').forEach(input => {
        input.addEventListener('change', function () {
            const max = parseInt(this.getAttribute('max')) || 99;
            if (parseInt(this.value) < 1 || isNaN(parseInt(this.value))) this.value = 1;
            if (parseInt(this.value) > max) this.value = max;
            this.closest('.update-form').submit();
        });
    });
});