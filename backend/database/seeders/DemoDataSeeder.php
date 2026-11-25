<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Dữ liệu GIẢ dùng để demo giao diện. Mọi tên, số điện thoại đều được sinh ngẫu nhiên.
 */
class DemoDataSeeder extends Seeder
{
    private array $ho = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Vũ', 'Đặng', 'Bùi', 'Đỗ', 'Ngô', 'Dương', 'Lý'];
    private array $dem = ['Văn', 'Thị', 'Minh', 'Ngọc', 'Gia', 'Đức', 'Thu', 'Hải', 'Quốc', 'Bảo', 'Khánh', 'Thanh'];
    private array $ten = ['An', 'Bình', 'Châu', 'Dũng', 'Giang', 'Hà', 'Hùng', 'Khoa', 'Linh', 'Long', 'Mai', 'Nam',
        'Ngân', 'Phúc', 'Quân', 'Sơn', 'Tâm', 'Thảo', 'Trang', 'Tuấn', 'Vy', 'Yến', 'Huy', 'Lan', 'My', 'Nhi'];

    public function run(): void
    {
        mt_srand(2026);
        $now = Carbon::now();

        $mainBranch = DB::table('branches')->orderBy('id')->value('id');
        $branch2 = DB::table('branches')->insertGetId([
            'name' => 'Cơ sở 2', 'address' => 'Số 1 Đường Demo', 'phone' => '0900000002',
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);

        // --- Nhân sự ---
        $accountant = User::forceCreate([
            'name' => 'Kế Toán Demo', 'email' => 'ketoan@demo.com', 'password' => Hash::make('123456'),
            'role' => 'accountant', 'branch_id' => $mainBranch,
        ]);
        $accountant->assignRole('accountant');

        $teachers = [];
        foreach (['Thầy Minh', 'Cô Lan', 'Cô Hương', 'Thầy Khoa'] as $i => $name) {
            $t = User::forceCreate([
                'name' => $name, 'email' => 'giaovien' . ($i + 1) . '@demo.com', 'password' => Hash::make('123456'),
                'role' => 'member', 'branch_id' => $i < 3 ? $mainBranch : $branch2,
            ]);
            $t->assignRole('member');
            $teachers[] = $t->id;
        }

        // --- Lớp học ---
        $classDefs = [
            ['3NC', 3, 'NC'], ['4NC1', 4, 'NC'], ['5NC1', 5, 'NC'], ['6CC1', 6, 'CC'], ['6CLC', 6, 'CLC'],
            ['7CC', 7, 'CC'], ['8CLC', 8, 'CLC'], ['9NC', 9, 'NC'], ['9CB', 9, 'CB'], ['10A', 10, 'NC'],
        ];
        $classes = [];
        foreach ($classDefs as $i => [$code, $grade, $type]) {
            $d1 = 2 + ($i % 3);
            $start = ['17:30', '18:00', '19:30'][$i % 3];
            $end = ['19:00', '19:30', '21:00'][$i % 3];
            $branch = $i < 8 ? $mainBranch : $branch2;
            $classes[] = [
                'id' => DB::table('classes')->insertGetId([
                    'class_code' => $code, 'name' => 'Lớp ' . $code, 'grade' => $grade,
                    'schedule_note' => "Thứ {$d1} & Thứ " . ($d1 + 3) . ", {$start}-{$end}",
                    'teacher_id' => $teachers[$i % count($teachers)],
                    'price_per_session' => $grade >= 9 ? 150000 : 130000,
                    'schedules' => json_encode([
                        ['dayOfWeek' => $d1, 'startTime' => $start, 'endTime' => $end],
                        ['dayOfWeek' => $d1 + 3, 'startTime' => $start, 'endTime' => $end],
                    ]),
                    'status' => 'active', 'branch_id' => $branch, 'created_at' => $now, 'updated_at' => $now,
                ]),
                'grade' => $grade, 'type' => $type, 'branch' => $branch,
                'price' => $grade >= 9 ? 150000 : 130000,
            ];
        }

        // --- Học sinh (6 em / lớp) ---
        $students = [];
        $n = 1;
        foreach ($classes as $c) {
            for ($k = 0; $k < 6; $k++, $n++) {
                $status = $n % 17 === 0 ? 'reserved' : 'studying';
                $id = DB::table('students')->insertGetId([
                    'student_code' => sprintf('HS%04d', $n),
                    'full_name' => $this->name(),
                    'parent_name' => 'PH ' . $this->name(),
                    'parent_phone' => '09' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'grade' => $c['grade'],
                    'class_type' => $n % 23 === 0 ? 'Vãng lai' : $c['type'],
                    'price_per_session' => $c['price'],
                    'debt' => 0,
                    'start_date' => $now->copy()->subMonths(mt_rand(2, 10))->startOfMonth()->toDateString(),
                    'dob' => Carbon::create($now->year - 5 - $c['grade'], mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                    'academic_status' => ['Giỏi', 'Khá', 'Trung bình'][mt_rand(0, 2)],
                    'status' => $status,
                    'branch_id' => $c['branch'],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('class_student')->insert([
                    'class_id' => $c['id'], 'student_id' => $id, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $students[] = ['id' => $id, 'class' => $c];
            }
        }

        // --- Điểm danh 4 tuần gần nhất ---
        $shifts = ['Ca 1 (17:30 - 19:00)', 'Ca 2 (19:15 - 20:45)'];
        $sessionsPerStudent = [];
        foreach ($students as $s) {
            $sessionsPerStudent[$s['id']] = 0;
        }
        for ($day = 27; $day >= 0; $day--) {
            $date = $now->copy()->subDays($day);
            if ($date->isSunday()) {
                continue;
            }
            foreach ($classes as $ci => $c) {
                if (($date->dayOfWeekIso + $ci) % 3 !== 0) {
                    continue;
                }
                $sessionId = DB::table('class_sessions')->insertGetId([
                    'class_id' => $c['id'], 'session_date' => $date->toDateString(),
                    'created_at' => $date, 'updated_at' => $date,
                ]);
                $checked = $date->copy()->setTime(17, mt_rand(25, 40));
                $count = 0;
                foreach ($students as $s) {
                    if ($s['class']['id'] !== $c['id']) {
                        continue;
                    }
                    $r = mt_rand(1, 100);
                    $status = $r <= 85 ? 'present' : ($r <= 93 ? 'absent_excused' : 'absent_unexcused');
                    DB::table('attendances')->insert([
                        'class_session_id' => $sessionId, 'student_id' => $s['id'], 'class_id' => $c['id'],
                        'shift' => $shifts[$ci % 2], 'created_by' => $teachers[$ci % count($teachers)],
                        'checked_at' => $checked, 'status' => $status,
                        'session_fee' => $status === 'present' ? $c['price'] : 0,
                        'score' => $status === 'present' ? (string) mt_rand(5, 10) : null,
                        'homework_status' => $status === 'present' ? ['done', 'done', 'not_done'][mt_rand(0, 2)] : null,
                        'branch_id' => $c['branch'], 'created_at' => $checked, 'updated_at' => $checked,
                    ]);
                    if ($status === 'present') {
                        $sessionsPerStudent[$s['id']]++;
                        $count++;
                    }
                }
                DB::table('attendance_logs')->insert([
                    'user_id' => $teachers[$ci % count($teachers)], 'class_id' => $c['id'], 'shift' => $shifts[$ci % 2],
                    'action_type' => 'bulk_check_in', 'student_count' => $count,
                    'created_at' => $checked, 'updated_at' => $checked,
                ]);
            }
        }

        // --- Hóa đơn 3 tháng gần nhất + thanh toán ---
        $receipt = 1;
        foreach ($students as $i => $s) {
            $debt = 0;
            for ($m = 2; $m >= 0; $m--) {
                $month = $now->copy()->subMonthsNoOverflow($m);
                $fee = $m === 0
                    ? $sessionsPerStudent[$s['id']] * $s['class']['price']
                    : mt_rand(6, 8) * $s['class']['price'];
                $discount = $i % 11 === 0 ? 100000 : 0;
                $final = $fee + $debt - $discount;
                $status = $m > 0 ? ($i % 9 === 0 ? 'unpaid' : 'paid') : (['paid', 'unpaid', 'partially_paid'][$i % 3]);
                $paidAt = $status === 'unpaid' ? null : $month->copy()->startOfMonth()->addDays(mt_rand(3, 20))->setTime(mt_rand(8, 19), mt_rand(0, 59));
                if ($paidAt && $paidAt->gt($now)) {
                    $paidAt = $now->copy();
                }
                $paidAmount = $status === 'paid' ? $final : ($status === 'partially_paid' ? round($final / 2, -3) : 0);
                $created = $month->copy()->startOfMonth()->addDay();

                DB::table('invoices')->insert([
                    'invoice_code' => sprintf('HD%s%04d', $month->format('ym'), $i + 1),
                    'student_id' => $s['id'],
                    'title' => 'Học phí tháng ' . $month->month . '/' . $month->year,
                    'billing_month' => $month->month, 'billing_year' => $month->year,
                    'current_fee' => $fee, 'previous_debt' => $debt, 'discount_amount' => $discount,
                    'final_amount' => $final, 'amount' => $paidAmount,
                    'status' => $status, 'approval_status' => 'approved',
                    'payment_method' => $paidAt ? ($i % 2 ? 'cash' : 'transfer') : null,
                    'paid_at' => $paidAt, 'branch_id' => $s['class']['branch'],
                    'created_at' => $created, 'updated_at' => $paidAt ?? $created,
                ]);
                if ($paidAmount > 0) {
                    DB::table('payments')->insert([
                        'receipt_code' => sprintf('PT%05d', $receipt++), 'student_id' => $s['id'],
                        'amount_paid' => $paidAmount, 'payment_method' => $i % 2 ? 'cash' : 'transfer',
                        'created_by' => $accountant->id, 'created_at' => $paidAt, 'updated_at' => $paidAt,
                    ]);
                }
                $debt = $final - $paidAmount;
            }
            DB::table('students')->where('id', $s['id'])->update(['debt' => $debt]);
        }

        // --- Kỳ thi thử ---
        $examId = DB::table('exams')->insertGetId([
            'name' => 'Thi thử vào lớp 10 - Lần 1', 'exam_date' => $now->copy()->subDays(10)->toDateString(),
            'scoring_type' => 'multiple_subjects', 'status' => 'completed', 'exam_type' => 'mock_test',
            'branch_id' => $mainBranch, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $shiftId = DB::table('exam_shifts')->insertGetId([
            'exam_id' => $examId, 'name' => 'Ca sáng', 'start_time' => '08:00', 'end_time' => '10:00',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $roomId = DB::table('exam_rooms')->insertGetId([
            'exam_shift_id' => $shiftId, 'name' => 'Phòng 101', 'capacity' => 20,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $candidates = [];
        foreach (array_filter($students, fn ($s) => $s['class']['grade'] === 9) as $s) {
            $scores = ['Toán' => mt_rand(40, 100) / 10, 'Văn' => mt_rand(40, 95) / 10, 'Anh' => mt_rand(40, 100) / 10];
            $candidates[] = ['student' => $s['id'], 'scores' => $scores, 'total' => array_sum($scores)];
        }
        usort($candidates, fn ($a, $b) => $b['total'] <=> $a['total']);
        foreach ($candidates as $rank => $c) {
            DB::table('exam_candidates')->insert([
                'exam_id' => $examId, 'student_id' => $c['student'], 'exam_shift_id' => $shiftId,
                'exam_room_id' => $roomId, 'is_absent' => false,
                'candidate_number' => sprintf('SBD%03d', $rank + 1),
                'scores' => json_encode($c['scores'], JSON_UNESCAPED_UNICODE),
                'total_score' => $c['total'], 'rank' => $rank + 1, 'is_scholarship' => $rank < 2,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('notifications')->insert([
            ['message' => 'Chào mừng bạn đến với Sunny Education (dữ liệu demo)!', 'type' => 'info',
                'is_read' => false, 'user_id' => null, 'created_at' => $now, 'updated_at' => $now],
            ['message' => 'Có hóa đơn học phí tháng này chưa thanh toán.', 'type' => 'warning',
                'is_read' => false, 'user_id' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function name(): string
    {
        return $this->ho[mt_rand(0, count($this->ho) - 1)] . ' '
            . $this->dem[mt_rand(0, count($this->dem) - 1)] . ' '
            . $this->ten[mt_rand(0, count($this->ten) - 1)];
    }
}
