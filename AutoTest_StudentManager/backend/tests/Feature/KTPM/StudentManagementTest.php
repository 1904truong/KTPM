<?php

namespace Tests\Feature\KTPM;

use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Kiểm thử tự động API chức năng QUẢN LÝ HỌC SINH (/api/students).
 * Mỗi test ứng với 1 test case trong file Excel (cột ID).
 * Test được viết theo KẾT QUẢ MONG MUỐN; test nào đỏ = hệ thống có lỗi (Fail).
 */
class StudentManagementTest extends KtpmTestCase
{
    // ===================== THÊM MỚI HỌC SINH =====================

    #[TestDox('QLHS_API_01: Thêm học sinh hợp lệ trả về 201 và lưu đúng dữ liệu')]
    public function test_api_01_them_hoc_sinh_hop_le(): void
    {
        $res = $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload());
        $res->assertStatus(201)->assertJsonPath('full_name', 'Nguyễn Văn Kiểm');
        $this->assertDatabaseHas('students', ['full_name' => 'Nguyễn Văn Kiểm', 'parent_phone' => '0912345678', 'branch_id' => $this->branch->id]);
    }

    #[TestDox('QLHS_API_02: Mã học sinh tự sinh theo định dạng HS + năm + 4 số')]
    public function test_api_02_ma_hoc_sinh_tu_sinh(): void
    {
        $res = $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload());
        $this->assertMatchesRegularExpression('/^HS' . date('Y') . '\d{4}$/', $res->json('student_code'));
    }

    #[TestDox('QLHS_API_03: Mã học sinh không trùng khi thêm liên tiếp nhiều học sinh')]
    public function test_api_03_ma_hoc_sinh_khong_trung(): void
    {
        $codes = [];
        for ($i = 1; $i <= 5; $i++) {
            $codes[] = $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['full_name' => "Học Sinh Số $i", 'parent_phone' => '091234567' . $i]))->json('student_code');
        }
        $this->assertCount(5, array_unique($codes));
    }

    #[TestDox('QLHS_API_04: Bỏ trống Họ và tên -> 422 kèm thông báo lỗi')]
    public function test_api_04_bo_trong_ho_ten(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['full_name' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('full_name');
    }

    #[TestDox('QLHS_API_05: Họ và tên chỉ chứa khoảng trắng -> 422')]
    public function test_api_05_ho_ten_chi_khoang_trang(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['full_name' => '     ']))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_06: Họ và tên có khoảng trắng đầu/cuối được tự động cắt (trim)')]
    public function test_api_06_trim_ho_ten(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['full_name' => '   Lê Thu Hà   ']))->assertStatus(201);
        $this->assertDatabaseHas('students', ['full_name' => 'Lê Thu Hà']);
    }

    #[TestDox('QLHS_API_07: Họ và tên dài 256 ký tự (vượt độ dài cột 255) -> 422')]
    public function test_api_07_ho_ten_qua_dai(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['full_name' => str_repeat('A', 256)]))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_08: Họ và tên chứa số/ký tự đặc biệt (Nguyen123@#) -> 422')]
    public function test_api_08_ho_ten_ky_tu_dac_biet(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['full_name' => 'Nguyen123@#']))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_09: Bỏ trống Khối/Lớp (grade) -> 422')]
    public function test_api_09_bo_trong_khoi(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['grade' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('grade');
    }

    #[TestDox('QLHS_API_10: Khối là chữ (grade = "abc") -> 422')]
    public function test_api_10_khoi_la_chu(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['grade' => 'abc']))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_11: Khối ngoài phạm vi 1-12 (grade = 13) -> 422')]
    public function test_api_11_khoi_ngoai_pham_vi(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['grade' => 13]))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_12: Học sinh khối 10/11/12 không lưu loại lớp (class_type = null)')]
    public function test_api_12_khoi_thpt_khong_luu_loai_lop(): void
    {
        $res = $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['grade' => 10, 'class_type' => 'NC']));
        $res->assertStatus(201);
        $this->assertNull($res->json('class_type'));
    }

    #[TestDox('QLHS_API_13: SĐT phụ huynh chứa chữ (09abc12345) -> 422')]
    public function test_api_13_sdt_chua_chu(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['parent_phone' => '09abc12345']))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_14: SĐT phụ huynh quá ngắn (123) -> 422')]
    public function test_api_14_sdt_qua_ngan(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['parent_phone' => '123']))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_15: SĐT phụ huynh 10 số hợp lệ được chấp nhận')]
    public function test_api_15_sdt_hop_le(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['parent_phone' => '0987654321']))
            ->assertStatus(201);
    }

    #[TestDox('QLHS_API_16: Không nhập SĐT phụ huynh (trường không bắt buộc) vẫn thêm được')]
    public function test_api_16_khong_nhap_sdt(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['parent_phone' => null, 'parent_name' => null]))
            ->assertStatus(201);
    }

    #[TestDox('QLHS_API_17: Trùng Họ tên + SĐT phụ huynh -> 422 "Học sinh này đã tồn tại"')]
    public function test_api_17_trung_hoc_sinh(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload())->assertStatus(201);
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload())
            ->assertStatus(422)->assertJsonFragment(['message' => 'Học sinh này đã tồn tại trong hệ thống (trùng Tên và SĐT phụ huynh)!']);
    }

    #[TestDox('QLHS_API_18: Cùng họ tên nhưng khác SĐT phụ huynh -> cho phép thêm (trường hợp trùng tên)')]
    public function test_api_18_trung_ten_khac_sdt(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload())->assertStatus(201);
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['parent_phone' => '0911111111']))->assertStatus(201);
    }

    #[TestDox('QLHS_API_19: Học phí/buổi âm (-130000) -> 422')]
    public function test_api_19_hoc_phi_am(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['price_per_session' => -130000]))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_20: Dư nợ hiện tại âm (-500000) -> 422')]
    public function test_api_20_du_no_am(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['debt' => -500000]))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_21: Không nhập học phí/buổi -> mặc định 130.000đ')]
    public function test_api_21_hoc_phi_mac_dinh(): void
    {
        $payload = $this->validStudentPayload();
        unset($payload['price_per_session']);
        $res = $this->asUser($this->admin)->postJson('/api/students', $payload)->assertStatus(201);
        $this->assertEquals(130000, (float) $res->json('price_per_session'));
    }

    #[TestDox('QLHS_API_22: Ngày bắt đầu sai định dạng -> 422 (không tự đổi thành ngày hôm nay)')]
    public function test_api_22_ngay_bat_dau_sai(): void
    {
        $this->asUser($this->admin)->postJson('/api/students', $this->validStudentPayload(['start_date' => 'ngay-abc']))
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_23: Admin chọn "Tất cả cơ sở" khi thêm -> 422 yêu cầu chọn cơ sở')]
    public function test_api_23_admin_chua_chon_co_so(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/students', $this->validStudentPayload())
            ->assertStatus(422);
    }

    #[TestDox('QLHS_API_24: Học sinh mới được gán đúng cơ sở của nhân viên tạo')]
    public function test_api_24_gan_dung_co_so(): void
    {
        $this->asUser($this->member)->postJson('/api/students', $this->validStudentPayload())->assertStatus(201);
        $this->assertDatabaseHas('students', ['full_name' => 'Nguyễn Văn Kiểm', 'branch_id' => $this->branch->id]);
    }

    // ===================== DANH SÁCH / TÌM KIẾM =====================

    #[TestDox('QLHS_API_25: Lấy danh sách học sinh trả về 200 và đủ các trường hiển thị')]
    public function test_api_25_danh_sach(): void
    {
        $this->makeStudent();
        $res = $this->asUser($this->admin)->getJson('/api/students')->assertOk();
        $row = $res->json('data.0');
        foreach (['student_code', 'full_name', 'grade', 'class_type', 'parent_name', 'parent_phone', 'total_sessions_in_cycle', 'final_amount'] as $k) {
            $this->assertArrayHasKey($k, $row);
        }
    }

    #[TestDox('QLHS_API_26: Danh sách không hiển thị học sinh đã nghỉ học (status = dropped)')]
    public function test_api_26_an_hoc_sinh_nghi(): void
    {
        $this->makeStudent(['full_name' => 'Đang Học']);
        $this->makeStudent(['full_name' => 'Đã Nghỉ', 'status' => 'dropped']);
        $names = collect($this->asUser($this->admin)->getJson('/api/students')->json('data'))->pluck('full_name');
        $this->assertTrue($names->contains('Đang Học'));
        $this->assertFalse($names->contains('Đã Nghỉ'));
    }

    #[TestDox('QLHS_API_27: Tìm kiếm theo một phần họ tên trả về đúng học sinh')]
    public function test_api_27_tim_theo_ten(): void
    {
        $this->makeStudent(['full_name' => 'Trần Minh An']);
        $this->makeStudent(['full_name' => 'Lê Thu Hà']);
        $names = collect($this->asUser($this->admin)->getJson('/api/students?search=Minh')->json('data'))->pluck('full_name');
        $this->assertEquals(['Trần Minh An'], $names->all());
    }

    #[TestDox('QLHS_API_28: Tìm kiếm theo mã học sinh trả về đúng 1 kết quả')]
    public function test_api_28_tim_theo_ma(): void
    {
        $s = $this->makeStudent();
        $this->makeStudent();
        $data = $this->asUser($this->admin)->getJson('/api/students?search=' . $s->student_code)->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($s->student_code, $data[0]['student_code']);
    }

    #[TestDox('QLHS_API_29: Tìm kiếm không phân biệt hoa/thường với tiếng Việt có dấu (TRẦN = trần)')]
    public function test_api_29_khong_phan_biet_hoa_thuong(): void
    {
        $this->makeStudent(['full_name' => 'Trần Minh An']);
        $data = $this->asUser($this->admin)->getJson('/api/students?search=' . urlencode('TRẦN'))->json('data');
        $this->assertCount(1, $data);
    }

    #[TestDox('QLHS_API_30: Tìm kiếm không dấu (tran minh) tìm được "Trần Minh An"')]
    public function test_api_30_tim_khong_dau(): void
    {
        $this->makeStudent(['full_name' => 'Trần Minh An']);
        $data = $this->asUser($this->admin)->getJson('/api/students?search=' . urlencode('tran minh'))->json('data');
        $this->assertCount(1, $data);
    }

    #[TestDox('QLHS_API_31: Từ khóa có khoảng trắng đầu/cuối được trim khi tìm kiếm')]
    public function test_api_31_trim_tu_khoa(): void
    {
        $this->makeStudent(['full_name' => 'Trần Minh An']);
        $data = $this->asUser($this->admin)->getJson('/api/students?search=' . urlencode('   Minh An   '))->json('data');
        $this->assertCount(1, $data);
    }

    #[TestDox('QLHS_API_32: Tìm kiếm không có kết quả trả về danh sách rỗng')]
    public function test_api_32_khong_co_ket_qua(): void
    {
        $this->makeStudent();
        $this->asUser($this->admin)->getJson('/api/students?search=KHONGTONTAI999')->assertOk()->assertJsonCount(0, 'data');
    }

    #[TestDox('QLHS_API_33: Từ khóa chứa ký tự đặc biệt SQL (\' OR 1=1 --) không gây lỗi, không trả toàn bộ dữ liệu')]
    public function test_api_33_sql_injection(): void
    {
        $this->makeStudent();
        $this->makeStudent();
        $this->asUser($this->admin)->getJson('/api/students?search=' . urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
    }

    #[TestDox('QLHS_API_34: Lọc theo khối (grade=9) chỉ trả về học sinh khối 9')]
    public function test_api_34_loc_theo_khoi(): void
    {
        $this->makeStudent(['grade' => 6]);
        $this->makeStudent(['grade' => 9, 'class_type' => 'NC']);
        $grades = collect($this->asUser($this->admin)->getJson('/api/students?grade=9')->json('data'))->pluck('grade')->unique()->values();
        $this->assertEquals([9], $grades->all());
    }

    #[TestDox('QLHS_API_35: Danh sách sắp xếp theo Khối -> Loại lớp -> Tên')]
    public function test_api_35_sap_xep(): void
    {
        $this->makeStudent(['full_name' => 'Nguyễn Văn Bình', 'grade' => 9, 'class_type' => 'NC']);
        $this->makeStudent(['full_name' => 'Lê Văn An', 'grade' => 6]);
        $this->makeStudent(['full_name' => 'Trần Văn Cường', 'grade' => 6]);
        $names = collect($this->asUser($this->admin)->getJson('/api/students')->json('data'))->pluck('full_name')->all();
        $this->assertEquals(['Lê Văn An', 'Trần Văn Cường', 'Nguyễn Văn Bình'], $names);
    }

    #[TestDox('QLHS_API_36: Nhân viên cơ sở 1 không nhìn thấy học sinh của cơ sở 2')]
    public function test_api_36_phan_tach_co_so(): void
    {
        $this->makeStudent(['full_name' => 'Học Sinh CS1']);
        $this->makeStudent(['full_name' => 'Học Sinh CS2', 'branch_id' => $this->branch2->id]);
        $names = collect($this->asUser($this->member)->getJson('/api/students')->json('data'))->pluck('full_name');
        $this->assertTrue($names->contains('Học Sinh CS1'));
        $this->assertFalse($names->contains('Học Sinh CS2'));
    }

    #[TestDox('QLHS_API_37: Số buổi học và học phí trong kỳ được tính đúng (3 buổi x 130.000 = 390.000)')]
    public function test_api_37_tinh_hoc_phi_ky(): void
    {
        $s = $this->makeStudent();
        $this->addAttendance($s, 3);
        $row = collect($this->asUser($this->admin)->getJson('/api/students')->json('data'))->firstWhere('id', $s->id);
        $this->assertEquals(3, $row['total_sessions_in_cycle']);
        $this->assertEquals(390000, (float) $row['total_tuition_in_cycle']);
    }

    // ===================== CẬP NHẬT =====================

    #[TestDox('QLHS_API_38: Cập nhật họ tên, SĐT hợp lệ -> 200 và dữ liệu thay đổi')]
    public function test_api_38_cap_nhat_hop_le(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->admin)->putJson("/api/students/{$s->id}", ['full_name' => 'Tên Mới', 'parent_phone' => '0900000001'])->assertOk();
        $this->assertDatabaseHas('students', ['id' => $s->id, 'full_name' => 'Tên Mới', 'parent_phone' => '0900000001']);
    }

    #[TestDox('QLHS_API_39: Cập nhật với Họ và tên rỗng -> 422 (không lỗi 500)')]
    public function test_api_39_cap_nhat_ten_rong(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->admin)->putJson("/api/students/{$s->id}", ['full_name' => ''])->assertStatus(422);
    }

    #[TestDox('QLHS_API_40: Cập nhật khối thành chữ ("abc") -> 422')]
    public function test_api_40_cap_nhat_khoi_sai(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->admin)->putJson("/api/students/{$s->id}", ['grade' => 'abc'])->assertStatus(422);
    }

    #[TestDox('QLHS_API_41: Cập nhật SĐT phụ huynh sai định dạng ("abc") -> 422')]
    public function test_api_41_cap_nhat_sdt_sai(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->admin)->putJson("/api/students/{$s->id}", ['parent_phone' => 'abc'])->assertStatus(422);
    }

    #[TestDox('QLHS_API_42: Không cho phép sửa trường nhạy cảm status/price_per_session qua API cập nhật')]
    public function test_api_42_khong_sua_truong_nhay_cam(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->admin)->putJson("/api/students/{$s->id}", ['status' => 'dropped', 'price_per_session' => 1])->assertOk();
        $this->assertDatabaseHas('students', ['id' => $s->id, 'status' => 'active', 'price_per_session' => 130000]);
    }

    #[TestDox('QLHS_API_43: Cập nhật học sinh không tồn tại -> 404')]
    public function test_api_43_cap_nhat_khong_ton_tai(): void
    {
        $this->asUser($this->admin)->putJson('/api/students/99999', ['full_name' => 'X'])->assertStatus(404);
    }

    #[TestDox('QLHS_API_44: Cập nhật gây trùng Họ tên + SĐT với học sinh khác -> 422')]
    public function test_api_44_cap_nhat_gay_trung(): void
    {
        $a = $this->makeStudent(['full_name' => 'Trùng Tên', 'parent_phone' => '0911000000']);
        $b = $this->makeStudent();
        $this->asUser($this->admin)->putJson("/api/students/{$b->id}", ['full_name' => 'Trùng Tên', 'parent_phone' => '0911000000'])->assertStatus(422);
    }

    #[TestDox('QLHS_API_45: Cập nhật dư nợ âm (-1.000.000) -> 422')]
    public function test_api_45_cap_nhat_no_am(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->admin)->putJson("/api/students/{$s->id}", ['debt' => -1000000])->assertStatus(422);
    }

    #[TestDox('QLHS_API_46: Cập nhật ghi chú (teacher_comment) thành công')]
    public function test_api_46_cap_nhat_ghi_chu(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->admin)->putJson("/api/students/{$s->id}", ['teacher_comment' => 'Chăm chỉ'])->assertOk();
        $this->assertDatabaseHas('students', ['id' => $s->id, 'teacher_comment' => 'Chăm chỉ']);
    }

    // ===================== XÓA =====================

    #[TestDox('QLHS_API_47: Xóa với lý do "Nghỉ học" -> chuyển status = dropped, giữ dữ liệu')]
    public function test_api_47_xoa_nghi_hoc(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->admin)->deleteJson("/api/students/{$s->id}", ['reason' => 'dropped'])->assertOk();
        $this->assertDatabaseHas('students', ['id' => $s->id, 'status' => 'dropped']);
    }

    #[TestDox('QLHS_API_48: Xóa với lý do "Xóa nhầm" -> xóa vĩnh viễn khỏi CSDL')]
    public function test_api_48_xoa_vinh_vien(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->admin)->deleteJson("/api/students/{$s->id}", ['reason' => 'error'])->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $s->id]);
    }

    #[TestDox('QLHS_API_49: Xóa vĩnh viễn học sinh ĐÃ CÓ hóa đơn đã thu -> phải chặn để không mất dữ liệu tài chính')]
    public function test_api_49_xoa_hs_co_hoa_don(): void
    {
        $s = $this->makeStudent();
        DB::table('invoices')->insert(['invoice_code' => 'HDTEST01', 'student_id' => $s->id, 'title' => 'HP', 'amount' => 390000, 'status' => 'paid', 'approval_status' => 'approved', 'payment_method' => 'cash', 'paid_at' => now(), 'branch_id' => $this->branch->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->asUser($this->admin)->deleteJson("/api/students/{$s->id}", ['reason' => 'error']);
        $this->assertDatabaseHas('invoices', ['invoice_code' => 'HDTEST01']);
    }

    #[TestDox('QLHS_API_50: Xóa học sinh không tồn tại -> 404')]
    public function test_api_50_xoa_khong_ton_tai(): void
    {
        $this->asUser($this->admin)->deleteJson('/api/students/99999', ['reason' => 'dropped'])->assertStatus(404);
    }

    #[TestDox('QLHS_API_51: Xóa hàng loạt 3 học sinh (lý do Nghỉ học) -> cả 3 chuyển dropped')]
    public function test_api_51_xoa_hang_loat(): void
    {
        $ids = [$this->makeStudent()->id, $this->makeStudent()->id, $this->makeStudent()->id];
        $this->asUser($this->admin)->postJson('/api/students/bulk-delete', ['student_ids' => $ids, 'reason' => 'dropped'])->assertOk();
        $this->assertEquals(3, Student::withoutGlobalScopes()->whereIn('id', $ids)->where('status', 'dropped')->count());
    }

    #[TestDox('QLHS_API_52: Xóa hàng loạt với danh sách rỗng -> 422')]
    public function test_api_52_xoa_hang_loat_rong(): void
    {
        $this->asUser($this->admin)->postJson('/api/students/bulk-delete', ['student_ids' => [], 'reason' => 'dropped'])->assertStatus(422);
    }

    #[TestDox('QLHS_API_53: Xóa hàng loạt thiếu lý do -> 422')]
    public function test_api_53_xoa_hang_loat_thieu_ly_do(): void
    {
        $this->asUser($this->admin)->postJson('/api/students/bulk-delete', ['student_ids' => [$this->makeStudent()->id]])->assertStatus(422);
    }

    #[TestDox('QLHS_API_54: Chuyển lớp hàng loạt cập nhật class_type cho các học sinh được chọn')]
    public function test_api_54_chuyen_lop_hang_loat(): void
    {
        $ids = [$this->makeStudent()->id, $this->makeStudent()->id];
        $this->asUser($this->admin)->postJson('/api/students/bulk-update-class', ['student_ids' => $ids, 'class_type' => 'CLC'])->assertOk();
        $this->assertEquals(2, Student::withoutGlobalScopes()->whereIn('id', $ids)->where('class_type', 'CLC')->count());
    }

    #[TestDox('QLHS_API_55: Import file sai định dạng (.txt) -> 422 thông báo định dạng')]
    public function test_api_55_import_sai_dinh_dang(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('ds.txt', 'abc');
        $this->asUser($this->admin)->post('/api/students/import', ['file' => $file])->assertStatus(422);
    }

    #[TestDox('QLHS_API_56: Import khi Admin đang chọn "Tất cả cơ sở" -> 422 yêu cầu chọn cơ sở')]
    public function test_api_56_import_chua_chon_co_so(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('ds.csv', "a,b\n1,2");
        $this->asUser($this->admin, 'all')->post('/api/students/import', ['file' => $file])->assertStatus(422);
    }

    // ===================== PHÂN QUYỀN =====================

    #[TestDox('QLHS_API_57: Chưa đăng nhập gọi API danh sách học sinh -> 401')]
    public function test_api_57_chua_dang_nhap(): void
    {
        $this->getJson('/api/students')->assertStatus(401);
    }

    #[TestDox('QLHS_API_58: Kế toán (không có quyền edit_students) thêm học sinh -> 403')]
    public function test_api_58_ke_toan_them_hs(): void
    {
        $this->asUser($this->accountant)->postJson('/api/students', $this->validStudentPayload())->assertStatus(403);
    }

    #[TestDox('QLHS_API_59: Giáo viên (member, không có quyền delete_students) xóa học sinh -> 403')]
    public function test_api_59_giao_vien_xoa_hs(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->member)->deleteJson("/api/students/{$s->id}", ['reason' => 'error'])->assertStatus(403);
        $this->assertDatabaseHas('students', ['id' => $s->id]);
    }

    #[TestDox('QLHS_API_60: Giáo viên cơ sở 1 không sửa được học sinh của cơ sở 2 -> 404')]
    public function test_api_60_sua_hs_khac_co_so(): void
    {
        $s = $this->makeStudent(['branch_id' => $this->branch2->id, 'full_name' => 'HS Cơ Sở 2']);
        $this->asUser($this->member)->putJson("/api/students/{$s->id}", ['full_name' => 'Bị sửa'])->assertStatus(404);
        $this->assertDatabaseHas('students', ['id' => $s->id, 'full_name' => 'HS Cơ Sở 2']);
    }
}
