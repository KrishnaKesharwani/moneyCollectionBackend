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

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function plans(){
        return $this->hasMany(CompanyPlan::class);
    }
    public function getMainLogoAttribute($value)
    {
        return !empty($value) ? url('storage/app/public/' . $value) : null;
    }

    // Accessor for sidebar_logo
    public function getSidebarLogoAttribute($value)
    {
        return !empty($value) ? url('storage/app/public/' . $value) : null;
    }

    // Accessor for favicon_icon
    public function getFaviconIconAttribute($value)
    {
        return !empty($value) ? url('storage/app/public/' . $value) : null;
    }

    // Accessor for owner_image
    public function getOwnerImageAttribute($value)
    {
        return !empty($value) ? url('storage/app/public/' . $value) : null;
    }
}
