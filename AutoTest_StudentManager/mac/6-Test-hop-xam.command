#!/bin/bash
# Kiểm thử hộp xám: thao tác qua API rồi mở cơ sở dữ liệu ra kiểm tra (bản trước khi sửa lỗi).
cd "$(dirname "$0")" || exit 1

# Chạy 1 test: lời giải thích đọc từ đầu vào chuẩn (khối EXPLAIN), sau đó là kết quả chạy thật.
run() {
  clear
  cat
  echo
  echo "---------------- KẾT QUẢ CHẠY ----------------"
  bash test-api.sh baocao "$1" </dev/null | grep -vE '(vendor|Users).*\.php|^PHPUnit|^Runtime|^Configuration|^$'
  echo
  read -n 1 -s -r -p "Nhấn phím bất kỳ để xem tiếp..." </dev/tty
}

run QLHS_API_01 <<'EXPLAIN'
KIỂM THỬ HỘP XÁM 1/3 - QLHS_API_01: Thêm học sinh hợp lệ

Mục đích : Xem hệ thống có lưu đúng thông tin học sinh không.
Thao tác : Gửi yêu cầu thêm học sinh "Nguyễn Văn Kiểm", SĐT phụ huynh 0912345678.
Kiểm tra : - Máy chủ trả mã 201 (đã tạo), tên trả về đúng.
           - Bảng students có bản ghi đúng tên, SĐT và cơ sở.
Mong đợi : Pass.
Ý nghĩa  : Dữ liệu được lưu thật, không chỉ báo thành công bên ngoài.
EXPLAIN

run QLHS_API_49 <<'EXPLAIN'
KIỂM THỬ HỘP XÁM 2/3 - QLHS_API_49: Xóa học sinh đã nộp học phí

Mục đích  : Xem xóa học sinh có làm mất dữ liệu tiền đã thu không.
Thao tác  : Tạo học sinh đã có hóa đơn đã thu, rồi xóa vĩnh viễn.
Kiểm tra  : Bảng invoices còn hóa đơn không.
Mong đợi  : Hóa đơn vẫn còn.
Phát hiện : Bảng hóa đơn trống (BUG_05, mức Cao).
Nguyên nhân: Cơ sở dữ liệu cài xóa dây chuyền (ON DELETE CASCADE).
Hậu quả   : Mất lịch sử thu tiền, doanh thu bị sai.
Cách sửa  : Chặn xóa vĩnh viễn học sinh đã có hóa đơn.
EXPLAIN

run TTHP_API_40 <<'EXPLAIN'
KIỂM THỬ HỘP XÁM 3/3 - TTHP_API_40: Từ chối hóa đơn

Mục đích  : Xem việc từ chối giao dịch có được ghi lại không.
Thao tác  : Tạo hóa đơn chuyển khoản, rồi từ chối qua API.
Kiểm tra  : Bảng audit_logs (nhật ký thao tác).
Mong đợi  : Có dòng INVOICE_REJECT.
Phát hiện : Chỉ có dòng INVOICE_CREATE (BUG_12, mức Trung bình).
Nguyên nhân: Chức năng từ chối không ghi nhật ký.
Hậu quả   : Không tra được ai đã từ chối hóa đơn và vào lúc nào.
Cách sửa  : Thêm bước ghi nhật ký khi từ chối.
EXPLAIN

clear
cat <<'EXPLAIN'
TÓM TẮT KIỂM THỬ HỘP XÁM
  1. QLHS_API_01  Pass  Thêm học sinh, dữ liệu lưu đúng.
  2. QLHS_API_49  Fail  Xóa học sinh thì mất luôn hóa đơn.
  3. TTHP_API_40  Fail  Từ chối hóa đơn mà không để lại dấu vết.

Hai lỗi sau thấy rõ khi mở cơ sở dữ liệu ra kiểm tra.
EXPLAIN
echo; read -n 1 -s -r -p "Nhấn phím bất kỳ để đóng..."
