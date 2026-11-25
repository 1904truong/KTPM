# Bộ kiểm thử tự động – Student Manager (Nhóm 1)

## Chạy nhanh trong dự án center-app-ban-phu
Mac: nhấp đúp file `.command` trong `mac/`. Windows: nhấp đúp file `.bat` trong `windows/`.

| Việc | `mac/` và `windows/` (cùng số) |
|---|---|
| Cài đặt lần đầu (chỉ Windows) | `00-Cai-dat-lan-dau.bat` |
| API đúng số liệu báo cáo (118 / 40) | `0-Test-API-dung-bao-cao` |
| API trên mã hiện tại (123 / 35) | `1-Test-API` |
| Demo 5 lỗi API | `2-Demo-5-loi` |
| Demo lỗi XSS | `3-Demo-loi-XSS` |
| 66 test giao diện | `4-Test-giao-dien` |
| Toàn bộ test Fail ghi trong Excel (40 + 9) | `5-Tat-ca-loi-trong-Excel` |
| Kiểm thử hộp xám | `6-Test-hop-xam` |

Máy Windows cần: XAMPP (PHP 8.2+), Composer, Node.js, Git, và cả thư mục dự án kèm `.git`.
Các file `.bat` chưa được chạy thử trên Windows.

Chạy 1 test API: `bash mac/test-api.sh QLHS_API_14` hoặc `windows\chay-test-api.bat QLHS_API_14`.

Dữ liệu mẫu cho test giao diện được dựng lại trong `support/UiTestSeeder.php`, lưu ở `e2e/ui.sqlite`, không đụng MySQL.

## 1. Kiểm thử API (PHPUnit) – 158 test
Chép `backend/tests/Feature/KTPM/` và `backend/phpunit.ktpm.xml` vào thư mục `backend` của repo center-app, rồi chạy:
```
cd backend
composer install
vendor/bin/phpunit -c phpunit.ktpm.xml --testdox
```
Test dùng SQLite trong bộ nhớ (`:memory:`) nên không ảnh hưởng dữ liệu thật. Cần PHP 8.2 trở lên có extension pdo_sqlite.
Các test Fail là lỗi thật của hệ thống (xem sheet "Danh sách lỗi" trong file Excel), không phải lỗi của script.

### Phiên bản mã tương ứng với số liệu báo cáo
Số liệu trong Excel/Word là kết quả **trước khi sửa lỗi**. Với kho `1904truong/KTPM`, phiên bản này là commit `91bd384`:
```
git worktree add ../center-app-baocao 91bd384
```
Chép bộ test vào `../center-app-baocao/backend` rồi chạy như trên (cần `composer install` và file `.env` có `APP_KEY`).

| Phiên bản | Pass | Fail |
|---|---|---|
| `91bd384` (trước khi sửa) – khớp từng test case với Excel | 118 | 40 |
| `93566ac` (đã sửa BUG_02, BUG_03, BUG_10) | 123 | 35 |

Test học phí được cố định ngày 10/11/2026 nên kết quả không phụ thuộc ngày chạy.

## 2. Kiểm thử giao diện (Playwright) – 66 test
1. Backend: `php artisan migrate:fresh --seed && php artisan serve` (cổng 8000)
2. Frontend: `npm run dev -- --host localhost` (cổng 5173, phải là localhost vì cấu hình CORS)
3. Chạy test:
```
cd e2e
npm init -y && npm i playwright && npx playwright install chromium
node ui_tests.js                 # chạy tất cả
ONLY=QLHS_UI_24,TTHP_UI_12 node ui_tests.js   # chạy một vài test
```
Kết quả ghi ra `results_ui.json`, ảnh chụp lưu trong `shots/`.
Script có các bước tạm dừng 65 giây do hệ thống giới hạn 60 request/phút; toàn bộ mất khoảng 10–15 phút.
