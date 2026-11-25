<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBranchScope;

class Invoice extends Model
{
    use HasBranchScope;

    protected $fillable = [
        'invoice_code',
        'student_id',
        'title',
        'billing_month',
        'billing_year',
        'current_fee',
        'previous_debt',
        'discount_amount',
        'final_amount',
        'amount',
        'payment_method',
        'status',
        'approval_status',
        'paid_at',
        'branch_id'
    ];

    protected $casts = [
        'settlement' => 'array',
    ];

    protected $hidden = ['settlement'];

    // Hóa đơn còn nợ: không tính hóa đơn chờ duyệt hoặc bị từ chối
    public function scopeOutstanding($query)
    {
        return $query->where('status', 'unpaid')->where('approval_status', 'approved');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}