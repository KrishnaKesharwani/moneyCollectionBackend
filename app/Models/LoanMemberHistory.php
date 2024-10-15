<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanMemberHistory extends Model
{
    use HasFactory;

    protected $table = 'loan_member_history';

    protected $fillable = [
        'loan_id',
        'member_id',
        'assigned_date',
        'assigned_by',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
