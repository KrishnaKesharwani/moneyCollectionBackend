<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositMemberHistory extends Model
{
    use HasFactory;

    protected $table = 'deposit_member_history';

    protected $fillable = [
        'deposit_id',
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
