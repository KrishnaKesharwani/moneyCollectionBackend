<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportBackup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table    = 'report_backups';
    protected $fillable = ['company_id', 'backup_type', 'search_data','backup_date','backup_by'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
