<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepositRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'deposit_requests';

    protected $fillable = [
        'deposit_id',
        'request_amount',
        'reason',
        'requested_by',
        'status',
        'request_date',
        'approved_at',
        'replied_message'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function user(){
        return $this->belongsTo(User::class, 'requested_by');
    }
}
