<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\AuditLog;
use App\Services\TuitionService;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(private TuitionService $tuition)
    {
    }

    private function getSettings()
    {
        $settingsFile = 'settings.json';
        if (!Storage::exists($settingsFile)) {
            return [
                'bank_id' => 'TCB',
                'account_no' => '19036420745015',
                'account_name' => 'SUNNY EDUCATION'
            ];
        }
        return json_decode(Storage::get($settingsFile), true);
    }
    public function index()
    {
        $invoices = Invoice::with('student')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($invoices);
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id'     => 'required|exists:students,id',
            'title'          => 'required|string',
            'amount'         => 'required|numeric|min:1000',
            'payment_method' => 'required|string',
        ]);

        $student = Student::findOrFail($request->student_id);

        // Sinh mã hóa đơn tự động chuẩn POS: HD + Thời gian + ID ngẫu nhiên
        $invoiceCode = 'HD' . date('YmdHis') . rand(10, 99);

        $invoice = DB::transaction(fn () => $this->tuition->createInvoice($student, [
            'invoice_code' => $invoiceCode,
            'title'        => $request->title,
            'amount'       => $request->amount,
        ], $request->payment_method));

        AuditLog::create([
            'user_id' => $request->user()->id,
            'student_id' => $student->id,
            'action' => 'INVOICE_CREATE',
            'new_data' => json_encode(['amount' => $invoice->amount, 'method' => $invoice->payment_method]),
            'reason' => 'Tạo hóa đơn thu tiền',
        ]);

        // Tạo đường dẫn VietQR động nếu chọn hình thức chuyển khoản
        $qrUrl = '';
        if ($request->payment_method === 'transfer') {
            $settings = $this->getSettings();
            $bankId  = $settings['bank_id'];
            $account = $settings['account_no'];
            $name    = urlencode($settings['account_name']);
            $addInfo = urlencode("HP " . $student->student_code);
            $qrUrl   = "https://img.vietqr.io/image/{$bankId}-{$account}-compact2.png?amount={$request->amount}&addInfo={$addInfo}&accountName={$name}";
        }

        $invoice->load('student');

        if ($request->payment_method === 'transfer') {
            $this->sendTransferNotificationEmail($invoice);
        }

        return response()->json([
            'message' => 'Tạo hóa đơn thành công!',
            'invoice' => $invoice,
            'qr_url'  => $qrUrl,
        ]);
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'invoices' => 'required|array|min:1',
            'invoices.*.student_id' => 'required|exists:students,id',
            'invoices.*.title' => 'required|string',
            'invoices.*.amount' => 'required|numeric|min:1000',
            'payment_method' => 'required|string',
        ]);

        $createdInvoices = [];
        $totalAmount = 0;
        $studentCodes = [];

        DB::beginTransaction();
        try {

            foreach ($request->invoices as $invData) {
                $student = Student::findOrFail($invData['student_id']);
                $invoiceCode = 'HD' . date('YmdHis') . rand(10, 99) . $student->id;

                $invoice = $this->tuition->createInvoice($student, [
                    'invoice_code' => $invoiceCode,
                    'title'        => $invData['title'],
                    'amount'       => $invData['amount'],
                ], $request->payment_method);

                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'student_id' => $student->id,
                    'action' => 'INVOICE_CREATE',
                    'new_data' => json_encode(['amount' => $invoice->amount, 'method' => $invoice->payment_method]),
                    'reason' => 'Tạo hóa đơn thu tiền gộp',
                ]);

                $invoice->load('student');
                $createdInvoices[] = $invoice;
                $totalAmount += $invData['amount'];
                $studentCodes[] = $student->student_code;
            }
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi tạo hóa đơn gộp: ' . $e->getMessage()], 500);
        }

        if ($request->payment_method === 'transfer') {
            $this->sendTransferNotificationEmailBulk($createdInvoices, $totalAmount);
        }

        $qrUrl = '';
        if ($request->payment_method === 'transfer') {
            $settings = $this->getSettings();
            $bankId  = $settings['bank_id'];
            $account = $settings['account_no'];
            $name    = urlencode($settings['account_name']);
            // Limit addInfo length if too many students
            $addInfoStr = "HP " . implode(" ", $studentCodes);
            if (strlen($addInfoStr) > 50) {
                $addInfoStr = substr($addInfoStr, 0, 47) . "...";
            }
            $addInfo = urlencode($addInfoStr);
            $qrUrl   = "https://img.vietqr.io/image/{$bankId}-{$account}-compact2.png?amount={$totalAmount}&addInfo={$addInfo}&accountName={$name}";
        }

        return response()->json([
            'message' => 'Tạo hóa đơn gộp thành công!',
            'invoices' => $createdInvoices,
            'qr_url'  => $qrUrl,
            'total_amount' => $totalAmount
        ]);
    }

    // Tính học phí cho nhiều học sinh trong một lần gọi (tránh vượt giới hạn số request/phút)
    public function getBulkBillingInfo(Request $request)
    {
        $data = $request->validate([
            'student_ids'   => 'required|array|min:1|max:1000',
            'student_ids.*' => 'integer|distinct',
            'month'         => 'nullable|integer|between:1,12',
            'year'          => 'nullable|integer',
        ]);

        $items = [];
        foreach ($data['student_ids'] as $id) {
            $response = $this->getBillingInfo($request, $id);
            if ($response->getStatusCode() !== 200) {
                return $response;
            }
            $items[] = $response->getData(true);
        }

        return response()->json($items);
    }

    public function getBillingInfo(Request $request, $id)
    {
        try {
            $student = Student::findOrFail($id);
            
            $currentMonth = $request->input('month', Carbon::now()->month);
            $currentYear = $request->input('year', Carbon::now()->year);
            
            
            // Bước 1: Nợ cũ
            $unpaidInvoices = Invoice::withoutGlobalScopes()->outstanding()
                ->where('student_id', $id)
                ->get();
                
            $previousDebt = $student->debt + $unpaidInvoices->sum('final_amount');
            
            $lastMonthDebt = 0;
            $yearlyDebt = $student->debt ?? 0;
            
            $lastMonth = $currentMonth - 1;
            $lastMonthYear = $currentYear;
            if ($lastMonth == 0) {
                $lastMonth = 12;
                $lastMonthYear = $currentYear - 1;
            }

            foreach ($unpaidInvoices as $inv) {
                if ($inv->billing_month == $lastMonth && $inv->billing_year == $lastMonthYear) {
                    $lastMonthDebt += $inv->final_amount;
                } else {
                    $yearlyDebt += $inv->final_amount;
                }
            }
            
            $debtDetails = $unpaidInvoices->map(function($inv) {
                return [
                    'month' => $inv->billing_month,
                    'year' => $inv->billing_year,
                    'unpaid_amount' => $inv->final_amount,
                    'invoice_code' => $inv->invoice_code
                ];
            });

            if ($student->debt > 0) {
                $debtDetails->push([
                    'month' => null,
                    'year' => null,
                    'unpaid_amount' => $student->debt,
                    'invoice_code' => 'Nợ đầu kỳ'
                ]);
            }

            // Bước 2: Tiền tháng này
            $current = $this->tuition->currentFee($student, (int) $currentMonth, (int) $currentYear);
            $attendedSessions = $current['sessions'];
            $pricePerSession = $current['price'];
            $currentFee = $current['fee'];

            // Bước 3: Tổng
            $finalAmount = $previousDebt + $currentFee;
            
            return response()->json([
                'student_id' => $student->id,
                'current_month' => (int)$currentMonth,
                'current_year' => (int)$currentYear,
                'attended_sessions' => $attendedSessions,
                'price_per_session' => (float)$pricePerSession,
                'current_fee' => (float)$currentFee,
                'previous_debt' => (float)$previousDebt,
                'last_month_debt' => (float)$lastMonthDebt,
                'yearly_debt' => (float)$yearlyDebt,
                'final_amount' => (float)$finalAmount,
                'has_debt' => $previousDebt > 0,
                'debt_details' => $debtDetails
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        DB::transaction(function () use ($request, $id) {
            $invoice = Invoice::lockForUpdate()->findOrFail($id);

            // Hóa đơn đã được một lần thu sau tất toán thì không được xóa riêng
            $settledByLater = Invoice::withoutGlobalScopes()
                ->where('student_id', $invoice->student_id)
                ->whereNotNull('settlement')
                ->get()
                ->contains(fn ($other) => in_array($invoice->id, $other->settlement['invoice_ids']));
            if ($settledByLater) {
                throw ValidationException::withMessages([
                    'invoice' => 'Hóa đơn này đã được gộp vào một lần thu sau. Hãy hoàn tác hóa đơn mới hơn trước.',
                ]);
            }

            $this->tuition->revert($invoice);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'student_id' => $invoice->student_id,
                'action' => 'INVOICE_DELETE',
                'old_data' => json_encode(['amount' => $invoice->amount, 'status' => $invoice->status]),
                'reason' => 'Hoàn tác thu tiền',
            ]);

            $invoice->delete();
        });

        return response()->json(['message' => 'Hóa đơn đã được xóa (hoàn tác) thành công.']);
    }

    public function approve(Request $request, $id)
    {
        DB::transaction(function () use ($request, $id) {
            $invoice = Invoice::lockForUpdate()->findOrFail($id);
            if ($invoice->approval_status !== 'pending') {
                throw ValidationException::withMessages(['invoice' => 'Chỉ duyệt được hóa đơn đang chờ duyệt.']);
            }

            $this->tuition->settle($invoice);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'student_id' => $invoice->student_id,
                'action' => 'INVOICE_APPROVE',
                'new_data' => json_encode(['status' => 'approved']),
                'reason' => 'Duyệt hóa đơn chuyển khoản',
            ]);
        });

        return response()->json(['message' => 'Hóa đơn đã được duyệt.']);
    }

    public function reject($id)
    {
        $invoice = Invoice::findOrFail($id);
        if ($invoice->approval_status !== 'pending') {
            return response()->json(['message' => 'Chỉ từ chối được hóa đơn đang chờ duyệt.'], 422);
        }
        $invoice->approval_status = 'rejected';
        $invoice->save();
        return response()->json(['message' => 'Hóa đơn đã bị từ chối.']);
    }

    private function getNotifyEmails()
    {
        $settings = $this->getSettings();
        $emailsStr = $settings['notify_emails'] ?? 'admin@gmail.com';
        
        if (is_array($emailsStr)) {
            return $emailsStr;
        } else {
            return array_map('trim', explode(',', $emailsStr));
        }
    }

    private function sendTransferNotificationEmail($invoice)
    {
        try {
            $emails = $this->getNotifyEmails();
            if (empty($emails)) return;

            $student = $invoice->student;
            $subject = 'Thông báo: Có một khoản chuyển khoản đang chờ duyệt (' . $invoice->invoice_code . ')';
            $body = "Chào Quản trị viên/Kế toán,\n\n"
                  . "Hệ thống vừa ghi nhận một giao dịch chuyển khoản đang chờ duyệt.\n\n"
                  . "- Mã hóa đơn: {$invoice->invoice_code}\n"
                  . "- Học sinh: {$student->full_name} (" . ($student->student_code ?? 'N/A') . ")\n"
                  . "- Số tiền: " . number_format($invoice->amount) . " VNĐ\n"
                  . "- Nội dung: {$invoice->title}\n\n"
                  . "Vui lòng kiểm tra tài khoản ngân hàng và truy cập hệ thống để duyệt/từ chối giao dịch này.\n\n"
                  . "Trân trọng,\nHệ thống Sunny Education";

            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($emails, $subject) {
                $message->to($emails)->subject($subject);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi gửi email thông báo: ' . $e->getMessage());
        }
    }

    private function sendTransferNotificationEmailBulk($invoices, $totalAmount)
    {
        try {
            $emails = $this->getNotifyEmails();
            if (empty($emails) || empty($invoices)) return;

            $subject = 'Thông báo: Có giao dịch chuyển khoản GỘP đang chờ duyệt';
            $body = "Chào Quản trị viên/Kế toán,\n\n"
                  . "Hệ thống vừa ghi nhận giao dịch chuyển khoản GỘP đang chờ duyệt.\n\n"
                  . "- Tổng số tiền: " . number_format($totalAmount) . " VNĐ\n"
                  . "- Số lượng hóa đơn: " . count($invoices) . "\n\n"
                  . "Chi tiết các hóa đơn:\n";

            foreach ($invoices as $inv) {
                $studentName = $inv->student ? $inv->student->full_name : 'N/A';
                $body .= " + {$inv->invoice_code} - {$studentName}: " . number_format($inv->amount) . " VNĐ - {$inv->title}\n";
            }

            $body .= "\nVui lòng kiểm tra tài khoản ngân hàng và truy cập hệ thống để duyệt/từ chối.\n\n"
                  . "Trân trọng,\nHệ thống Sunny Education";

            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($emails, $subject) {
                $message->to($emails)->subject($subject);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi gửi email thông báo gộp: ' . $e->getMessage());
        }
    }
}