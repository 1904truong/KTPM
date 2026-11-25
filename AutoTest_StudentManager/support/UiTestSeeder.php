<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Dữ liệu mẫu dựng lại theo "Tiền điều kiện" trong file Excel (chỉ dùng cho CSDL test giao diện). */
class UiTestSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $b1 = \App\Models\Branch::create(['name' => 'Cơ sở Chính', 'address' => 'Hà Nội', 'phone' => '0988888888']);
        \App\Models\Branch::create(['name' => 'Cơ sở 2', 'address' => 'Hà Nội', 'phone' => '0900000002']);
        $this->call(RolePermissionSeeder::class);

        foreach ([
            ['Quản Trị Viên', 'admin@gmail.com', 'admin', null, 'active'],
            ['Kế Toán', 'ketoan@gmail.com', 'accountant', $b1->id, 'active'],
            ['Giáo Viên', 'giaovien@gmail.com', 'member', $b1->id, 'active'],
            ['Khoa', 'khoa@gmail.com', 'member', $b1->id, 'inactive'],
        ] as [$name, $email, $role, $branch, $status]) {
            $u = User::forceCreate(['name' => $name, 'email' => $email, 'password' => Hash::make('123456'), 'role' => $role, 'branch_id' => $branch, 'status' => $status]);
            $u->assignRole($role);
        }

        $classes = [];
        foreach ([['6CC1', 6], ['6CLC', 6], ['9NC', 9], ['Khối 10', 10]] as [$code, $grade]) {
            $classes[] = DB::table('classes')->insertGetId([
                'class_code' => $code, 'name' => $code, 'grade' => $grade, 'price_per_session' => 130000,
                'status' => 'active', 'branch_id' => $b1->id, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $students = [
            [2, 'Trần Minh An', 0, 'CC1', 0, 3], [3, 'Lê Thu Hà', 0, 'CC1', 0, 3],
            [4, 'Phạm Gia Bảo', 1, 'CLC', 260000, 3], [5, 'Nguyễn Hải Nam', 1, 'CLC', 0, 3],
            [6, 'Vũ Ngọc Lan', 2, 'NC', 0, 0], [7, 'Đỗ Quốc Huy', 2, 'NC', 0, 0],
            [8, 'Bùi Thanh Mai', 3, null, 0, 0], [9, 'Hoàng Đức Long', 3, null, 0, 0],
        ];
        $grades = [6, 6, 9, 10];
        foreach ($students as [$n, $name, $c, $type, $debt, $sessions]) {
            $id = DB::table('students')->insertGetId([
                'student_code' => 'HS2026' . sprintf('%04d', $n), 'full_name' => $name,
                'parent_name' => 'Phụ Huynh ' . $n, 'parent_phone' => '090000000' . $n,
                'grade' => $grades[$c], 'class_type' => $type, 'price_per_session' => 130000, 'debt' => $debt,
                'start_date' => '2026-09-01', 'status' => 'studying', 'branch_id' => $b1->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('class_student')->insert(['class_id' => $classes[$c], 'student_id' => $id, 'created_at' => $now, 'updated_at' => $now]);
            // Buổi học nằm trong kỳ phí của tháng hiện tại (21 tháng trước -> 20 tháng này)
            for ($i = 0; $i < $sessions; $i++) {
                DB::table('attendances')->insert([
                    'student_id' => $id, 'class_id' => $classes[$c], 'status' => 'present', 'session_fee' => 130000,
                    'shift' => 'Ca 1 (17:30 - 19:00)', 'checked_at' => $now->copy()->day(10 + $i)->setTime(17, 30),
                    'branch_id' => $b1->id, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }
}
