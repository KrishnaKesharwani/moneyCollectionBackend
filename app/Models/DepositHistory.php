<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositHistory extends Model
{
    use HasFactory;

    protected $table = 'deposit_history';

    protected $fillable = [
        'deposit_id',
        'amount',
        'action_type',
        'action_date',
        'receiver_member_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function recieved_member(){
        return $this->belongsTo(Member::class, 'receiver_member_id')->select('id', 'name');
    }


    public function deposit(){
        return $this->belongsTo(CustomerDeposit::class, 'deposit_id')->select('id', 'customer_id','loan_amount','loan_no');
    }
}
