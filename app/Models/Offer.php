<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'offers';
    protected $fillable = ['name', 'company_id', 'type','status','details','image','default_offer'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function getImageAttribute($value)
    {
        return !empty($value) ? url('storage/app/public/' . $value) : null;
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id')->select('id', 'company_name','owner_name');
    }
}
