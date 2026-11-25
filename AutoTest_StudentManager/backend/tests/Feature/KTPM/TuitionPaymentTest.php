<?php

namespace Tests\Feature\KTPM;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Kiểm thử tự động API chức năng THANH TOÁN HỌC PHÍ (/api/invoices, /api/students/{id}/billing-info).
 */
class TuitionPaymentTest extends KtpmTestCase
{
    /**
     * Cố định "hôm nay" trong kỳ tính phí tháng 11 (21/10 - 20/11) để kết quả
     * không phụ thuộc ngày chạy test (Laravel tự bỏ cố định sau mỗi test).
     */
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 10:00:00');
    }

    private function invoicePayload($studentId, array $over = []): array
    {
        return array_merge([
            'student_id' => $studentId,
            'title' => 'Học phí Tháng 11/2026',
            'amount' => 390000,
            'payment_method' => 'cash',
        ], $over);
    }

    // ===================== TÍNH HỌC PHÍ =====================

    #[TestDox('TTHP_API_01: Tính học phí: 3 buổi có mặt x 130.000 = 390.000đ')]
    public function test_api_01_tinh_hoc_phi(): void
    {
        $s = $this->makeStudent();
        $this->addAttendance($s, 3);
        $res = $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info")->assertOk();
        $this->assertEquals(3, $res->json('attended_sessions'));
        $this->assertEquals(390000, $res->json('current_fee'));
        $this->assertEquals(390000, $res->json('final_amount'));
    }

    #[TestDox('TTHP_API_02: Học sinh có đơn giá riêng 150.000đ/buổi, 2 buổi -> 300.000đ')]
    public function test_api_02_don_gia_rieng(): void
    {
        $s = $this->makeStudent(['price_per_session' => 150000]);
        $this->addAttendance($s, 2);
        $this->assertEquals(300000, $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info")->json('current_fee'));
    }

    #[TestDox('TTHP_API_03: Học sinh không đi buổi nào trong kỳ -> học phí 0đ')]
    public function test_api_03_khong_di_hoc(): void
    {
        $s = $this->makeStudent();
        $res = $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info")->assertOk();
        $this->assertEquals(0, $res->json('current_fee'));
        $this->assertFalse($res->json('has_debt'));
    }

    #[TestDox('TTHP_API_04: Buổi vắng (absent) không bị tính tiền, buổi học bù (makeup) có tính tiền')]
    public function test_api_04_vang_va_hoc_bu(): void
    {
        $s = $this->makeStudent();
        foreach (['present', 'absent_unexcused', 'absent_excused', 'makeup'] as $st) {
            DB::table('attendances')->insert(['student_id' => $s->id, 'status' => $st, 'session_fee' => 130000, 'checked_at' => now()->setTime(18, 0), 'branch_id' => $s->branch_id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertEquals(260000, $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info")->json('current_fee'));
    }

    #[TestDox('TTHP_API_05: Kỳ tính phí từ ngày 21 tháng trước đến ngày 20: buổi ngày 21/11 tính sang kỳ tháng 12')]
    public function test_api_05_ky_tinh_phi(): void
    {
        $s = $this->makeStudent();
        $this->addAttendance($s, 1, '2026-11-20');
        $this->addAttendance($s, 1, '2026-11-21');
        $nov = $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info?month=11&year=2026")->json('attended_sessions');
        $dec = $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info?month=12&year=2026")->json('attended_sessions');
        $this->assertEquals([1, 1], [$nov, $dec]);
    }

    #[TestDox('TTHP_API_06: Kỳ tháng 1 lấy buổi học từ 21/12 năm trước (chuyển năm)')]
    public function test_api_06_ky_thang_1(): void
    {
        $s = $this->makeStudent();
        $this->addAttendance($s, 2, '2026-12-25');
        $this->assertEquals(2, $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info?month=1&year=2027")->json('attended_sessions'));
    }

    #[TestDox('TTHP_API_07: Tổng phải thu = học phí kỳ này + dư nợ đầu kỳ (390.000 + 260.000 = 650.000)')]
    public function test_api_07_cong_no_cu(): void
    {
        $s = $this->makeStudent(['debt' => 260000]);
        $this->addAttendance($s, 3);
        $res = $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info")->assertOk();
        $this->assertEquals(260000, $res->json('previous_debt'));
        $this->assertEquals(650000, $res->json('final_amount'));
        $this->assertTrue($res->json('has_debt'));
    }

    #[TestDox('TTHP_API_08: Sau khi đã thu đủ (hóa đơn tiền mặt), dư nợ đầu kỳ được trừ - không bị tính lại')]
    public function test_api_08_tru_no_sau_khi_thu(): void
    {
        $s = $this->makeStudent(['debt' => 260000]);
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['amount' => 260000]))->assertOk();
        $this->assertEquals(0, $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info")->json('previous_debt'));
    }

    #[TestDox('TTHP_API_09: Xem học phí của học sinh không tồn tại -> 404')]
    public function test_api_09_hs_khong_ton_tai(): void
    {
        $this->asUser($this->accountant)->getJson('/api/students/99999/billing-info')->assertStatus(404);
    }

    #[TestDox('TTHP_API_10: Tham số tháng không hợp lệ (month=13) -> 422')]
    public function test_api_10_thang_khong_hop_le(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->getJson("/api/students/{$s->id}/billing-info?month=13&year=2026")->assertStatus(422);
    }

    // ===================== TẠO HÓA ĐƠN =====================

    #[TestDox('TTHP_API_11: Thu tiền mặt hợp lệ -> hóa đơn status=paid, approval=approved, có paid_at')]
    public function test_api_11_thu_tien_mat(): void
    {
        $s = $this->makeStudent();
        $res = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->assertOk();
        $this->assertEquals('paid', $res->json('invoice.status'));
        $this->assertEquals('approved', $res->json('invoice.approval_status'));
        $this->assertNotNull($res->json('invoice.paid_at'));
        $this->assertSame('', $res->json('qr_url'));
    }

    #[TestDox('TTHP_API_12: Chuyển khoản -> hóa đơn unpaid + chờ duyệt (pending), chưa có paid_at')]
    public function test_api_12_chuyen_khoan(): void
    {
        $s = $this->makeStudent();
        $res = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['payment_method' => 'transfer']))->assertOk();
        $this->assertEquals('unpaid', $res->json('invoice.status'));
        $this->assertEquals('pending', $res->json('invoice.approval_status'));
        $this->assertNull($res->json('invoice.paid_at'));
    }

    #[TestDox('TTHP_API_13: Chuyển khoản -> sinh link VietQR có đúng số tiền và nội dung "HP <mã HS>"')]
    public function test_api_13_vietqr(): void
    {
        $s = $this->makeStudent();
        $qr = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['payment_method' => 'transfer']))->json('qr_url');
        $this->assertStringStartsWith('https://img.vietqr.io/image/', $qr);
        $this->assertStringContainsString('amount=390000', $qr);
        $this->assertStringContainsString(urlencode('HP ' . $s->student_code), $qr);
    }

    #[TestDox('TTHP_API_14: Mã hóa đơn tự sinh dạng HD + yyyyMMddHHmmss + 2 số')]
    public function test_api_14_ma_hoa_don(): void
    {
        $s = $this->makeStudent();
        $code = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->json('invoice.invoice_code');
        $this->assertMatchesRegularExpression('/^HD\d{14}\d{2}$/', $code);
    }

    #[TestDox('TTHP_API_15: Tạo hóa đơn ghi nhật ký kiểm toán INVOICE_CREATE')]
    public function test_api_15_audit_log(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'INVOICE_CREATE', 'student_id' => $s->id, 'user_id' => $this->accountant->id]);
    }

    #[TestDox('TTHP_API_16: Số tiền = 1.000đ (biên dưới) -> tạo thành công')]
    public function test_api_16_bien_duoi(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['amount' => 1000]))->assertOk();
    }

    #[TestDox('TTHP_API_17: Số tiền = 999đ (dưới biên) -> 422')]
    public function test_api_17_duoi_bien(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['amount' => 999]))->assertStatus(422);
    }

    #[TestDox('TTHP_API_18: Số tiền = 0đ -> 422')]
    public function test_api_18_bang_0(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['amount' => 0]))->assertStatus(422);
    }

    #[TestDox('TTHP_API_19: Số tiền âm (-390000) -> 422')]
    public function test_api_19_so_am(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['amount' => -390000]))->assertStatus(422);
    }

    #[TestDox('TTHP_API_20: Số tiền là chữ ("ba tram nghin") -> 422')]
    public function test_api_20_so_tien_chu(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['amount' => 'ba tram nghin']))->assertStatus(422);
    }

    #[TestDox('TTHP_API_21: Số tiền có phần thập phân (390000.5) -> 422 (VNĐ không có xu lẻ)')]
    public function test_api_21_so_thap_phan(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['amount' => 390000.5]))->assertStatus(422);
    }

    #[TestDox('TTHP_API_22: Số tiền quá lớn (100.000.000.000đ, vượt decimal(12,2)) -> 422')]
    public function test_api_22_so_qua_lon(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['amount' => 100000000000]))->assertStatus(422);
    }

    #[TestDox('TTHP_API_23: Bỏ trống số tiền -> 422')]
    public function test_api_23_bo_trong_so_tien(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['amount' => '']))->assertStatus(422);
    }

    #[TestDox('TTHP_API_24: Bỏ trống nội dung thu (title) -> 422')]
    public function test_api_24_bo_trong_noi_dung(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['title' => '']))->assertStatus(422);
    }

    #[TestDox('TTHP_API_25: Học sinh không tồn tại (student_id=99999) -> 422')]
    public function test_api_25_hs_khong_ton_tai(): void
    {
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload(99999))->assertStatus(422);
    }

    #[TestDox('TTHP_API_26: Bỏ trống hình thức thanh toán -> 422')]
    public function test_api_26_bo_trong_hinh_thuc(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['payment_method' => '']))->assertStatus(422);
    }

    #[TestDox('TTHP_API_27: Hình thức thanh toán ngoài danh sách (bitcoin) -> 422')]
    public function test_api_27_hinh_thuc_khong_hop_le(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['payment_method' => 'bitcoin']))->assertStatus(422);
    }

    #[TestDox('TTHP_API_28: Hóa đơn ghi đủ thông tin tài chính (final_amount = số tiền thu, tháng/năm kỳ thu)')]
    public function test_api_28_luu_du_thong_tin(): void
    {
        $s = $this->makeStudent();
        $id = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->json('invoice.id');
        $inv = Invoice::withoutGlobalScopes()->find($id);
        $this->assertEquals(390000, (float) $inv->final_amount);
        $this->assertNotNull($inv->billing_month);
        $this->assertNotNull($inv->billing_year);
    }

    #[TestDox('TTHP_API_29: Hóa đơn được gán đúng cơ sở (branch_id) của học sinh')]
    public function test_api_29_gan_co_so(): void
    {
        $s = $this->makeStudent();
        $id = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->json('invoice.id');
        $this->assertEquals($this->branch->id, Invoice::withoutGlobalScopes()->find($id)->branch_id);
    }

    #[TestDox('TTHP_API_30: Thu 2 lần cho cùng học sinh cùng kỳ -> lần 2 bị chặn (chống thu trùng)')]
    public function test_api_30_thu_trung(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->assertOk();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->assertStatus(422);
    }

    #[TestDox('TTHP_API_31: Thu cho học sinh thuộc cơ sở khác -> bị từ chối (404/422)')]
    public function test_api_31_thu_hs_co_so_khac(): void
    {
        $s = $this->makeStudent(['branch_id' => $this->branch2->id]);
        $status = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->status();
        $this->assertContains($status, [403, 404, 422]);
        $this->assertDatabaseMissing('invoices', ['student_id' => $s->id]);
    }

    // ===================== THU GỘP =====================

    #[TestDox('TTHP_API_32: Thu gộp 3 học sinh tiền mặt -> tạo 3 hóa đơn, total_amount = tổng')]
    public function test_api_32_thu_gop(): void
    {
        $a = $this->makeStudent(); $b = $this->makeStudent(); $c = $this->makeStudent();
        $res = $this->asUser($this->accountant)->postJson('/api/invoices/bulk', [
            'payment_method' => 'cash',
            'invoices' => [
                ['student_id' => $a->id, 'title' => 'HP', 'amount' => 390000],
                ['student_id' => $b->id, 'title' => 'HP', 'amount' => 260000],
                ['student_id' => $c->id, 'title' => 'HP', 'amount' => 130000],
            ],
        ])->assertOk();
        $this->assertCount(3, $res->json('invoices'));
        $this->assertEquals(780000, $res->json('total_amount'));
    }

    #[TestDox('TTHP_API_33: Thu gộp chuyển khoản -> 1 mã VietQR với tổng tiền, nội dung chứa các mã HS')]
    public function test_api_33_thu_gop_qr(): void
    {
        $a = $this->makeStudent(); $b = $this->makeStudent();
        $res = $this->asUser($this->accountant)->postJson('/api/invoices/bulk', [
            'payment_method' => 'transfer',
            'invoices' => [
                ['student_id' => $a->id, 'title' => 'HP', 'amount' => 390000],
                ['student_id' => $b->id, 'title' => 'HP', 'amount' => 260000],
            ],
        ])->assertOk();
        $this->assertStringContainsString('amount=650000', $res->json('qr_url'));
        $this->assertStringContainsString(urlencode('HP ' . $a->student_code . ' ' . $b->student_code), $res->json('qr_url'));
    }

    #[TestDox('TTHP_API_34: Thu gộp danh sách rỗng -> 422')]
    public function test_api_34_thu_gop_rong(): void
    {
        $this->asUser($this->accountant)->postJson('/api/invoices/bulk', ['payment_method' => 'cash', 'invoices' => []])->assertStatus(422);
    }

    #[TestDox('TTHP_API_35: Thu gộp có 1 dòng số tiền 500đ -> 422, không tạo hóa đơn nào (toàn vẹn giao dịch)')]
    public function test_api_35_thu_gop_mot_dong_loi(): void
    {
        $a = $this->makeStudent(); $b = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices/bulk', [
            'payment_method' => 'cash',
            'invoices' => [
                ['student_id' => $a->id, 'title' => 'HP', 'amount' => 390000],
                ['student_id' => $b->id, 'title' => 'HP', 'amount' => 500],
            ],
        ])->assertStatus(422);
        $this->assertDatabaseCount('invoices', 0);
    }

    #[TestDox('TTHP_API_36: Nội dung QR thu gộp nhiều học sinh bị cắt ≤ 50 ký tự')]
    public function test_api_36_cat_noi_dung_qr(): void
    {
        $items = [];
        for ($i = 0; $i < 6; $i++) {
            $items[] = ['student_id' => $this->makeStudent()->id, 'title' => 'HP', 'amount' => 130000];
        }
        $qr = $this->asUser($this->accountant)->postJson('/api/invoices/bulk', ['payment_method' => 'transfer', 'invoices' => $items])->json('qr_url');
        parse_str(parse_url($qr, PHP_URL_QUERY), $q);
        $this->assertLessThanOrEqual(50, strlen($q['addInfo']));
    }

    // ===================== DUYỆT / TỪ CHỐI / HOÀN TÁC =====================

    private function makePendingInvoice(): Invoice
    {
        $s = $this->makeStudent();
        $id = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['payment_method' => 'transfer']))->json('invoice.id');
        return Invoice::withoutGlobalScopes()->find($id);
    }

    #[TestDox('TTHP_API_37: Duyệt hóa đơn chuyển khoản -> status=paid, approval=approved, có paid_at')]
    public function test_api_37_duyet(): void
    {
        $inv = $this->makePendingInvoice();
        $this->asUser($this->accountant)->putJson("/api/invoices/{$inv->id}/approve")->assertOk();
        $inv->refresh();
        $this->assertEquals(['paid', 'approved'], [$inv->status, $inv->approval_status]);
        $this->assertNotNull($inv->paid_at);
    }

    #[TestDox('TTHP_API_38: Duyệt hóa đơn ghi nhật ký INVOICE_APPROVE')]
    public function test_api_38_duyet_audit(): void
    {
        $inv = $this->makePendingInvoice();
        $this->asUser($this->accountant)->putJson("/api/invoices/{$inv->id}/approve")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'INVOICE_APPROVE', 'student_id' => $inv->student_id]);
    }

    #[TestDox('TTHP_API_39: Từ chối hóa đơn chuyển khoản -> approval_status = rejected')]
    public function test_api_39_tu_choi(): void
    {
        $inv = $this->makePendingInvoice();
        $this->asUser($this->accountant)->putJson("/api/invoices/{$inv->id}/reject")->assertOk();
        $this->assertEquals('rejected', $inv->fresh()->approval_status);
    }

    #[TestDox('TTHP_API_40: Từ chối hóa đơn có ghi nhật ký kiểm toán (INVOICE_REJECT)')]
    public function test_api_40_tu_choi_audit(): void
    {
        $inv = $this->makePendingInvoice();
        $this->asUser($this->accountant)->putJson("/api/invoices/{$inv->id}/reject")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'INVOICE_REJECT', 'student_id' => $inv->student_id]);
    }

    #[TestDox('TTHP_API_41: Không cho duyệt lại hóa đơn ĐÃ BỊ TỪ CHỐI -> 422')]
    public function test_api_41_duyet_hoa_don_da_tu_choi(): void
    {
        $inv = $this->makePendingInvoice();
        $this->asUser($this->accountant)->putJson("/api/invoices/{$inv->id}/reject")->assertOk();
        $this->asUser($this->accountant)->putJson("/api/invoices/{$inv->id}/approve")->assertStatus(422);
        $this->assertNotEquals('paid', $inv->fresh()->status);
    }

    #[TestDox('TTHP_API_42: Không cho từ chối hóa đơn tiền mặt ĐÃ THU (paid) -> 422')]
    public function test_api_42_tu_choi_hoa_don_da_thu(): void
    {
        $s = $this->makeStudent();
        $id = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->json('invoice.id');
        $this->asUser($this->accountant)->putJson("/api/invoices/{$id}/reject")->assertStatus(422);
    }

    #[TestDox('TTHP_API_43: Duyệt 2 lần cùng một hóa đơn -> lần 2 báo đã duyệt, không đổi paid_at')]
    public function test_api_43_duyet_hai_lan(): void
    {
        $inv = $this->makePendingInvoice();
        $this->asUser($this->accountant)->putJson("/api/invoices/{$inv->id}/approve")->assertOk();
        $paidAt = $inv->fresh()->paid_at;
        $this->travel(5)->minutes();
        $this->asUser($this->accountant)->putJson("/api/invoices/{$inv->id}/approve")->assertStatus(422);
        $this->assertEquals((string) $paidAt, (string) $inv->fresh()->paid_at);
    }

    #[TestDox('TTHP_API_44: Duyệt hóa đơn không tồn tại -> 404')]
    public function test_api_44_duyet_khong_ton_tai(): void
    {
        $this->asUser($this->accountant)->putJson('/api/invoices/99999/approve')->assertStatus(404);
    }

    #[TestDox('TTHP_API_45: Hoàn tác (xóa) hóa đơn -> hóa đơn bị xóa, ghi nhật ký INVOICE_DELETE')]
    public function test_api_45_hoan_tac(): void
    {
        $s = $this->makeStudent();
        $id = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id))->json('invoice.id');
        $this->asUser($this->accountant)->deleteJson("/api/invoices/{$id}")->assertOk();
        $this->assertDatabaseMissing('invoices', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'INVOICE_DELETE', 'student_id' => $s->id]);
    }

    #[TestDox('TTHP_API_46: Hoàn tác hóa đơn không tồn tại -> 404')]
    public function test_api_46_hoan_tac_khong_ton_tai(): void
    {
        $this->asUser($this->accountant)->deleteJson('/api/invoices/99999')->assertStatus(404);
    }

    #[TestDox('TTHP_API_47: Lịch sử thu tiền sắp xếp mới nhất lên đầu, kèm thông tin học sinh')]
    public function test_api_47_lich_su(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['title' => 'Lần 1']));
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($this->makeStudent()->id, ['title' => 'Lần 2']));
        $res = $this->asUser($this->accountant)->getJson('/api/invoices')->assertOk();
        $this->assertEquals('Lần 2', $res->json('0.title'));
        $this->assertArrayHasKey('student', $res->json('0'));
    }

    #[TestDox('TTHP_API_48: Kế toán cơ sở 1 không xem được hóa đơn của cơ sở 2')]
    public function test_api_48_phan_tach_hoa_don(): void
    {
        $s2 = $this->makeStudent(['branch_id' => $this->branch2->id]);
        DB::table('invoices')->insert(['invoice_code' => 'HDCS2', 'student_id' => $s2->id, 'title' => 'HP', 'amount' => 1000, 'status' => 'paid', 'approval_status' => 'approved', 'payment_method' => 'cash', 'branch_id' => $this->branch2->id, 'created_at' => now(), 'updated_at' => now()]);
        $codes = collect($this->asUser($this->accountant)->getJson('/api/invoices')->json())->pluck('invoice_code');
        $this->assertFalse($codes->contains('HDCS2'));
    }

    // ===================== PHÂN QUYỀN =====================

    #[TestDox('TTHP_API_49: Giáo viên (member, không có view_finance) xem hóa đơn -> 403')]
    public function test_api_49_member_xem_hoa_don(): void
    {
        $this->asUser($this->member)->getJson('/api/invoices')->assertStatus(403);
    }

    #[TestDox('TTHP_API_50: Giáo viên tạo hóa đơn -> 403')]
    public function test_api_50_member_tao_hoa_don(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->member)->postJson('/api/invoices', $this->invoicePayload($s->id))->assertStatus(403);
    }

    #[TestDox('TTHP_API_51: Giáo viên tự duyệt hóa đơn chuyển khoản -> 403')]
    public function test_api_51_member_duyet(): void
    {
        $inv = $this->makePendingInvoice();
        $this->asUser($this->member)->putJson("/api/invoices/{$inv->id}/approve")->assertStatus(403);
    }

    #[TestDox('TTHP_API_52: Chưa đăng nhập tạo hóa đơn -> 401')]
    public function test_api_52_chua_dang_nhap(): void
    {
        $this->postJson('/api/invoices', ['student_id' => 1])->assertStatus(401);
    }

    #[TestDox('TTHP_API_53: Cập nhật cấu hình ngân hàng (số TK, tên TK) -> QR mới dùng cấu hình mới')]
    public function test_api_53_cau_hinh_ngan_hang(): void
    {
        \Illuminate\Support\Facades\Storage::fake();
        $this->asUser($this->admin)->putJson('/api/settings', ['bank_id' => 'VCB', 'account_no' => '0011223344', 'account_name' => 'TRUNG TAM TEST'])->assertOk();
        $s = $this->makeStudent();
        $qr = $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['payment_method' => 'transfer']))->json('qr_url');
        $this->assertStringContainsString('VCB-0011223344', $qr);
    }

    #[TestDox('TTHP_API_54: Chuyển khoản -> gửi email thông báo chờ duyệt tới email cấu hình')]
    public function test_api_54_email_thong_bao(): void
    {
        $s = $this->makeStudent();
        $this->asUser($this->accountant)->postJson('/api/invoices', $this->invoicePayload($s->id, ['payment_method' => 'transfer']))->assertOk();
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('chờ duyệt', $messages[0]->getOriginalMessage()->getSubject());
    }
}
