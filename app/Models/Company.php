<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'company_name',
        'owner_name',
        'mobile',
        'start_date',
        'end_date',
        'aadhar_no',
        'total_amount',
        'advance_amount',
        'status',
        'main_logo',
        'sidebar_logo',
        'favicon_icon',
        'owner_image',
        'address',
        'details',
    ];
}
