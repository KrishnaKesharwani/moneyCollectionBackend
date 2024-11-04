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
        'amount_type',
        'amount_date',
        'details'
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

}
