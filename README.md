# 📚 NOVELTY Website

🔗 **Live Demo (Đã triển khai):** [http://novelty-bookstore.lovestoblog.com](http://novelty-bookstore.lovestoblog.com)

Một hệ thống website thương mại điện tử quy mô nhỏ, tập trung vào việc giới thiệu và kinh doanh sách trực tuyến. Dự án được thiết kế với tiêu chí tối giản, hiệu năng cao, dễ dàng triển khai và bảo trì.

## 🎯 Mục tiêu dự án
Cung cấp trải nghiệm mua sắm mượt mà cho khách hàng, đồng thời tích hợp trang quản trị (Admin Panel) tinh gọn giúp chủ cửa hàng dễ dàng vận hành và quản lý dữ liệu mà không phụ thuộc vào các framework nặng nề.

## ⚙️ Các tính năng chính (Phân quyền 3 cấp độ)

### 1. Khách Vãng Lai (Guest)
* Xem giao diện Trang chủ phong cách hiện đại (Banners, danh sách sách mới cập nhật, sách bán chạy).
* Tìm kiếm sách thông minh theo từ khóa (Tên sách, tác giả).
* Lọc danh mục sản phẩm trực quan theo từng Thể loại (Category).
* Xem trang chi tiết sản phẩm đầy đủ thông tin (Hình ảnh bìa, tác giả, giá tiền, mô tả chi tiết, số lượng tồn kho thực tế) cùng gợi ý các tựa sách cùng thể loại.

### 2. Thành Viên (Logged-in User)
* **Quản lý tài khoản:** Xem và cập nhật thông tin cá nhân (Họ tên, số điện thoại, địa chỉ giao hàng mặc định).
* **Giỏ hàng vĩnh viễn (Database-backed Cart):** Thêm sản phẩm, điều chỉnh số lượng (có chặn kiểm tra giới hạn tồn kho bằng AJAX/JS), xóa sản phẩm khỏi giỏ. Dữ liệu được lưu trực tiếp vào CSDL nên không bị mất khi đăng xuất hoặc đổi thiết bị.
* **Thanh toán thông minh (Checkout):** Đặt hàng nhanh chóng theo hình thức COD (Thanh toán khi nhận hàng), tự động đồng bộ hóa thông tin giao hàng từ hồ sơ cá nhân. Tích hợp cơ chế *Database Transaction* bảo vệ toàn vẹn dữ liệu.
* **Lịch sử & Tiến trình đơn hàng:** Theo dõi trực quan trạng thái xử lý đơn hàng theo 5 giai đoạn rõ ràng (Chờ xác nhận ➔ Đã xác nhận ➔ Đang giao hàng ➔ Đã giao hàng / Đã hủy).
* **Xác thực an toàn:** Tính năng quên mật khẩu qua Email mã hóa token tự động hết hạn, biểu đồ đo lường độ mạnh mật khẩu trực thì và tính năng ẩn/hiện mật khẩu thân thiện.

### 3. Quản Trị Viên (Admin)
Sở hữu không gian làm việc biệt lập (`admin/`) kiểm soát toàn bộ vòng đời dữ liệu hệ thống:
* **Dashboard (Tổng quan):** Thống kê trực quan tổng số sách, tổng đơn hàng, tổng thành viên, biểu đồ doanh thu thuần (chỉ tính các đơn đã giao thành công), cảnh báo danh sách sản phẩm sắp hết hàng hoặc đã cháy hàng.
* **Quản lý sách (Books Manager):** Thêm mới sách (tự động xử lý upload ảnh an toàn, kiểm tra định dạng và dung lượng file), sửa thông tin sách, cập nhật số lượng kho và xóa sách. Tích hợp phân trang dữ liệu (Pagination).
* **Quản lý thể loại (Categories Manager):** Thêm, sửa, xóa danh mục thể loại. Hệ thống có cơ chế ràng buộc thông minh: Không cho phép xóa thể loại nếu đang có sách thuộc thể loại đó để tránh lỗi mồ côi dữ liệu (Orphan Data).
* **Quản lý đơn hàng (Orders Manager):** Xem chi tiết danh sách sản phẩm trong từng đơn, cập nhật trạng thái đơn hàng (Duyệt đơn, Đang giao, Đã giao, Hủy đơn). Đóng băng dữ liệu (không cho phép sửa đổi) đối với các đơn hàng đã ở trạng thái cuối cùng (`delivered`, `cancelled`). Hiển thị badge thông báo đỏ thời gian thực cho các đơn hàng mới đang chờ duyệt.
* **Quản lý thành viên (Users Manager):** Xem danh sách tài khoản, tìm kiếm, lọc theo vai trò, hỗ trợ cơ chế phân quyền nhanh (Cấp quyền Admin hoặc hạ quyền thành User) với ràng buộc bảo mật tối cao (Admin không thể tự hạ quyền chính mình).

---
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
├── actions/                  # Xử lý logic ngầm (Chỉ nhận POST request, không chứa UI)
│   ├── add_to_cart.php       # Logic thêm sản phẩm và cộng dồn số lượng vào giỏ hàng
│   ├── remove_cart.php       # Logic xóa sản phẩm khỏi giỏ hàng của user
│   └── update_cart.php       # Logic cập nhật số lượng giỏ hàng (chặn vượt quá tồn kho)
│
├── admin/                    # Khu vực biệt lập của Quản trị viên
│   ├── books.php             # Quản lý toàn bộ vòng đời sản phẩm sách
│   ├── categories.php        # Quản lý danh mục thể loại sách
│   ├── index.php             # Trang Dashboard thống kê doanh thu và dữ liệu hệ thống
│   ├── orders.php            # Quản lý và duyệt trạng thái đơn hàng của toàn hệ thống
│   └── users.php             # Quản lý thành viên và phân quyền tài khoản
│
├── assets/                   # Tài nguyên tĩnh của hệ thống
│   ├── css/
│   │   ├── admin_style.css   # Giao diện dành riêng cho Admin Panel
│   │   └── style.css         # Giao diện trang chính của khách hàng
│   ├── images/
│   │   └── books/            # Thư mục lưu trữ ảnh bìa sách (ảnh mặc định & ảnh upload)
│   └── js/
│       └── main.js           # Xử lý JS dùng chung (Toggle password, độ mạnh mật khẩu, qty control)
│
├── config/                   # Các file cấu hình hệ thống
│   ├── db.php                # Kết nối cơ sở dữ liệu qua PDO an toàn
│   ├── mail.php              # Cấu hình tài khoản gửi Email SMTP thực tế (.gitignore bảo vệ)
│   └── mail.example.php      # File cấu hình mẫu cấu trúc mail phục vụ cho deploy
│
├── includes/                 # Các thành phần giao diện dùng chung (Layout Fragments)
│   ├── header.php            # Thanh điều hướng Navbar chính của khách hàng
│   ├── footer.php            # Chân trang chính của khách hàng & nhúng thư viện JS
│   ├── admin_header.php      # Thanh Sidebar, Topbar và bức tường bảo mật bảo vệ Admin
│   └── admin_footer.php      # Chân trang và xử lý JS nghiệp vụ (SweetAlert2) của Admin
│
├── pages/                    # [MỚI] Không gian gom các file hiển thị của User theo Module
│   ├── auth/                 # Module Xác thực tài khoản
│   │   ├── login.php         # Giao diện và xử lý đăng nhập
│   │   ├── register.php      # Giao diện và đăng ký tài khoản mới (Tích hợp SweetAlert2)
│   │   ├── forgot_password.php # Yêu cầu gửi link đặt lại mật khẩu qua email
│   │   ├── reset_password.php  # Nhận diện token an toàn, hiển thị form đổi mật khẩu mới
│   │   └── logout.php        # Xử lý hủy session, xóa cookie trình duyệt an toàn
│   │
│   ├── shop/                 # Module Mua sắm & Tiến trình đơn hàng
│   │   ├── cart.php          # Giao diện chi tiết giỏ hàng người dùng
│   │   ├── checkout.php      # Form điền thông tin giao hàng và xác nhận đặt đơn
│   │   └── product.php       # Trang thông tin chi tiết một cuốn sách cụ thể
│   │
│   └── user/                 # Module Quản lý cá nhân
│       ├── profile.php       # Xem thông tin cá nhân và lịch sử thống kê chi tiêu
│       └── my_orders.php     # Theo dõi danh sách đơn hàng đã mua và tiến trình xử lý
│
├── index.php                 # Điểm đón tiếp chính (Trang chủ website nằm tại thư mục gốc)
├── vendor/                   # Các thư viện bên thứ ba quản lý bởi Composer (PHPMailer,...)
├── composer.json             # Định nghĩa các package phụ thuộc của dự án
├── composer.lock             # Khóa phiên bản chính xác của các thư viện cài đặt
└── .gitignore                # Chặn đẩy các file nhạy cảm (như mail.php chứa pass thật) lên GitHub
```

# 📚 NOVELTY

Website bán sách xây dựng bằng PHP thuần + MySQL + Bootstrap 5.

## Cài đặt

### Yêu cầu
- Phần mềm môi trường: XAMPP (Hỗ trợ PHP phiên bản từ 8.0 trở lên, MySQL / MariaDB).
- Công cụ quản lý thư viện: Composer (Đã cài đặt trên máy).

### Các bước chạy dự án

1. Tải mã nguồn dự án
   - Clone repository này về máy tính của bạn và di chuyển thư mục dự án vào phân vùng chạy web của XAMPP (htdocs):
   - cd C:\xampp\htdocs
   - git clone [https://github.com/nhtung-0801/book-store-website.git](https://github.com/nhtung-0801/book-store-website.git) bookstore

2. Cài đặt các thư viện phụ thuộc (Dependencies)
   - Mở terminal/cmd tại thư mục gốc của dự án (C:/xampp/htdocs/bookstore) và chạy lệnh cài đặt Composer để tự động thiết lập thư viện gửi thư PHPMailer:
composer install

3. Thiết lập Cơ sở dữ liệu
   - Khởi động hai dịch vụ Apache và MySQL trên ứng dụng XAMPP Control Panel.
   - Truy cập vào trình quản lý dữ liệu http://localhost/phpmyadmin/.
   - Tạo một cơ sở dữ liệu mới với tên gọi chính xác là: bookstore_db (Chọn bảng mã collation là utf8mb4_general_ci).
   - Chọn cơ sở dữ liệu vừa tạo, nhấn vào thẻ Import, chọn file cấu hình SQL đi kèm trong dự án (ví dụ: bookstore_db.sql) và nhấn Import/Go để khởi tạo toàn bộ cấu trúc bảng và dữ liệu mẫu.

4. Cấu hình Tài khoản gửi Email (Chức năng Quên mật khẩu)
   - Di chuyển vào thư mục config/ của dự án.
   - Sao chép file mẫu mail.example.php và đổi tên thành mail.php.
   - Mở file mail.php lên và tiến hành cấu hình thông tin tài khoản SMTP của bạn:
        
      - define('MAIL_USERNAME', 'email_cua_ban@gmail.com'); // Nhập Gmail dùng làm server gửi thư
      - define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx');    // Nhập Mật khẩu ứng dụng (App Password) gồm 16 ký tự của Gmail
      - define('APP_URL',       'http://localhost/bookstore'); // Đường dẫn cơ sở của dự án dưới localhost

5. Khởi chạy và kiểm tra hệ thống
   - http://localhost/bookstore/index.php 

## Tài khoản Admin mặc định
- Username: `admin`
- Password: `123456`

## Tài khoản Trải nghiệm Khách hàng (Mặc định):
- Username: `user`
- Password: `123456`

Dự án được hoàn thiện cấu trúc và tối ưu hóa mã nguồn bởi NOVELTY Team. Chúc bạn có những trải nghiệm tuyệt vời khi học tập và phát triển hệ thống này!
