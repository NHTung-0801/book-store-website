# 📚 Book Store Website

Một hệ thống website thương mại điện tử quy mô nhỏ, tập trung vào việc giới thiệu và kinh doanh sách trực tuyến. Dự án được thiết kế với tiêu chí tối giản, hiệu năng cao, dễ dàng triển khai và bảo trì.

## 🎯 Mục tiêu dự án
Cung cấp trải nghiệm mua sắm mượt mà cho khách hàng, đồng thời tích hợp trang quản trị (Admin Panel) tinh gọn giúp chủ cửa hàng dễ dàng vận hành và quản lý dữ liệu mà không phụ thuộc vào các framework nặng nề.

## ⚙️ Các tính năng chính (Phân quyền 3 cấp độ)

### 1. Khách vãng lai (Guest)
* Xem giao diện Trang chủ (Banner, sách mới, sách bán chạy).
* Tìm kiếm sách theo từ khóa (Tên sách, tác giả).
* Lọc và xem sách theo danh mục thể loại.
* Xem chi tiết sách (Hình ảnh, mô tả, giá, số lượng tồn kho).

### 2. Thành viên (Logged-in User)
* Bao gồm toàn bộ quyền của Khách vãng lai.
* **Quản lý tài khoản:** Cập nhật thông tin cá nhân, số điện thoại, địa chỉ giao hàng.
* **Giỏ hàng vĩnh viễn (Database Cart):** Thêm, sửa, xóa sản phẩm. Dữ liệu lưu trong CSDL, không mất khi đăng xuất.
* **Thanh toán (Checkout):** Đặt hàng qua hình thức COD, tự động điền địa chỉ từ hồ sơ cá nhân.
* **Lịch sử mua hàng:** Theo dõi trạng thái đơn hàng (Chờ duyệt, Đang giao, Đã giao, Đã hủy).

### 3. Quản trị viên (Admin)
* **Quản lý Sách (CRUD):** Thêm, sửa, xóa, cập nhật giá và tồn kho, tải lên hình ảnh.
* **Quản lý Danh mục:** Phân loại sách.
* **Quản lý Đơn hàng:** Xem, duyệt, cập nhật trạng thái hoặc hủy đơn hàng.
* **Quản lý Người dùng:** Quản lý danh sách khách hàng đã đăng ký.

## 💻 Công nghệ sử dụng

**Frontend:**
* HTML5 / CSS3
* Bootstrap 5 (Responsive Design)
* Vanilla JavaScript

**Backend & Database:**
* PHP (Session, PDO)
* MySQL (Lưu trữ cấu trúc, hóa đơn, giỏ hàng)

## 📂 Cấu trúc thư mục

```text
bookstore/
├── actions/
│   ├── add_to_cart.php
│   ├── remove_cart.php
│   └── update_cart.php
├── admin/
│   ├── books.php
│   ├── categories.php
│   ├── index.php
│   ├── orders.php
│   └── users.php
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── images/
│   │   ├── banners/
│   │   ├── books/
│   │   └── logo.png
│   └── js/
│       └── main.js
├── config/
│   └── db.php
├── includes/
│   ├── admin_header.php
│   ├── footer.php
│   └── header.php
├── cart.php
├── checkout.php
├── index.php
├── login.php
├── logout.php
├── my_orders.php
├── product.php
├── profile.php
└── register.php
```

# 📚 Book Store

Website bán sách xây dựng bằng PHP thuần + MySQL + Bootstrap 5.

## Cài đặt

### Yêu cầu
- XAMPP (PHP 8.x, MySQL)
- Composer

### Các bước chạy dự án

1. Clone repository về máy:
git clone https://github.com/username/bookstore.git

2. Copy vào thư mục XAMPP:
C:\xampp\htdocs\bookstore\

3. Cài PHPMailer:
composer install

4. Import database:
   - Mở phpMyAdmin
   - Tạo database tên `bookstore_db`
   - Import file `bookstore_db.sql`

5. Cấu hình database:
   - Mở `config/db.php`
   - Sửa thông tin kết nối nếu cần

6. Cấu hình email (tính năng quên mật khẩu):
   - Copy file mẫu:
 config/mail.example.php → config/mail.php
   - Điền Gmail + App Password vào `config/mail.php`

7. Truy cập:
http://localhost/bookstore

## Tài khoản Admin mặc định
- Username: `admin`
- Password: `123456`