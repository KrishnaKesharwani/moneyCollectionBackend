<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedDeposit extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fixed_deposits';

    protected $fillable = [
        'company_id',
        'customer_id',
        'name',
        'apply_date',
        'start_date',
        'end_date',
        'deposit_amount',
        'refund_amount',
        'status',
        'deposit_status',
        'details',
        'reason',
        'status_change_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->select('id','customer_no','name', 'image', 'mobile');
    }

    public function fixedDepositHistory()
    {
        return $this->hasMany(FixedDepositHistory::class, 'fixed_deposit_id', 'id');
    }
}
