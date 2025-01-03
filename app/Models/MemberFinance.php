<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberFinance extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'member_finance';

    protected $fillable = [
        'member_id',
        'company_id',
        'collect_date',
        'balance',
        'paid_amount',
        'payment_status',
        'remaining_amount',
        'previous_balance',
        'details',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class)->select('id','member_no','name', 'image');
    }

    public function member_finance_history()
    {
        return $this->hasMany(MemberFinanceHistory::class);
    }

}
