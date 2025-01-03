<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberFinanceHistory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'member_finance_history';

    protected $fillable = [
        'member_finance_id',
        'amount',
        'amount_by',
        'amount_by_id',
        'history_id',
        'customer_id',
        'amount_type',
        'amount_date',
        'details'
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function member_finance()
    {
        return $this->belongsTo(MemberFinance::class, 'member_finance_id', 'id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id')->select('id','customer_no','name', 'image', 'mobile');
    }

}
