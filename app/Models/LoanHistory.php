<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanHistory extends Model
{
    use HasFactory;

    protected $table = 'loan_history';

    protected $fillable = [
        'loan_id',
        'amount',
        'receive_date',
        'receiver_member_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function recieved_member(){
        return $this->belongsTo(Member::class, 'receiver_member_id')->select('id', 'name');
    }
}
