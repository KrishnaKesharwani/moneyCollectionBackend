<?php

namespace App\Repositories;

use App\Models\DepositHistory;
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
        $maxDate = $this->model->where('Deposit_id', $depositId)->where('action_type', 'credit')->max('action_date');
        if($maxDate)
            return $maxDate;
        else
            return null;
    }

    public function getTodayDepositCollection($memberId){
        $data = $this->model->with('deposit.customer')->where('receiver_member_id', $memberId)->whereDate('action_date', carbon::today())->get();
        return $data;
    }
    
    // You can add any specific methods related to User here
}
