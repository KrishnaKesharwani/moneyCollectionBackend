<?php

namespace App\Repositories;

use App\Models\FixedDepositHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixedDepositHistoryRepository extends BaseRepository
{
    public function __construct(FixedDepositHistory $fixedDepositHistory)
    {
        parent::__construct($fixedDepositHistory);
    }
    

    public function getTotalDepositAmount($depositId, $type){
        return $this->model->where('fixed_deposit_id', $depositId)->where('action_type', $type)->sum('amount');
    }


    public function getMaxDepositHistoryDate($depositId){
        $maxDate = $this->model->where('fixed_eposit_id', $depositId)->where('action_type', 'credit')->max('action_date');
        if($maxDate)
            return $maxDate;
        else
            return null;
    }
    
    public function deleteByFixedDepositId($fixedDepositId){
        return $this->model->where('fixed_deposit_id', $fixedDepositId)->delete();
    }
    // You can add any specific methods related to User here
}
