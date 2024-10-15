<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'loan_status_history';

    protected $fillable = [
        'loan_id',
        'loan_status',
        'loan_status_message',
        'loan_status_changed_by',
        'loan_status_change_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
