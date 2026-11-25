/**
 * Kiểm thử giao diện tự động (E2E) – Student Manager – Nhóm 1
 * Công cụ: Playwright (Chromium). Chạy: node ui_tests.js
 * Yêu cầu: backend chạy ở http://127.0.0.1:8000, frontend ở http://localhost:5173
 * Kết quả: results_ui.json + ảnh chụp minh chứng trong thư mục shots/
 */
const { chromium } = require('playwright');
const fs = require('fs');

const FE = process.env.FE_URL || 'http://localhost:5173';
const API = process.env.API_URL || 'http://127.0.0.1:8000/api';
const SHOTS = __dirname + '/shots';
fs.mkdirSync(SHOTS, { recursive: true });

const results = [];
const tests = [];
const test = (id, name, fn) => tests.push({ id, name, fn });
// Hệ thống giới hạn 60 request/phút/người dùng (RateLimiter 'api'), nên nghỉ 65s giữa các nhóm test
const pause = () => tests.push({ pause: true });
const expect = (cond, msg) => { if (!cond) throw new Error(msg); };

let browser, ctx, page, token, window_lastToast = '';

async function apiCall(method, path, body, branch = '1') {
  const res = await fetch(API + path, {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: 'Bearer ' + token, 'X-Branch-Id': branch },
    body: body ? JSON.stringify(body) : undefined,
  });
  return { status: res.status, json: await res.json().catch(() => null) };
}

async function toastText(timeout = 6000) {
  const t = page.locator('[data-sonner-toast]:not([data-seen])').first();
  await t.waitFor({ timeout });
  const txt = await t.innerText();
  await clearToasts();
  return txt;
}
// Đánh dấu các toast đã xem (chỉ gắn data-attribute, không xóa node để không làm hỏng React)
async function clearToasts() {
  await page.evaluate(() => document.querySelectorAll('[data-sonner-toast]').forEach((e) => (e.dataset.seen = '1')));
}
async function goto(path) {
  await page.goto(FE + path);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(600);
}
async function login(email = 'admin@gmail.com', pass = '123456') {
  await goto('/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', pass);
  await page.click('button:has-text("Đăng Nhập")');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 8000 });
  await page.evaluate(() => localStorage.setItem('active_branch_id', '1'));
}
const shot = (name) => page.screenshot({ path: `${SHOTS}/${name}.png` });

// ============================ ĐĂNG NHẬP ============================
test('QLND_UI_01', 'Giao diện đăng nhập hiển thị đủ Email, Mật khẩu, nút Đăng Nhập', async () => {
  await goto('/login');
  expect(await page.locator('input[type=email]').isVisible(), 'Thiếu ô Email');
  expect(await page.locator('input[type=password]').isVisible(), 'Thiếu ô Mật khẩu');
  expect(await page.locator('button:has-text("Đăng Nhập")').isVisible(), 'Thiếu nút Đăng Nhập');
  await shot('ui_login');
});
test('QLND_UI_02', 'Ô mật khẩu được che ký tự (type=password)', async () => {
  expect((await page.locator('input[placeholder="••••••"]').getAttribute('type')) === 'password', 'Mật khẩu không bị che');
});
test('QLND_UI_03', 'Bỏ trống Email/Mật khẩu -> trình duyệt chặn submit, ở lại trang đăng nhập', async () => {
  await goto('/login');
  await page.click('button:has-text("Đăng Nhập")');
  const msg = await page.locator('input[type=email]').evaluate((e) => e.validationMessage);
  expect(msg.length > 0 && page.url().includes('/login'), 'Không chặn được form rỗng');
});
test('QLND_UI_04', 'Email sai định dạng (abc) -> trình duyệt báo lỗi định dạng email', async () => {
  await page.fill('input[type=email]', 'abc');
  await page.fill('input[type=password]', '123456');
  await page.click('button:has-text("Đăng Nhập")');
  const valid = await page.locator('input[type=email]').evaluate((e) => e.validity.valid);
  expect(!valid && page.url().includes('/login'), 'Không báo lỗi định dạng email');
});
test('QLND_UI_05', 'Sai mật khẩu -> hiển thị thông báo "Email hoặc mật khẩu không chính xác!"', async () => {
  await goto('/login');
  await page.fill('input[type=email]', 'admin@gmail.com');
  await page.fill('input[type=password]', 'saimatkhau');
  await page.click('button:has-text("Đăng Nhập")');
  const t = await toastText();
  await shot('ui_login_sai');
  expect(t.includes('không chính xác'), 'Thông báo nhận được: ' + t);
});
test('QLND_UI_06', 'Nhấn Enter trong ô mật khẩu thực hiện đăng nhập thành công', async () => {
  await goto('/login');
  await page.fill('input[type=email]', 'admin@gmail.com');
  await page.fill('input[type=password]', '123456');
  await page.press('input[type=password]', 'Enter');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 8000 });
  token = await page.evaluate(() => localStorage.getItem('token'));
  expect(!!token, 'Không lưu token');
  await page.evaluate(() => localStorage.setItem('active_branch_id', '1'));
});
test('QLND_UI_07', 'Chưa đăng nhập truy cập /students -> chuyển về trang đăng nhập', async () => {
  const c2 = await browser.newContext();
  const p2 = await c2.newPage();
  await p2.goto(FE + '/students');
  await p2.waitForTimeout(1500);
  const url = p2.url();
  await c2.close();
  expect(url.includes('/login'), 'Không chuyển hướng, URL = ' + url);
});

pause();
// ============================ QUẢN LÝ HỌC SINH ============================
test('QLHS_UI_01', 'Màn hình Học sinh hiển thị bộ lọc, ô tìm kiếm, các nút chức năng và bảng danh sách', async () => {
  await goto('/students');
  for (const b of ['Xuất Excel', 'Import Excel', 'Thêm Học Sinh']) expect(await page.locator(`button:has-text("${b}")`).first().isVisible(), 'Thiếu nút ' + b);
  expect(await page.locator('input[placeholder="Tìm tên hoặc mã HS..."]').isVisible(), 'Thiếu ô tìm kiếm');
  await shot('ui_students');
});
test('QLHS_UI_02', 'Bảng danh sách có đủ cột: Mã HS, Họ và Tên, Khối/Lớp, Tên Bố/Mẹ & SĐT', async () => {
  const heads = (await page.locator('table thead th').allInnerTexts()).map((s) => s.trim().toUpperCase());
  for (const h of ['MÃ HS', 'HỌ VÀ TÊN', 'KHỐI / LỚP', 'TÊN BỐ/MẸ & SĐT']) expect(heads.includes(h), 'Thiếu cột ' + h + ' / có: ' + heads.join(','));
});
test('QLHS_UI_03', 'Tìm kiếm theo tên "Minh" chỉ hiển thị học sinh có tên chứa "Minh"', async () => {
  await page.fill('input[placeholder="Tìm tên hoặc mã HS..."]', 'Minh');
  await page.waitForTimeout(1500);
  const rows = await page.locator('table tbody tr').allInnerTexts();
  await shot('ui_students_search');
  expect(rows.length > 0 && rows.every((r) => r.includes('Minh')), 'Kết quả: ' + rows.length + ' dòng');
});
test('QLHS_UI_04', 'Tìm kiếm theo mã HS20260004 trả về đúng 1 học sinh', async () => {
  await page.fill('input[placeholder="Tìm tên hoặc mã HS..."]', 'HS20260004');
  await page.waitForTimeout(1500);
  const rows = await page.locator('table tbody tr').allInnerTexts();
  expect(rows.length === 1 && rows[0].includes('HS20260004'), 'Số dòng: ' + rows.length);
});
test('QLHS_UI_05', 'Tìm kiếm không dấu "tran minh" tìm được học sinh "Trần Minh An"', async () => {
  await page.fill('input[placeholder="Tìm tên hoặc mã HS..."]', 'tran minh');
  await page.waitForTimeout(1500);
  const txt = await page.locator('table tbody').innerText();
  expect(txt.includes('Trần Minh An'), 'Không tìm thấy khi gõ không dấu');
});
test('QLHS_UI_06', 'Tìm kiếm không có kết quả -> bảng không hiển thị học sinh nào', async () => {
  await page.fill('input[placeholder="Tìm tên hoặc mã HS..."]', 'zzzkhongtontai');
  await page.waitForTimeout(1500);
  const txt = await page.locator('table tbody').innerText();
  await shot('ui_students_empty');
  expect(!txt.includes('HS2026'), 'Vẫn hiển thị học sinh');
  await page.fill('input[placeholder="Tìm tên hoặc mã HS..."]', '');
  await page.waitForTimeout(1200);
});
test('QLHS_UI_07', 'Mở form "Thêm Học Sinh Mới": đủ trường, học phí mặc định 130000, ngày bắt đầu = hôm nay', async () => {
  await page.click('button:has-text("Thêm Học Sinh")');
  const form = page.locator('form:has(button:has-text("Lưu Học Sinh"))');
  await form.waitFor();
  const num = form.locator('input[type=number]');
  expect((await num.nth(0).inputValue()) === '130000', 'Học phí mặc định = ' + (await num.nth(0).inputValue()));
  const d = await form.locator('input[type=date]').inputValue();
  const today = await page.evaluate(() => { const n = new Date(); return `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}-${String(n.getDate()).padStart(2, '0')}`; });
  await shot('ui_students_add_form');
  expect(d === today, 'Ngày bắt đầu = ' + d);
});
test('QLHS_UI_08', 'Bấm "Lưu Học Sinh" khi bỏ trống Họ và tên -> form bị chặn, hiện cảnh báo bắt buộc', async () => {
  const form = page.locator('form:has(button:has-text("Lưu Học Sinh"))');
  await form.locator('button:has-text("Lưu Học Sinh")').click();
  const msg = await form.locator('input[type=text]').first().evaluate((e) => e.validationMessage);
  expect(msg.length > 0, 'Không có cảnh báo bắt buộc');
});
test('QLHS_UI_09', 'Không chọn Lớp học -> form bị chặn (trường bắt buộc)', async () => {
  const form = page.locator('form:has(button:has-text("Lưu Học Sinh"))');
  await form.locator('input[type=text]').first().fill('Học Sinh Thiếu Lớp');
  await form.locator('button:has-text("Lưu Học Sinh")').click();
  const msg = await form.locator('select').first().evaluate((e) => e.validationMessage);
  expect(msg.length > 0, 'Không bắt buộc chọn lớp');
});
test('QLHS_UI_10', 'Ô SĐT Bố/Mẹ tự loại bỏ ký tự không phải số (nhập "09ab12-34" -> "091234")', async () => {
  const form = page.locator('form:has(button:has-text("Lưu Học Sinh"))');
  const phone = form.locator('input[type=text]').nth(2);
  await phone.fill('');
  await phone.type('09ab12-34');
  expect((await phone.inputValue()) === '091234', 'Giá trị: ' + (await phone.inputValue()));
});
test('QLHS_UI_11', 'Ô SĐT Bố/Mẹ giới hạn tối đa 10-11 chữ số (nhập 15 số)', async () => {
  const form = page.locator('form:has(button:has-text("Lưu Học Sinh"))');
  const phone = form.locator('input[type=text]').nth(2);
  await phone.fill('');
  await phone.type('091234567891234');
  const v = await phone.inputValue();
  expect(v.length <= 11, 'Cho nhập ' + v.length + ' chữ số');
});
test('QLHS_UI_12', 'Ô Học phí/Buổi không cho nhập số âm', async () => {
  const form = page.locator('form:has(button:has-text("Lưu Học Sinh"))');
  const fee = form.locator('input[type=number]').nth(0);
  const min = await fee.getAttribute('min');
  expect(min !== null && Number(min) >= 0, 'Không có thuộc tính min, cho phép nhập số âm');
});
test('QLHS_UI_13', 'Thêm học sinh hợp lệ -> thông báo "Thêm học sinh mới thành công", học sinh xuất hiện trong danh sách', async () => {
  const form = page.locator('form:has(button:has-text("Lưu Học Sinh"))');
  await form.locator('input[type=text]').first().fill('Kiều Thị Test');
  const opt = await form.locator('select option').nth(1).getAttribute('value');
  await form.locator('select').first().selectOption(opt);
  await form.locator('input[type=text]').nth(1).fill('Kiều Văn Bố');
  await form.locator('input[type=text]').nth(2).fill('0977000111');
  await clearToasts();
  await form.locator('button:has-text("Lưu Học Sinh")').click();
  const t = await toastText();
  await page.waitForTimeout(1500);
  await shot('ui_students_add_ok');
  const body = await page.locator('table tbody').innerText();
  expect(t.includes('thành công') && body.includes('Kiều Thị Test'), 'Toast: ' + t);
});
test('QLHS_UI_14', 'Thêm trùng Họ tên + SĐT phụ huynh -> báo lỗi "Học sinh này đã tồn tại"', async () => {
  await page.click('button:has-text("Thêm Học Sinh")');
  const form = page.locator('form:has(button:has-text("Lưu Học Sinh"))');
  await form.locator('input[type=text]').first().fill('Kiều Thị Test');
  const opt = await form.locator('select option').nth(1).getAttribute('value');
  await form.locator('select').first().selectOption(opt);
  await form.locator('input[type=text]').nth(2).fill('0977000111');
  await clearToasts();
  await form.locator('button:has-text("Lưu Học Sinh")').click();
  const t = await toastText();
  await shot('ui_students_add_dup');
  expect(t.includes('đã tồn tại'), 'Toast: ' + t);
});
test('QLHS_UI_15', 'Nút X đóng form thêm mới, không lưu dữ liệu', async () => {
  const modal = page.locator('div:has(> h2:has-text("Thêm Học Sinh Mới"))');
  await modal.locator('button').first().click();
  await page.waitForTimeout(400);
  expect(!(await page.locator('h2:has-text("Thêm Học Sinh Mới")').isVisible()), 'Form chưa đóng');
});
pause();
test('QLHS_UI_16', 'Mở chi tiết học sinh (nút xem) hiển thị thông tin và các nút Chỉnh sửa/In/Xóa', async () => {
  await page.fill('input[placeholder="Tìm tên hoặc mã HS..."]', 'Kiều Thị Test');
  await page.waitForTimeout(1500);
  await page.locator('table tbody tr').first().locator('button').last().click();
  await page.waitForTimeout(600);
  await shot('ui_students_detail');
  for (const b of ['Chỉnh sửa thông tin', 'Xóa học sinh']) expect(await page.locator(`button:has-text("${b}")`).isVisible(), 'Thiếu nút ' + b);
});
test('QLHS_UI_17', 'Chỉnh sửa họ tên học sinh -> thông báo "Cập nhật thông tin học sinh thành công!"', async () => {
  await page.click('button:has-text("Chỉnh sửa thông tin")');
  const form = page.locator('form:has(button:has-text("Lưu Thay Đổi"))');
  await form.locator('input[type=text]').first().fill('Kiều Thị Test Sửa');
  await clearToasts();
  await form.locator('button:has-text("Lưu Thay Đổi")').click();
  const t = await toastText();
  await shot('ui_students_edit_ok');
  expect(t.includes('thành công'), 'Toast: ' + t);
});
test('QLHS_UI_18', 'Form sửa: bỏ trống Họ và tên -> bị chặn (trường bắt buộc)', async () => {
  await page.click('button:has-text("Chỉnh sửa thông tin")');
  const form = page.locator('form:has(button:has-text("Lưu Thay Đổi"))');
  await form.locator('input[type=text]').first().fill('');
  await form.locator('button:has-text("Lưu Thay Đổi")').click();
  const msg = await form.locator('input[type=text]').first().evaluate((e) => e.validationMessage);
  expect(msg.length > 0, 'Không chặn họ tên rỗng');
  await page.click('button:has-text("Hủy chỉnh sửa")');
});
test('QLHS_UI_19', 'Tính nhất quán: SĐT Bố/Mẹ ở form Thêm là không bắt buộc nhưng ở form Sửa lại bắt buộc (*)', async () => {
  await page.click('button:has-text("Chỉnh sửa thông tin")');
  const form = page.locator('form:has(button:has-text("Lưu Thay Đổi"))');
  const txt = await form.innerText();
  const phoneRequired = /SĐT Bố\/Mẹ \*/.test(txt);
  await page.click('button:has-text("Hủy chỉnh sửa")');
  expect(!phoneRequired, 'Form Sửa bắt buộc SĐT trong khi form Thêm không bắt buộc');
});
test('QLHS_UI_20', 'Xóa học sinh: hộp thoại yêu cầu chọn lý do; không chọn -> báo "Bạn cần chọn một lý do xóa!"', async () => {
  await page.click('button:has-text("Xóa học sinh")');
  await page.locator('.swal2-popup').waitFor();
  await page.click('.swal2-confirm');
  await page.waitForTimeout(300);
  const m = await page.locator('.swal2-validation-message').innerText();
  await shot('ui_students_delete_dialog');
  expect(m.includes('Bạn cần chọn một lý do xóa'), 'Thông báo: ' + m);
});
test('QLHS_UI_21', 'Xóa học sinh: bấm Hủy -> đóng hộp thoại, học sinh vẫn còn', async () => {
  await page.click('.swal2-cancel');
  await page.waitForTimeout(500);
  const r = await apiCall('GET', '/students?search=' + encodeURIComponent('Kiều Thị Test Sửa'));
  expect(r.json.data.length === 1, 'Học sinh bị mất');
});
test('QLHS_UI_22', 'Xóa với lý do "Nghỉ học" -> thông báo thành công, học sinh không còn trong danh sách', async () => {
  await page.click('button:has-text("Xóa học sinh")');
  await page.locator('.swal2-popup').waitFor();
  await page.check('.swal2-radio input[value="dropped"]');
  await clearToasts();
  await page.click('.swal2-confirm');
  const t = await toastText();
  await page.waitForTimeout(1200);
  const r = await apiCall('GET', '/students?search=' + encodeURIComponent('Kiều Thị Test Sửa'));
  await shot('ui_students_delete_ok');
  expect(t.includes('nghỉ học') && r.json.data.length === 0, 'Toast: ' + t);
});
test('QLHS_UI_23', 'Bảo mật XSS: học sinh tên chứa mã HTML/JS hiển thị an toàn ở bảng danh sách', async () => {
  await apiCall('POST', '/students', { full_name: '<img src=x onerror="window.__xss=1">', grade: 6, class_type: 'CC1', parent_phone: '0900000999' });
  await page.evaluate(() => { window.__xss = 0; });
  await page.fill('input[placeholder="Tìm tên hoặc mã HS..."]', 'onerror');
  await page.waitForTimeout(1500);
  const x = await page.evaluate(() => window.__xss);
  expect(x === 0, 'Mã JS bị thực thi trong bảng');
});
test('QLHS_UI_24', 'Bảo mật XSS: mở hộp thoại Xóa của học sinh tên chứa mã độc -> mã KHÔNG được thực thi', async () => {
  await page.locator('table tbody tr').first().locator('button').last().click();
  await page.waitForTimeout(500);
  await page.click('button:has-text("Xóa học sinh")');
  await page.locator('.swal2-popup').waitFor();
  await page.waitForTimeout(500);
  const x = await page.evaluate(() => window.__xss);
  await shot('ui_students_xss');
  await page.click('.swal2-cancel');
  await page.keyboard.press('Escape');
  expect(x === 0, 'Mã JavaScript trong tên học sinh đã bị thực thi (window.__xss = 1) trong hộp thoại SweetAlert');
});
test('QLHS_UI_25', 'Mở "Import Excel": chỉ cho chọn .xlsx/.xls/.csv và bắt buộc chọn file', async () => {
  await goto('/students');
  await page.click('button:has-text("Import Excel")');
  const inp = page.locator('input[type=file]');
  const acc = await inp.getAttribute('accept');
  const req = await inp.getAttribute('required');
  await shot('ui_students_import');
  expect(acc.includes('.xlsx') && acc.includes('.csv') && req !== null, 'accept=' + acc);
  await page.keyboard.press('Escape');
  await goto('/students');
});
test('QLHS_UI_26', 'Chọn nhiều học sinh bằng checkbox -> hiện thanh thao tác hàng loạt (Xóa)', async () => {
  const boxes = page.locator('table tbody tr input[type=checkbox]');
  await boxes.nth(0).check();
  await boxes.nth(1).check();
  await page.waitForTimeout(400);
  await shot('ui_students_bulk');
  const vis = await page.locator('button:has-text("Xóa")').first().isVisible();
  await boxes.nth(0).uncheck();
  await boxes.nth(1).uncheck();
  expect(vis, 'Không hiện nút xóa hàng loạt');
});
test('QLHS_UI_27', 'Giao diện mobile 375px: danh sách hiển thị dạng thẻ, không tràn ngang', async () => {
  await page.setViewportSize({ width: 375, height: 812 });
  await goto('/students');
  const over = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  await shot('ui_students_mobile');
  await page.setViewportSize({ width: 1440, height: 900 });
  expect(over <= 1, 'Tràn ngang ' + over + 'px');
});

pause();
// ============================ QUẢN LÝ NGƯỜI DÙNG ============================
test('QLND_UI_08', 'Màn hình Quản Lý Người Dùng có tiêu đề, ô tìm kiếm, nút "Thêm Tài Khoản"', async () => {
  await goto('/users');
  expect(await page.locator('h1:has-text("Quản Lý Người Dùng")').isVisible(), 'Thiếu tiêu đề');
  expect(await page.locator('input[placeholder="Tìm kiếm theo tên hoặc email..."]').isVisible(), 'Thiếu ô tìm kiếm');
  await shot('ui_users');
});
test('QLND_UI_09', 'Bảng người dùng có cột STT, Người Dùng, Vai trò, Cơ sở, Ngày Tạo, Thao Tác', async () => {
  const heads = (await page.locator('table thead th').allInnerTexts()).map((s) => s.trim().toUpperCase());
  for (const h of ['STT', 'NGƯỜI DÙNG', 'VAI TRÒ', 'CƠ SỞ', 'NGÀY TẠO', 'THAO TÁC']) expect(heads.includes(h), 'Thiếu cột ' + h);
});
test('QLND_UI_10', 'Mở form "Thêm Tài Khoản": có Họ và Tên*, Email*, Vai trò*, Cơ sở (Tùy chọn), Mật khẩu*', async () => {
  await page.click('button:has-text("Thêm Tài Khoản")');
  const form = page.locator('form:has(button:has-text("Tạo Tài Khoản"))');
  await form.waitFor();
  const t = await form.innerText();
  await shot('ui_users_add_form');
  for (const l of ['Họ và Tên', 'Email đăng nhập', 'Vai trò', 'Cơ sở', 'Mật khẩu']) expect(t.includes(l), 'Thiếu ' + l);
});
test('QLND_UI_11', 'Mật khẩu 5 ký tự -> trình duyệt chặn (minLength = 6)', async () => {
  const form = page.locator('form:has(button:has-text("Tạo Tài Khoản"))');
  await form.locator('input[name=name]').fill('Hoàng Văn Trường');
  await form.locator('input[name=email]').fill('truong.ktpm@gmail.com');
  await form.locator('input[name=password]').fill('12345');
  await form.locator('button:has-text("Tạo Tài Khoản")').click();
  const valid = await form.locator('input[name=password]').evaluate((e) => e.validity.valid);
  expect(!valid, 'Không chặn mật khẩu 5 ký tự');
});
test('QLND_UI_12', 'Email sai định dạng (truong@) -> trình duyệt chặn', async () => {
  const form = page.locator('form:has(button:has-text("Tạo Tài Khoản"))');
  await form.locator('input[name=email]').fill('truong@');
  await form.locator('input[name=password]').fill('123456');
  await form.locator('button:has-text("Tạo Tài Khoản")').click();
  const valid = await form.locator('input[name=email]').evaluate((e) => e.validity.valid);
  expect(!valid, 'Không chặn email sai');
});
test('QLND_UI_13', 'Tạo nhân viên vai trò member nhưng chọn "Tất cả cơ sở" -> báo lỗi yêu cầu chọn cơ sở', async () => {
  const form = page.locator('form:has(button:has-text("Tạo Tài Khoản"))');
  await form.locator('input[name=email]').fill('truong.ktpm@gmail.com');
  await form.locator('select[name=role]').selectOption('member');
  await form.locator('select[name=branch_id]').selectOption('');
  await clearToasts();
  await form.locator('button:has-text("Tạo Tài Khoản")').click();
  const t = await toastText();
  expect(t.includes('Cơ sở'), 'Toast: ' + t);
});
test('QLND_UI_14', 'Tạo tài khoản hợp lệ (member, Cơ sở Chính) -> "Thêm tài khoản mới thành công!", hiển thị trong bảng', async () => {
  const form = page.locator('form:has(button:has-text("Tạo Tài Khoản"))');
  await form.locator('select[name=branch_id]').selectOption('1');
  await clearToasts();
  await form.locator('button:has-text("Tạo Tài Khoản")').click();
  const t = await toastText();
  await page.waitForTimeout(1200);
  await shot('ui_users_add_ok');
  const body = await page.locator('table tbody').innerText();
  expect(t.includes('thành công') && body.includes('truong.ktpm@gmail.com'), 'Toast: ' + t);
});
test('QLND_UI_15', 'Tạo tài khoản trùng email -> báo lỗi email đã tồn tại', async () => {
  await page.click('button:has-text("Thêm Tài Khoản")');
  const form = page.locator('form:has(button:has-text("Tạo Tài Khoản"))');
  await form.locator('input[name=name]').fill('Trùng Email');
  await form.locator('input[name=email]').fill('truong.ktpm@gmail.com');
  await form.locator('input[name=password]').fill('123456');
  await form.locator('select[name=branch_id]').selectOption('1');
  await clearToasts();
  await form.locator('button:has-text("Tạo Tài Khoản")').click();
  const t = await toastText();
  window_lastToast = t;
  await shot('ui_users_dup');
  expect(/taken|tồn tại|đã được sử dụng/i.test(t), 'Toast: ' + t);
});
test('QLND_UI_16', 'Thông báo lỗi trùng email hiển thị bằng tiếng Việt (thống nhất ngôn ngữ giao diện)', async () => {
  const t = window_lastToast;
  await page.keyboard.press('Escape');
  await page.locator('form button:has-text("Hủy bỏ")').click().catch(() => {});
  expect(!/has already been taken/i.test(t), 'Thông báo tiếng Anh: ' + t);
});
pause();
test('QLND_UI_17', 'Tạo tài khoản Admin quản lý chung (Tất cả cơ sở) -> thành công', async () => {
  await page.evaluate(() => localStorage.removeItem('active_branch_id'));
  await goto('/users');
  await page.click('button:has-text("Thêm Tài Khoản")');
  const form = page.locator('form:has(button:has-text("Tạo Tài Khoản"))');
  await form.locator('input[name=name]').fill('Trần Khánh Duy');
  await form.locator('input[name=email]').fill('duy.admin@gmail.com');
  await form.locator('input[name=password]').fill('123456');
  await form.locator('select[name=role]').selectOption('admin');
  await form.locator('select[name=branch_id]').selectOption('');
  await clearToasts();
  await form.locator('button:has-text("Tạo Tài Khoản")').click();
  const t = await toastText();
  await shot('ui_users_admin_all');
  await page.evaluate(() => localStorage.setItem('active_branch_id', '1'));
  await page.locator('form button:has-text("Hủy bỏ")').click().catch(() => {});
  expect(t.includes('thành công'), 'Toast: ' + t);
});
test('QLND_UI_18', 'Tìm kiếm người dùng theo email "truong" -> chỉ hiển thị tài khoản phù hợp', async () => {
  await goto('/users');
  await page.fill('input[placeholder="Tìm kiếm theo tên hoặc email..."]', 'truong');
  await page.waitForTimeout(400);
  const rows = await page.locator('table tbody tr').allInnerTexts();
  expect(rows.length === 1 && rows[0].includes('truong.ktpm'), 'Số dòng ' + rows.length);
});
test('QLND_UI_19', 'Sửa tài khoản: ô mật khẩu là tùy chọn với gợi ý "Để trống nếu không muốn đổi"; lưu tên mới thành công', async () => {
  await page.locator('table tbody tr').first().locator('button').first().click();
  const form = page.locator('form:has(button:has-text("Lưu Thay Đổi"))');
  await form.waitFor();
  const ph = await form.locator('input[name=password]').getAttribute('placeholder');
  await form.locator('input[name=name]').fill('Hoàng Văn Trường (Sửa)');
  await shot('ui_users_edit');
  await clearToasts();
  await form.locator('button:has-text("Lưu Thay Đổi")').click();
  const t = await toastText();
  expect(ph.includes('Để trống') && t.includes('thành công'), 'placeholder=' + ph + ' toast=' + t);
});
test('QLND_UI_20', 'Nút Xóa ở dòng tài khoản đang đăng nhập bị vô hiệu hóa (không thể tự xóa)', async () => {
  // Super Admin không thuộc cơ sở nào nên chỉ hiện khi chọn "Tất cả cơ sở"
  await page.evaluate(() => localStorage.removeItem('active_branch_id'));
  await goto('/users');
  await page.locator('table tbody tr:has-text("admin@gmail.com")').first().waitFor({ timeout: 10000 });
  await page.fill('input[placeholder="Tìm kiếm theo tên hoặc email..."]', 'admin@gmail.com');
  await page.waitForTimeout(400);
  const btn = page.locator('table tbody tr').first().locator('button').last();
  const disabled = await btn.isDisabled();
  await shot('ui_users_self_delete');
  await page.evaluate(() => localStorage.setItem('active_branch_id', '1'));
  await goto('/users');
  expect(disabled, 'Nút xóa tài khoản của chính mình vẫn bấm được');
});
test('QLND_UI_21', 'Xóa tài khoản khác -> hộp thoại xác nhận, đồng ý -> "Đã xóa người dùng thành công"', async () => {
  await page.locator('table tbody tr:has-text("truong.ktpm")').first().waitFor({ timeout: 10000 });
  await page.fill('input[placeholder="Tìm kiếm theo tên hoặc email..."]', 'truong.ktpm');
  await page.waitForTimeout(400);
  await page.locator('table tbody tr').first().locator('button').last().click();
  await page.locator('.swal2-popup').waitFor();
  await clearToasts();
  await page.click('.swal2-confirm');
  const t = await toastText();
  await shot('ui_users_delete_ok');
  expect(t.includes('thành công'), 'Toast: ' + t);
});

pause();
// ============================ THANH TOÁN HỌC PHÍ (POS) ============================
test('TTHP_UI_01', 'Màn hình Thu Tiền Học Phí POS: ô tìm học sinh, 2 hình thức thanh toán, nút tạo hóa đơn bị khóa khi chưa chọn HS', async () => {
  await goto('/pos');
  expect(await page.locator('input[placeholder="Nhập Mã HS hoặc Tên học sinh..."]').isVisible(), 'Thiếu ô tìm');
  expect(await page.locator('button:has-text("Chuyển khoản VietQR")').isVisible(), 'Thiếu nút CK');
  expect(await page.locator('button:has-text("Tiền mặt (Cash)")').isVisible(), 'Thiếu nút TM');
  expect(await page.locator('button:has-text("Tạo Hóa Đơn")').isDisabled(), 'Nút tạo hóa đơn không bị khóa');
  await shot('ui_pos');
});
test('TTHP_UI_02', 'Bảng lịch sử thu tiền có cột Mã HD, Học sinh, Nội dung, Số tiền, Hình thức, Trạng thái, Thao tác', async () => {
  const heads = (await page.locator('table thead th').allInnerTexts()).map((s) => s.trim().toUpperCase());
  for (const h of ['MÃ HD', 'HỌC SINH', 'NỘI DUNG', 'SỐ TIỀN', 'HÌNH THỨC', 'TRẠNG THÁI', 'THAO TÁC']) expect(heads.includes(h), 'Thiếu cột ' + h);
});
test('TTHP_UI_03', 'Dòng "Chưa có lịch sử thu tiền" trải hết chiều rộng bảng (colspan = số cột)', async () => {
  const cell = page.locator('td:has-text("Chưa có lịch sử thu tiền")');
  if (!(await cell.count())) throw new Error('Đã có dữ liệu – không kiểm tra được');
  const cs = Number(await cell.getAttribute('colspan'));
  const n = await page.locator('table thead th').count();
  expect(cs === n, `colspan=${cs} trong khi bảng có ${n} cột`);
});
test('TTHP_UI_04', 'Tìm học sinh "HS20260002" -> chọn -> tự tính học phí 3 buổi x 130.000 = 390.000đ', async () => {
  await page.fill('input[placeholder="Nhập Mã HS hoặc Tên học sinh..."]', 'HS20260002');
  await page.waitForTimeout(500);
  await page.locator('text=Trần Minh An').first().click();
  await page.waitForTimeout(1500);
  const amt = await page.locator('input[type=number]').first().inputValue();
  await shot('ui_pos_cart');
  expect(Number(amt) === 390000, 'Số tiền = ' + amt);
});
test('TTHP_UI_05', 'Chọn lại cùng học sinh đang có trong danh sách thu -> cảnh báo "đã có trong giỏ hàng"', async () => {
  await page.fill('input[placeholder="Nhập Mã HS hoặc Tên học sinh..."]', 'HS20260002');
  await page.waitForTimeout(500);
  await clearToasts();
  await page.locator('text=Trần Minh An').first().click();
  const t = await toastText();
  expect(t.includes('đã có trong giỏ'), 'Toast: ' + t);
});
test('TTHP_UI_06', 'Sửa số tiền thành 0 rồi tạo hóa đơn -> cảnh báo "Số tiền thu phải lớn hơn 0 đ!"', async () => {
  await page.locator('input[type=number]').first().fill('0');
  await clearToasts();
  await page.click('button:has-text("Tạo Hóa Đơn")');
  const t = await toastText();
  expect(t.includes('lớn hơn 0'), 'Toast: ' + t);
});
test('TTHP_UI_07', 'Số tiền 500đ (dưới mức tối thiểu 1.000đ của hệ thống) -> báo lỗi rõ ràng', async () => {
  await page.locator('input[type=number]').first().fill('500');
  await clearToasts();
  await page.click('button:has-text("Tạo Hóa Đơn")');
  const t = await toastText();
  await shot('ui_pos_500');
  expect(t.includes('Lỗi') || t.includes('1000') || t.includes('1.000'), 'Toast: ' + t);
});
test('TTHP_UI_08', 'Ô số tiền không cho nhập số âm', async () => {
  const min = await page.locator('input[type=number]').first().getAttribute('min');
  expect(min !== null && Number(min) >= 0, 'Không có ràng buộc min');
});
test('TTHP_UI_09', 'Thu tiền mặt 390.000đ -> "Đã tạo hóa đơn thành công!", lịch sử có dòng "Tiền mặt" – "Đã thu"', async () => {
  await page.locator('input[type=number]').first().fill('390000');
  await page.click('button:has-text("Tiền mặt (Cash)")');
  await clearToasts();
  await page.click('button:has-text("Tạo Hóa Đơn")');
  const t = await toastText();
  await page.waitForTimeout(1500);
  await shot('ui_pos_cash_ok');
  const row = await page.locator('table tbody tr').first().innerText();
  expect(t.includes('thành công') && row.includes('Trần Minh An') && /Tiền mặt/i.test(row) && row.includes('Đã thu'), 'Dòng: ' + row);
});
test('TTHP_UI_10', 'Chọn học sinh đã có hóa đơn trong tháng -> cảnh báo "đã có hóa đơn tháng này"', async () => {
  await page.fill('input[placeholder="Nhập Mã HS hoặc Tên học sinh..."]', 'HS20260002');
  await page.waitForTimeout(500);
  await clearToasts();
  await page.locator('text=Trần Minh An').first().click();
  const t = await toastText();
  expect(t.includes('đã có hóa đơn tháng này'), 'Toast: ' + t);
});
pause();
test('TTHP_UI_11', 'Thu chuyển khoản -> hiển thị mã VietQR đúng số tiền, trạng thái "Chờ duyệt"', async () => {
  await page.fill('input[placeholder="Nhập Mã HS hoặc Tên học sinh..."]', 'HS20260003');
  await page.waitForTimeout(500);
  await page.locator('text=Lê Thu Hà').first().click();
  await page.waitForTimeout(1500);
  await page.click('button:has-text("Chuyển khoản VietQR")');
  await clearToasts();
  await page.click('button:has-text("Tạo Hóa Đơn")');
  await toastText();
  await page.waitForTimeout(1500);
  const src = await page.locator('img[src*="vietqr"]').first().getAttribute('src').catch(() => '');
  await shot('ui_pos_transfer');
  const row = await page.locator('table tbody tr').first().innerText();
  expect(src.includes('amount=390000') && row.includes('Chờ duyệt'), 'src=' + src + ' row=' + row);
});
test('TTHP_UI_12', 'Admin bấm Duyệt hóa đơn chờ duyệt -> "Đã duyệt hóa đơn!", trạng thái chuyển "Đã thu"', async () => {
  await clearToasts();
  await page.locator('table tbody tr').first().locator('button[title="Duyệt"]').click();
  const t = await toastText();
  await page.waitForTimeout(1500);
  const row = await page.locator('table tbody tr').first().innerText();
  await shot('ui_pos_approve');
  expect(t.includes('Đã duyệt') && row.includes('Đã thu'), 'Dòng: ' + row);
});
test('TTHP_UI_13', 'Từ chối hóa đơn chuyển khoản (xác nhận OK) -> "Đã từ chối hóa đơn!", trạng thái "Từ chối"', async () => {
  await page.fill('input[placeholder="Nhập Mã HS hoặc Tên học sinh..."]', 'HS20260004');
  await page.waitForTimeout(500);
  await page.locator('text=Phạm Gia Bảo').first().click();
  await page.waitForTimeout(1500);
  await page.click('button:has-text("Chuyển khoản VietQR")');
  await page.click('button:has-text("Tạo Hóa Đơn")');
  await page.waitForTimeout(2000);
  page.once('dialog', (d) => d.accept());
  await clearToasts();
  await page.locator('table tbody tr').first().locator('button[title="Từ chối"]').click();
  const t = await toastText();
  await page.waitForTimeout(1500);
  const row = await page.locator('table tbody tr').first().innerText();
  await shot('ui_pos_reject');
  expect(t.includes('từ chối') && row.includes('Từ chối'), 'Dòng: ' + row);
});
test('TTHP_UI_14', 'Học phí có nợ cũ: HS Phạm Gia Bảo (nợ 260.000đ) hiển thị "+ Nợ cũ" và tổng = 650.000đ', async () => {
  await page.fill('input[placeholder="Nhập Mã HS hoặc Tên học sinh..."]', 'HS20260004');
  await page.waitForTimeout(500);
  await page.locator('text=Phạm Gia Bảo').first().click();
  await page.waitForTimeout(1500);
  const txt = (await page.locator('body').innerText()).normalize('NFC');
  const amt = await page.locator('input[type=number]').first().inputValue();
  await shot('ui_pos_debt');
  expect(txt.includes('Nợ cũ'.normalize('NFC')) && Number(amt) === 650000, 'amount=' + amt);
});
test('TTHP_UI_15', 'Hoàn tác hóa đơn (xác nhận OK) -> "Đã hoàn tác (xóa) hóa đơn!"', async () => {
  page.once('dialog', (d) => d.accept());
  await clearToasts();
  await page.locator('table tbody tr').first().locator('button:has-text("Hoàn tác")').click();
  const t = await toastText();
  await shot('ui_pos_undo');
  expect(t.includes('hoàn tác'), 'Toast: ' + t);
});
test('TTHP_UI_16', 'Hoàn tác: bấm Cancel ở hộp xác nhận -> hóa đơn không bị xóa', async () => {
  const before = await page.locator('table tbody tr').count();
  page.once('dialog', (d) => d.dismiss());
  await page.locator('table tbody tr').first().locator('button:has-text("Hoàn tác")').click();
  await page.waitForTimeout(1000);
  const after = await page.locator('table tbody tr').count();
  expect(before === after, `Trước ${before} sau ${after}`);
});
test('TTHP_UI_17', 'Nút "Chọn nhiều" mở cửa sổ chọn nhiều học sinh theo khối', async () => {
  await page.click('button:has-text("Chọn nhiều")');
  await page.waitForTimeout(600);
  await shot('ui_pos_multi');
  const vis = await page.locator('select').count();
  await page.keyboard.press('Escape');
  expect(vis > 0, 'Không mở được cửa sổ chọn nhiều');
});
test('TTHP_UI_18', 'Giáo viên (member) truy cập /pos -> không được vào màn hình thu tiền', async () => {
  await apiCall('POST', '/users', { name: 'Nguyễn Tuấn Kiệt', email: 'kiet.gv@gmail.com', password: '123456', role: 'member', branch_id: 1 });
  const c2 = await browser.newContext();
  const p2 = await c2.newPage();
  p2.on('dialog', (d) => d.accept());
  await p2.goto(FE + '/login');
  await p2.fill('input[type=email]', 'kiet.gv@gmail.com');
  await p2.fill('input[type=password]', '123456');
  await p2.click('button:has-text("Đăng Nhập")');
  await p2.waitForTimeout(2500);
  await p2.goto(FE + '/pos');
  await p2.waitForTimeout(2000);
  const url = p2.url();
  await p2.screenshot({ path: `${SHOTS}/ui_pos_member.png` });
  await c2.close();
  expect(!url.endsWith('/pos'), 'Giáo viên vẫn vào được /pos: ' + url);
});

// ============================ RUNNER ============================
(async () => {
  browser = await chromium.launch({ headless: !process.env.HEADED, slowMo: process.env.HEADED ? 400 : 0 });
  ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  page = await ctx.newPage();
  page.setDefaultTimeout(8000);
  const ONLY = process.env.ONLY ? process.env.ONLY.split(',') : null;
  for (const t of tests) {
    if (ONLY && (t.pause || !ONLY.includes(t.id))) continue;
    if (t.pause) { console.log('… nghỉ 65s (rate limit)'); await page.waitForTimeout(65000); continue; }
    const start = Date.now();
    try {
      await t.fn();
      results.push({ id: t.id, name: t.name, status: 'P', ms: Date.now() - start });
      console.log('✔', t.id, t.name);
    } catch (e) {
      const msg = String(e.message || e).split('\n')[0];
      results.push({ id: t.id, name: t.name, status: 'F', note: msg, ms: Date.now() - start });
      console.log('✘', t.id, t.name, '->', msg);
      await page.screenshot({ path: `${SHOTS}/fail_${t.id}.png` }).catch(() => {});
      await page.keyboard.press('Escape').catch(() => {});
    }
  }
  fs.writeFileSync(__dirname + (ONLY ? '/results_ui_rerun.json' : '/results_ui.json'), JSON.stringify(results, null, 2));
  const p = results.filter((r) => r.status === 'P').length;
  console.log(`\nTổng: ${results.length} | Pass: ${p} | Fail: ${results.length - p}`);
  await browser.close();
})();
