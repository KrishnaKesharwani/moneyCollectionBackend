<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vc extends Model
{
    use HasFactory;

    protected $table = 'vcs';

    protected $fillable = [
        'company_id',
        'vc_name',
        'type',
        'total_month',
        'total_member',
        'final_amount',
        'start_date',
        'end_date',
        'vc_image',
        'instalment_amount',
        'details',
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
