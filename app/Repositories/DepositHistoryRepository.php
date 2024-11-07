<?php

namespace App\Repositories;

use App\Models\DepositHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DepositHistoryRepository extends BaseRepository
{
    public function __construct(DepositHistory $depositHistory)
    {
        parent::__construct($depositHistory);
    }
    

    public function getTotalDepositAmount($depositId, $type){
        return $this->model->where('deposit_id', $depositId)->where('action_type', $type)->sum('amount');
    }


    public function getMaxDepositHistoryDate($depositId){
        $maxDate = $this->model->where('deposit_id', $depositId)->where('action_type', 'credit')->max('action_date');
        if($maxDate)
            return $maxDate;
        else
            return null;
    }
    
    // You can add any specific methods related to User here
}
