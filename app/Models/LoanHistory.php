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
        'received_member_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
