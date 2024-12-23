<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class FixedDepositHistory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fixed_deposits_history';

    protected $fillable = [
        'fixed_deposit_id',
        'amount',
        'action_type',
        'action_date',
        'debit_type',
        'details'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
