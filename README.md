# Sunny Education – Hệ thống quản lý trung tâm

Ứng dụng web quản lý trung tâm dạy thêm: học sinh, lớp học, điểm danh, học phí, kỳ thi thử, phân quyền nhân viên.

| Phần | Công nghệ | Thư mục |
|---|---|---|
| Backend (API) | Laravel 12, PHP 8.2, MySQL/MariaDB | `backend/` |
| Frontend (giao diện) | React + Vite + Tailwind | `frontend/` (mã nguồn) – bản build sẵn nằm trong `backend/public/` |

## Tài khoản đăng nhập (dữ liệu demo)

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Quản trị viên | `admin@gmail.com` | `123456` |
| Kế toán | `ketoan@demo.com` | `123456` |
| Giáo viên | `giaovien1@demo.com` … `giaovien4@demo.com` | `123456` |

Toàn bộ học sinh, phụ huynh, hóa đơn, điểm danh… là **dữ liệu giả** để minh họa.

---

## Chạy bằng XAMPP (Windows) – 4 bước

Cần **XAMPP bản PHP 8.2** (https://www.apachefriends.org) và **Composer** (https://getcomposer.org/download). Không cần Node.js vì giao diện đã được build sẵn trong `backend/public/`.

**Bước 1 – Cài thư viện PHP (chỉ làm lần đầu).** Thư viện (`backend/vendor/`) không có trên GitHub nên phải tải về:

1. Mở `C:\xampp\php\php.ini`, bỏ dấu `;` trước dòng `extension=zip` và `extension=gd`, rồi lưu lại.
2. Mở Command Prompt tại thư mục dự án và chạy (cần có mạng):

```bash
cd backend
composer install
```

**Bước 2 – Bật MySQL.** Mở *XAMPP Control Panel* → bấm **Start** ở dòng **Apache** và **MySQL**.

**Bước 3 – Nạp database.** Vào http://localhost/phpmyadmin → tab **Import** → chọn file **`database_center_app.sql`** → bấm **Import**.
File đã có sẵn lệnh tạo database `center_app`, không cần tạo trước. Lưu ý: import lại sẽ **ghi đè** dữ liệu đang có trong các bảng.

**Bước 4 – Chạy web.** Nhấp đúp file **`run.bat`**. Trình duyệt sẽ mở **http://127.0.0.1:8000** → đăng nhập bằng tài khoản ở trên.
Giữ cửa sổ đen đang mở trong lúc dùng; đóng cửa sổ đó để tắt chương trình.

> macOS / Linux: cài thư viện (bước 1), bật MySQL, import database rồi chạy `./run.sh`.

### Cấu hình MySQL khác mặc định

Mặc định ứng dụng kết nối MySQL `127.0.0.1:3306`, user `root`, **không mật khẩu** (giống XAMPP). Nếu khác, sửa trong `backend/.env`:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=center_app
DB_USERNAME=root
DB_PASSWORD=
```

## Lỗi thường gặp

| Lỗi | Cách xử lý |
|---|---|
| `SQLSTATE[HY000] [2002]` / *No connection could be made* | MySQL chưa bật – Start MySQL trong XAMPP. |
| `SQLSTATE[HY000] [1049] Unknown database 'center_app'` | Chưa import `database_center_app.sql` (Bước 3). |
| `Access denied for user 'root'` | Sửa `DB_USERNAME` / `DB_PASSWORD` trong `backend/.env`. |
| `Failed to open stream ... vendor/autoload.php` | Chưa cài thư viện – chạy `composer install` trong `backend/` (Bước 1). |
| `composer install` báo thiếu `ext-zip` / `ext-gd` | Bật hai extension trong `php.ini` (Bước 1) rồi chạy lại. |
| `Không tìm thấy PHP` | Cài XAMPP vào `C:\xampp`, hoặc thêm thư mục chứa `php.exe` vào PATH. |
| Cổng 8000 bị chiếm | Tắt chương trình đang dùng cổng 8000 rồi chạy lại `run.bat`. |
| Nhập/xuất Excel báo lỗi `ZipArchive` | Mở `C:\xampp\php\php.ini`, bỏ dấu `;` trước dòng `extension=zip` (và `extension=gd`), lưu lại rồi chạy lại `run.bat`. |
| Admin thêm học sinh báo "Vui lòng chọn một Cơ sở" | Chọn cơ sở ở menu bên trái trước khi thêm (đúng nghiệp vụ). |

---

## Dành cho lập trình viên

```bash
# Backend
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed     # tạo bảng + dữ liệu demo (thay cho việc import file .sql)
php artisan serve

# Frontend (chế độ phát triển, http://localhost:5173)
cd frontend
cp .env.example .env
npm install
npm run dev

# Build lại giao diện vào backend/public sau khi sửa frontend
cd frontend
VITE_API_URL=/api npm run build
cp -R dist/ ../backend/public/
```

Dữ liệu demo gốc được sinh bởi `backend/database/seeders/DemoDataSeeder.php`. Seeder này **khác với file `.sql`**: chỉ tạo 6 học sinh mỗi lớp và dùng mã lớp kiểu cũ (`3NC`) nên sĩ số lớp hiển thị bằng 0. Muốn có dữ liệu đầy đủ (15 học sinh mỗi lớp), hãy import `database_center_app.sql`.

Quy ước mã lớp để sĩ số đếm đúng: khối 3–9 đặt dạng `khối-loại` (ví dụ `7-NC`, học sinh có loại lớp `NC`); khối 10 trở lên đặt mã bằng số khối (ví dụ `10`, học sinh để trống loại lớp).
