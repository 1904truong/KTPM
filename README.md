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

## Chạy bằng XAMPP (Windows) – 3 bước

Chỉ cần **XAMPP bản PHP 8.2** (https://www.apachefriends.org). Không cần cài Composer hay Node.js – thư viện và giao diện đã được đóng gói sẵn.

**Bước 1 – Bật MySQL.** Mở *XAMPP Control Panel* → bấm **Start** ở dòng **Apache** và **MySQL**.

**Bước 2 – Nạp database.** Vào http://localhost/phpmyadmin → tab **Import** → chọn file **`database_center_app.sql`** → bấm **Import**.
File đã có sẵn lệnh tạo database `center_app`, không cần tạo trước.

**Bước 3 – Chạy web.** Nhấp đúp file **`run.bat`**. Trình duyệt sẽ mở **http://127.0.0.1:8000** → đăng nhập bằng tài khoản ở trên.
Giữ cửa sổ đen đang mở trong lúc dùng; đóng cửa sổ đó để tắt chương trình.

> macOS / Linux: làm bước 1–2 tương tự rồi chạy `./run.sh`.

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
| `SQLSTATE[HY000] [1049] Unknown database 'center_app'` | Chưa import `database_center_app.sql` (Bước 2). |
| `Access denied for user 'root'` | Sửa `DB_USERNAME` / `DB_PASSWORD` trong `backend/.env`. |
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

Dữ liệu demo được sinh bởi `backend/database/seeders/DemoDataSeeder.php`.
