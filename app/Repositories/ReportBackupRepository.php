<?php

namespace App\Repositories;
use App\Models\ReportBackup;

class ReportBackupRepository extends BaseRepository
{
    public function __construct(ReportBackup $reportBackup)
    {
        parent::__construct($reportBackup);
    }

    Public function getLastBackupDate($companyId,$backupType)
    {
        $backupDate = $this->model->where('company_id', $companyId)->where('backup_type', $backupType)->max('backup_date');
        if($backupDate)
        {
            return $backupDate;
        }
        else
        {
            return '';
        }
    }
}