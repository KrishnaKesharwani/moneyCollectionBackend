<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = [
        'user_id',
        'company_id',
        'customer_no',
        'name',
        'mobile',
        'email',
        'join_date',
        'aadhar_no',
        'image',
        'address',
        'status',
        'loan_count',
        'deposit_count',
        'created_by',
        'adhar_front',
        'adhar_back',
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

    public function getAdharFrontAttribute($value)
    {
        return !empty($value) ? url('storage/app/public/' . $value) : null;
    }

    public function getAdharBackAttribute($value)
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
}
