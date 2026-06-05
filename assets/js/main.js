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

// ── PASSWORD STRENGTH METER ───────────────────────────────
const newPwInput = document.getElementById('new_password');
if (newPwInput) {
    newPwInput.addEventListener('input', function () {
        const val = this.value;
        const bar = document.getElementById('pwStrengthBar');
        const txt = document.getElementById('pwStrengthText');
        if (!bar || !txt) return;

        let score = 0;
        if (val.length >= 6)                    score++;
        if (val.length >= 10)                   score++;
        if (/[A-Z]/.test(val))                  score++;
        if (/[0-9]/.test(val))                  score++;
        if (/[^A-Za-z0-9]/.test(val))           score++;

        const levels = [
            { w: '0%',   bg: '',          text: '' },
            { w: '25%',  bg: '#dc3545',   text: '😟 Rất yếu' },
            { w: '50%',  bg: '#fd7e14',   text: '😐 Trung bình' },
            { w: '75%',  bg: '#ffc107',   text: '🙂 Khá mạnh' },
            { w: '100%', bg: '#198754',   text: '💪 Rất mạnh' },
        ];
        const lv = levels[Math.min(score, 4)];
        bar.style.width      = lv.w;
        bar.style.background = lv.bg;
        txt.textContent      = lv.text;
    });
}