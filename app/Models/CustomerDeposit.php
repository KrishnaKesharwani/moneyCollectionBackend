<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CustomerDeposit extends Model
{
    use HasFactory;

    protected $table = 'customer_deposits';

    protected $fillable = [
        'company_id',
        'customer_id',
        'deposit_no',
        'assigned_member_id',
        'member_changed_reason',
        'details',
        'created_by',
        'status',
        'status_changed_reason',
        'status_changed_by',
        'status_changed_at',
    ];

    // protected $hidden = [
    //     'created_at',
    //     'updated_at',
    //     'deleted_at',
    // ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->select('id','customer_no','name', 'image', 'mobile');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'assigned_member_id')->select('id','member_no','name', 'image');;
    }

    public function depositHistory()
    {
        return $this->hasMany(DepositHistory::class, 'deposit_id');
    }

    public function getCreatedAtAttribute()
    {
        //change format to Y-m-d
        return Carbon::parse($this->attributes['created_at'])->format('Y-m-d'); 
    }
}
