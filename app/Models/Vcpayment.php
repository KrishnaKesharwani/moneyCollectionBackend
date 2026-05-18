<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vcpayment extends Model
{
    use HasFactory;

    protected $table = 'vcs_payment';

    protected $fillable = [
        'company_id',
        'customer_id',
        'vc_id',
        'paid_amount',
        'paid_date',
        'amount_type',
        'details',
        'document1',
        'document2',
        'status', 
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getImageAttribute($value)
    {
        return !empty($value) ? url('storage/app/public/' . $value) : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function recived_member()
    {
        return $this->hasMany(LoanHistory::class, 'receiver_member_id');
    }
}
