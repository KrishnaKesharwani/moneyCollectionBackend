<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class LoanMemberHistory extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table = 'loan_member_history';

    protected $fillable = [
        'loan_id',
        'member_id',
        'assigned_date',
        'assigned_by',
        'member_changed_reason',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
