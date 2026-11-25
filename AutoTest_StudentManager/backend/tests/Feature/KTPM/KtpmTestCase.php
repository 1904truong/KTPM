<?php

namespace Tests\Feature\KTPM;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Lớp nền cho bộ kiểm thử tự động BTL Kiểm thử phần mềm - Nhóm 1.
 * Dựng dữ liệu mẫu: 2 cơ sở, 3 vai trò (admin, accountant, member) và vài học sinh.
 */
abstract class KtpmTestCase extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected Branch $branch2;
    protected User $admin;
    protected User $accountant;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch  = Branch::create(['name' => 'Cơ sở Chính', 'address' => 'Hà Nội']);
        $this->branch2 = Branch::create(['name' => 'Cơ sở 2', 'address' => 'Hà Nội']);
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create(['name' => 'Quản Trị Viên', 'email' => 'admin@gmail.com', 'password' => Hash::make('123456'), 'branch_id' => null]);
        $this->admin->assignRole('admin');
        $this->accountant = User::create(['name' => 'Kế Toán', 'email' => 'ketoan@gmail.com', 'password' => Hash::make('123456'), 'branch_id' => $this->branch->id]);
        $this->accountant->assignRole('accountant');
        $this->member = User::create(['name' => 'Giáo Viên', 'email' => 'giaovien@gmail.com', 'password' => Hash::make('123456'), 'branch_id' => $this->branch->id]);
        $this->member->assignRole('member');
    }

    /** Đăng nhập giả lập + gửi header cơ sở như frontend. */
    protected function asUser(User $user, $branchId = 'default')
    {
        Sanctum::actingAs($user);
        $branchId = $branchId === 'default' ? $this->branch->id : $branchId;
        return $this->withHeaders(array_filter([
            'Accept' => 'application/json',
            'X-Branch-Id' => $branchId === null ? null : (string) $branchId,
        ]));
    }

    /** Xóa trạng thái đăng nhập giả lập để gọi API như một client mới (dùng token thật). */
    protected function resetAuth(): void
    {
        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();
    }

    protected function makeStudent(array $attrs = []): Student
    {
        static $n = 100;
        $n++;
        $id = DB::table('students')->insertGetId(array_merge([
            'student_code' => 'HS2026' . str_pad($n, 4, '0', STR_PAD_LEFT),
            'full_name' => 'Học Sinh ' . $n,
            'parent_name' => 'Phụ Huynh ' . $n,
            'parent_phone' => '09' . str_pad($n, 8, '0', STR_PAD_LEFT),
            'grade' => 6,
            'class_type' => 'CC1',
            'price_per_session' => 130000,
            'debt' => 0,
            'start_date' => '2026-09-01',
            'status' => 'active',
            'branch_id' => $this->branch->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
        return Student::withoutGlobalScopes()->find($id);
    }

    protected function addAttendance(Student $s, int $sessions, $date = null): void
    {
        for ($i = 0; $i < $sessions; $i++) {
            DB::table('attendances')->insert([
                'student_id' => $s->id, 'status' => 'present', 'session_fee' => 130000,
                'checked_at' => ($date ? \Carbon\Carbon::parse($date) : now())->copy()->setTime(18, 0)->subMinutes($i),
                'shift' => '18:00 - 19:30', 'branch_id' => $s->branch_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function validStudentPayload(array $over = []): array
    {
        return array_merge([
            'full_name' => 'Nguyễn Văn Kiểm',
            'grade' => 6,
            'class_type' => 'CC1',
            'parent_name' => 'Nguyễn Văn Bình',
            'parent_phone' => '0912345678',
            'price_per_session' => 130000,
            'start_date' => '2026-11-01',
            'debt' => 0,
        ], $over);
    }
}
