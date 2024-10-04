<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyPlanHistory extends Model
{
    use HasFactory;

    protected $table = 'company_plan_history';

    protected $fillable = [
        'plan_id',
        'amount',
        'pay_date',
        'detail'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function plan()
    {
        return $this->belongsTo(CompanyPlan::class);
    }
}
