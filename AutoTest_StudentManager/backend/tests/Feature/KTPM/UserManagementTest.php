<?php

namespace Tests\Feature\KTPM;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Kiểm thử tự động API chức năng QUẢN LÝ NGƯỜI DÙNG (/api/users) và ĐĂNG NHẬP (/api/login).
 */
class UserManagementTest extends KtpmTestCase
{
    private function payload(array $over = []): array
    {
        return array_merge([
            'name' => 'Phan Đình Mạnh',
            'email' => 'manh.ktpm@gmail.com',
            'password' => '123456',
            'role' => 'member',
            'branch_id' => $this->branch->id,
        ], $over);
    }

    // ===================== ĐĂNG NHẬP =====================

    #[TestDox('QLND_API_01: Đăng nhập đúng email/mật khẩu -> 200, trả token, vai trò và quyền')]
    public function test_api_01_dang_nhap_dung(): void
    {
        $this->postJson('/api/login', ['email' => 'admin@gmail.com', 'password' => '123456'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]);
    }

    #[TestDox('QLND_API_02: Đăng nhập sai mật khẩu -> 401 "Email hoặc mật khẩu không chính xác!"')]
    public function test_api_02_sai_mat_khau(): void
    {
        $this->postJson('/api/login', ['email' => 'admin@gmail.com', 'password' => 'sai'])
            ->assertStatus(401)->assertJsonFragment(['message' => 'Email hoặc mật khẩu không chính xác!']);
    }

    #[TestDox('QLND_API_03: Đăng nhập email không tồn tại -> 401')]
    public function test_api_03_email_khong_ton_tai(): void
    {
        $this->postJson('/api/login', ['email' => 'khongco@gmail.com', 'password' => '123456'])->assertStatus(401);
    }

    #[TestDox('QLND_API_04: Đăng nhập bỏ trống email và mật khẩu -> 422')]
    public function test_api_04_bo_trong(): void
    {
        $this->postJson('/api/login', ['email' => '', 'password' => ''])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    #[TestDox('QLND_API_05: Đăng nhập email sai định dạng (admin.gmail.com) -> 422')]
    public function test_api_05_email_sai_dinh_dang(): void
    {
        $this->postJson('/api/login', ['email' => 'admin.gmail.com', 'password' => '123456'])->assertStatus(422);
    }

    #[TestDox('QLND_API_06: Sai mật khẩu 5 lần liên tiếp -> lần thứ 6 bị khóa tạm (429)')]
    public function test_api_06_khoa_tam_sau_5_lan(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'admin@gmail.com', 'password' => 'sai' . $i])->assertStatus(401);
        }
        $this->postJson('/api/login', ['email' => 'admin@gmail.com', 'password' => '123456'])->assertStatus(429);
    }

    #[TestDox('QLND_API_07: Tài khoản có trạng thái "inactive" (bị khóa) không được đăng nhập')]
    public function test_api_07_tai_khoan_bi_khoa(): void
    {
        $u = User::create(['name' => 'Bị khóa', 'email' => 'khoa@gmail.com', 'password' => Hash::make('123456'), 'status' => 'inactive', 'branch_id' => $this->branch->id]);
        $u->assignRole('member');
        $this->postJson('/api/login', ['email' => 'khoa@gmail.com', 'password' => '123456'])->assertStatus(403);
    }

    #[TestDox('QLND_API_08: Đăng xuất -> token bị thu hồi, gọi lại API bằng token cũ -> 401')]
    public function test_api_08_dang_xuat(): void
    {
        $token = $this->admin->createToken('auth_token')->plainTextToken;
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->resetAuth();
        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }

    // ===================== DANH SÁCH =====================

    #[TestDox('QLND_API_09: Admin xem danh sách người dùng -> 200, có role_name và branch_name')]
    public function test_api_09_danh_sach(): void
    {
        $res = $this->asUser($this->admin, null)->getJson('/api/users')->assertOk();
        $this->assertGreaterThanOrEqual(3, count($res->json()));
        $this->assertArrayHasKey('role_name', $res->json('0'));
        $this->assertArrayHasKey('branch_name', $res->json('0'));
    }

    #[TestDox('QLND_API_10: Danh sách người dùng không trả về mật khẩu (password bị ẩn)')]
    public function test_api_10_an_mat_khau(): void
    {
        $res = $this->asUser($this->admin, null)->getJson('/api/users')->assertOk();
        $this->assertArrayNotHasKey('password', $res->json('0'));
    }

    // ===================== THÊM =====================

    #[TestDox('QLND_API_11: Thêm tài khoản hợp lệ -> 201, mật khẩu được mã hóa (hash)')]
    public function test_api_11_them_hop_le(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload())->assertStatus(201);
        $u = User::withoutGlobalScopes()->where('email', 'manh.ktpm@gmail.com')->first();
        $this->assertNotNull($u);
        $this->assertNotEquals('123456', $u->password);
        $this->assertTrue(Hash::check('123456', $u->password));
        $this->assertTrue($u->hasRole('member'));
    }

    #[TestDox('QLND_API_12: Tài khoản vừa tạo đăng nhập được ngay')]
    public function test_api_12_dang_nhap_tai_khoan_moi(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload())->assertStatus(201);
        $this->resetAuth();
        $this->postJson('/api/login', ['email' => 'manh.ktpm@gmail.com', 'password' => '123456'])->assertOk();
    }

    #[TestDox('QLND_API_13: Bỏ trống Họ và tên -> 422')]
    public function test_api_13_bo_trong_ten(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['name' => '']))->assertStatus(422);
    }

    #[TestDox('QLND_API_14: Họ và tên dài 256 ký tự -> 422 (max 255)')]
    public function test_api_14_ten_qua_dai(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['name' => str_repeat('a', 256)]))->assertStatus(422);
    }

    #[TestDox('QLND_API_15: Họ và tên đúng 255 ký tự (biên trên) -> 201')]
    public function test_api_15_ten_255(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['name' => str_repeat('a', 255)]))->assertStatus(201);
    }

    #[TestDox('QLND_API_16: Bỏ trống email -> 422')]
    public function test_api_16_bo_trong_email(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['email' => '']))->assertStatus(422);
    }

    #[TestDox('QLND_API_17: Email sai định dạng (manh@) -> 422')]
    public function test_api_17_email_sai(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['email' => 'manh@']))->assertStatus(422);
    }

    #[TestDox('QLND_API_18: Email đã tồn tại -> 422 "email has already been taken"')]
    public function test_api_18_email_trung(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['email' => 'giaovien@gmail.com']))->assertStatus(422);
    }

    #[TestDox('QLND_API_20: Mật khẩu 5 ký tự (dưới biên) -> 422')]
    public function test_api_20_mat_khau_5(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['password' => '12345']))->assertStatus(422);
    }

    #[TestDox('QLND_API_21: Mật khẩu đúng 6 ký tự (biên dưới) -> 201')]
    public function test_api_21_mat_khau_6(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['password' => '123456']))->assertStatus(201);
    }

    #[TestDox('QLND_API_22: Bỏ trống mật khẩu khi thêm mới -> 422')]
    public function test_api_22_bo_trong_mat_khau(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['password' => '']))->assertStatus(422);
    }

    #[TestDox('QLND_API_23: Vai trò không tồn tại (superman) -> 422')]
    public function test_api_23_vai_tro_khong_ton_tai(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['role' => 'superman']))->assertStatus(422);
    }

    #[TestDox('QLND_API_24: Tạo nhân viên (member) không chọn cơ sở -> 422 yêu cầu chọn cơ sở')]
    public function test_api_24_nhan_vien_khong_co_so(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['branch_id' => null]))->assertStatus(422);
    }

    #[TestDox('QLND_API_25: Tạo admin không chọn cơ sở (quản lý chung) -> 201')]
    public function test_api_25_admin_khong_co_so(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['role' => 'admin', 'branch_id' => null]))->assertStatus(201);
    }

    #[TestDox('QLND_API_26: Cơ sở không tồn tại (branch_id=999) -> 422')]
    public function test_api_26_co_so_khong_ton_tai(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['branch_id' => 999]))->assertStatus(422);
    }

    #[TestDox('QLND_API_27: Mật khẩu chỉ gồm khoảng trắng (6 dấu cách) -> 422')]
    public function test_api_27_mat_khau_khoang_trang(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['password' => '      ']))->assertStatus(422);
    }

    #[TestDox('QLND_API_28: Mật khẩu yếu (chỉ toàn số 111111) bị từ chối theo chính sách mật khẩu mạnh')]
    public function test_api_28_mat_khau_yeu(): void
    {
        $this->asUser($this->admin, null)->postJson('/api/users', $this->payload(['password' => '111111']))->assertStatus(422);
    }

    // ===================== SỬA =====================

    #[TestDox('QLND_API_29: Sửa tên, vai trò người dùng -> 200, vai trò được đồng bộ')]
    public function test_api_29_sua_hop_le(): void
    {
        $this->asUser($this->admin, null)->putJson("/api/users/{$this->member->id}", ['name' => 'GV Đã Sửa', 'email' => 'giaovien@gmail.com', 'role' => 'accountant', 'branch_id' => $this->branch->id])->assertOk();
        $u = User::withoutGlobalScopes()->find($this->member->id);
        $this->assertEquals('GV Đã Sửa', $u->name);
        $this->assertTrue($u->hasRole('accountant'));
        $this->assertFalse($u->hasRole('member'));
    }

    #[TestDox('QLND_API_30: Sửa nhưng để trống mật khẩu -> giữ nguyên mật khẩu cũ')]
    public function test_api_30_sua_giu_mat_khau(): void
    {
        $this->asUser($this->admin, null)->putJson("/api/users/{$this->member->id}", ['name' => 'GV', 'email' => 'giaovien@gmail.com', 'password' => ''])->assertOk();
        $this->assertTrue(Hash::check('123456', User::withoutGlobalScopes()->find($this->member->id)->password));
    }

    #[TestDox('QLND_API_31: Sửa mật khẩu mới -> đăng nhập bằng mật khẩu mới thành công, mật khẩu cũ thất bại')]
    public function test_api_31_doi_mat_khau(): void
    {
        $this->asUser($this->admin, null)->putJson("/api/users/{$this->member->id}", ['name' => 'GV', 'email' => 'giaovien@gmail.com', 'password' => 'matkhaumoi'])->assertOk();
        $this->resetAuth();
        $this->postJson('/api/login', ['email' => 'giaovien@gmail.com', 'password' => '123456'])->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'giaovien@gmail.com', 'password' => 'matkhaumoi'])->assertOk();
    }

    #[TestDox('QLND_API_32: Sửa email trùng với người dùng khác -> 422')]
    public function test_api_32_sua_email_trung(): void
    {
        $this->asUser($this->admin, null)->putJson("/api/users/{$this->member->id}", ['name' => 'GV', 'email' => 'ketoan@gmail.com'])->assertStatus(422);
    }

    #[TestDox('QLND_API_33: Sửa người dùng không tồn tại -> 404 "Không tìm thấy người dùng"')]
    public function test_api_33_sua_khong_ton_tai(): void
    {
        $this->asUser($this->admin, null)->putJson('/api/users/99999', ['name' => 'X', 'email' => 'x@gmail.com'])->assertStatus(404);
    }

    #[TestDox('QLND_API_34: Sửa mật khẩu mới 3 ký tự -> 422')]
    public function test_api_34_sua_mat_khau_ngan(): void
    {
        $this->asUser($this->admin, null)->putJson("/api/users/{$this->member->id}", ['name' => 'GV', 'email' => 'giaovien@gmail.com', 'password' => '123'])->assertStatus(422);
    }

    #[TestDox('QLND_API_35: Đổi mật khẩu của người dùng -> các phiên đăng nhập cũ (token) bị thu hồi')]
    public function test_api_35_doi_mk_thu_hoi_token(): void
    {
        $this->member->createToken('old');
        $this->asUser($this->admin, null)->putJson("/api/users/{$this->member->id}", ['name' => 'GV', 'email' => 'giaovien@gmail.com', 'password' => 'matkhaumoi'])->assertOk();
        $this->assertEquals(0, $this->member->tokens()->count());
    }

    // ===================== XÓA =====================

    #[TestDox('QLND_API_36: Xóa người dùng khác -> 200 và bị xóa khỏi CSDL')]
    public function test_api_36_xoa(): void
    {
        $this->asUser($this->admin, null)->deleteJson("/api/users/{$this->member->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $this->member->id]);
    }

    #[TestDox('QLND_API_37: Tự xóa tài khoản đang đăng nhập -> 403')]
    public function test_api_37_tu_xoa(): void
    {
        $this->asUser($this->admin, null)->deleteJson("/api/users/{$this->admin->id}")->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    #[TestDox('QLND_API_38: Xóa người dùng không tồn tại -> 404')]
    public function test_api_38_xoa_khong_ton_tai(): void
    {
        $this->asUser($this->admin, null)->deleteJson('/api/users/99999')->assertStatus(404);
    }

    #[TestDox('QLND_API_39: Sau khi bị xóa, token cũ của người dùng không còn truy cập được')]
    public function test_api_39_token_sau_khi_xoa(): void
    {
        $token = $this->member->createToken('t')->plainTextToken;
        $this->asUser($this->admin, null)->deleteJson("/api/users/{$this->member->id}")->assertOk();
        $this->resetAuth();
        $this->withToken($token)->getJson('/api/students')->assertStatus(401);
    }

    // ===================== PHÂN QUYỀN =====================

    #[TestDox('QLND_API_40: Giáo viên (member) truy cập danh sách người dùng -> 403')]
    public function test_api_40_member_xem_users(): void
    {
        $this->asUser($this->member)->getJson('/api/users')->assertStatus(403);
    }

    #[TestDox('QLND_API_41: Kế toán tạo tài khoản mới -> 403')]
    public function test_api_41_ke_toan_tao_user(): void
    {
        $this->asUser($this->accountant)->postJson('/api/users', $this->payload())->assertStatus(403);
    }

    #[TestDox('QLND_API_42: Chưa đăng nhập gọi /api/users -> 401')]
    public function test_api_42_chua_dang_nhap(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
    }

    #[TestDox('QLND_API_43: Người dùng tự cập nhật hồ sơ (tên, mật khẩu) qua /api/profile -> 200')]
    public function test_api_43_cap_nhat_ho_so(): void
    {
        $this->asUser($this->member)->putJson('/api/profile', ['name' => 'Tên Hồ Sơ Mới', 'email' => 'giaovien@gmail.com', 'password' => 'abcdef'])->assertOk();
        $this->assertTrue(Hash::check('abcdef', User::withoutGlobalScopes()->find($this->member->id)->password));
    }

    #[TestDox('QLND_API_44: Người dùng tự đổi vai trò của mình qua /api/profile -> không có tác dụng')]
    public function test_api_44_tu_nang_quyen(): void
    {
        $this->asUser($this->member)->putJson('/api/profile', ['name' => 'GV', 'email' => 'giaovien@gmail.com', 'role' => 'admin'])->assertOk();
        $this->assertFalse(User::withoutGlobalScopes()->find($this->member->id)->hasRole('admin'));
    }

    #[TestDox('QLND_API_45: Quản lý cơ sở 1 (manage_users) không thấy tài khoản của cơ sở 2')]
    public function test_api_45_phan_tach_co_so(): void
    {
        $other = User::create(['name' => 'NV CS2', 'email' => 'cs2@gmail.com', 'password' => Hash::make('123456'), 'branch_id' => $this->branch2->id]);
        $other->assignRole('member');
        $mgr = User::create(['name' => 'QL CS1', 'email' => 'ql1@gmail.com', 'password' => Hash::make('123456'), 'branch_id' => $this->branch->id]);
        $mgr->givePermissionTo('manage_users');
        $emails = collect($this->asUser($mgr)->getJson('/api/users')->assertOk()->json())->pluck('email');
        $this->assertFalse($emails->contains('cs2@gmail.com'));
    }
}
