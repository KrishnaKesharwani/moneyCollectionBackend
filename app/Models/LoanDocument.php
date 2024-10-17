<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanDocument extends Model
{
    use HasFactory;

    protected $table = 'loan_documents';

    protected $fillable = [
        'loan_id',
        'document_url',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function loan()
    {
        return $this->belongsTo(CustomerLoan::class, 'loan_id');
    }

    public function getDocumentUrlAttribute($value)
    {
        return !empty($value) ? url('storage/app/public/' . $value) : null;
    }
}
