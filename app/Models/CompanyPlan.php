<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyPlan extends Model
{
    use HasFactory;

    protected $table = 'company_plans';

    protected $fillable = [
        'plan',
        'company_id',
        'total_amount',
        'advance_amount',
        'full_paid',
        'start_date',
        'end_date',
        'status',
    ];
}
