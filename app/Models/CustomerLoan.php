<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLoan extends Model
{
    use HasFactory;

    protected $table = 'customer_loans';

    protected $fillable = [
        'company_id',
        'customer_id',
        'loan_no',
        'loan_amount',
        'installment_amount',
        'no_of_days',
        'start_date',
        'end_date',
        'apply_date',
        'assigned_member_id',
        'details',
        'created_by',
        'applied_by',
        'applied_user_type',
        'status',
        'loan_status',
        'loan_status_message',
        'loan_status_changed_by',
        'loan_status_change_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->select('id','customer_no','name', 'image');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'assigned_member_id')->select('id','member_no','name', 'image');;
    }

    public function document()
    {
        return $this->hasMany(LoanDocument::class, 'loan_id');
    }

    public function loanHistory()
    {
        return $this->hasMany(LoanHistory::class, 'loan_id');
    }
}
