<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Invoice;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Công nợ của học sinh = students.debt + tổng final_amount các hóa đơn còn nợ.
 * Khi thu được tiền, hóa đơn mới gộp toàn bộ nợ cũ; phần thu thiếu chuyển vào students.debt.
 */
class TuitionService
{
    // Học phí phát sinh trong kỳ: từ 21 tháng trước đến 20 tháng này
    public function currentFee(Student $student, int $month, int $year): array
    {
        $startDate = Carbon::create($year, $month, 21)->subMonthNoOverflow()->startOfDay();
        $endDate = Carbon::create($year, $month, 20)->endOfDay();

        $sessions = Attendance::where('student_id', $student->id)
            ->whereIn('status', ['present', 'makeup'])
            ->whereBetween('checked_at', [$startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')])
            ->count();

        $price = $student->price_per_session ?? 130000;

        return ['sessions' => $sessions, 'price' => (float) $price, 'fee' => (float) ($sessions * $price)];
    }

    // Tạo hóa đơn; chỉ tất toán nợ khi đã thực thu (chuyển khoản phải chờ duyệt)
    public function createInvoice(Student $student, array $attributes, string $paymentMethod): Invoice
    {
        $student = Student::lockForUpdate()->findOrFail($student->id);
        $month = Carbon::now()->month;
        $year = Carbon::now()->year;

        $exists = Invoice::withoutGlobalScopes()
            ->where('student_id', $student->id)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->where('approval_status', '!=', 'rejected')
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'student_id' => "Học sinh {$student->full_name} đã có hóa đơn tháng {$month}/{$year}.",
            ]);
        }

        $fee = $this->currentFee($student, $month, $year)['fee'];
        $debt = $this->outstandingDebt($student);
        $isTransfer = $paymentMethod === 'transfer';

        $invoice = Invoice::create($attributes + [
            'student_id'      => $student->id,
            'branch_id'       => $student->branch_id,
            'billing_month'   => $month,
            'billing_year'    => $year,
            'current_fee'     => $fee,
            'previous_debt'   => $debt,
            'final_amount'    => $fee + $debt,
            'payment_method'  => $paymentMethod,
            'status'          => 'unpaid',
            'approval_status' => $isTransfer ? 'pending' : 'approved',
        ]);

        if (!$isTransfer) {
            $this->settle($invoice, $student);
        }

        return $invoice;
    }

    public function settle(Invoice $invoice, ?Student $student = null): void
    {
        $student ??= Student::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->student_id);

        // Hóa đơn tạo theo cách cũ chưa lưu kỳ tính tiền
        if (!$invoice->billing_month) {
            $created = Carbon::parse($invoice->created_at);
            $invoice->billing_month = $created->month;
            $invoice->billing_year = $created->year;
            $invoice->current_fee = $this->currentFee($student, $created->month, $created->year)['fee'];
        }

        $old = Invoice::withoutGlobalScopes()->outstanding()
            ->where('student_id', $student->id)
            ->where('id', '!=', $invoice->id)
            ->lockForUpdate()
            ->get();

        $debtBefore = (float) $student->debt;
        $previousDebt = $debtBefore + (float) $old->sum('final_amount');
        $due = (float) $invoice->current_fee + $previousDebt - (float) $invoice->discount_amount;
        // Số âm nghĩa là thu dư, được trừ vào kỳ sau
        $remaining = round($due - (float) $invoice->amount, 2);

        Invoice::withoutGlobalScopes()->whereIn('id', $old->pluck('id'))->update(['status' => 'paid']);
        $student->update(['debt' => $remaining]);

        $invoice->forceFill([
            'previous_debt'   => $previousDebt,
            'final_amount'    => $due,
            'status'          => $remaining > 0 ? 'partially_paid' : 'paid',
            'approval_status' => 'approved',
            'paid_at'         => Carbon::now(),
            'settlement'      => [
                'invoice_ids' => $old->pluck('id')->all(),
                'debt_change' => $remaining - $debtBefore,
            ],
        ])->save();
    }

    // Hoàn tác: khôi phục nợ như trước khi thu
    public function revert(Invoice $invoice): void
    {
        $settlement = $invoice->settlement;
        if (!$settlement) {
            return;
        }

        $student = Student::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->student_id);

        $hasLater = Invoice::withoutGlobalScopes()
            ->where('student_id', $student->id)
            ->where('id', '!=', $invoice->id)
            ->whereNotNull('settlement')
            ->where(function ($q) use ($invoice) {
                $q->where('paid_at', '>', $invoice->paid_at)
                  ->orWhere(fn ($q) => $q->where('paid_at', $invoice->paid_at)->where('id', '>', $invoice->id));
            })
            ->exists();
        if ($hasLater) {
            throw ValidationException::withMessages([
                'invoice' => 'Học sinh đã có lần thu sau hóa đơn này. Hãy hoàn tác hóa đơn mới hơn trước.',
            ]);
        }

        Invoice::withoutGlobalScopes()->whereIn('id', $settlement['invoice_ids'])->update(['status' => 'unpaid']);
        $student->update(['debt' => (float) $student->debt - $settlement['debt_change']]);
    }

    public function outstandingDebt(Student $student): float
    {
        return (float) $student->debt + (float) Invoice::withoutGlobalScopes()->outstanding()
            ->where('student_id', $student->id)
            ->sum('final_amount');
    }
}
