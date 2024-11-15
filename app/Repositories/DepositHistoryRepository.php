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

    public function getDepositIdByDate($depositId,$fromDate,$toDate){
        return $this->model->whereIn('deposit_id', $depositId)->whereDate('action_date','>=', $fromDate)->whereDate('action_date','<=', $toDate)->pluck('deposit_id')->toArray();
    }

    public function getDepositAmountByDate($depositId, $type,$fromDate,$toDate){
        return $this->model->whereIn('deposit_id', $depositId)->where('action_type', $type)->whereDate('action_date','>=', $fromDate)->whereDate('action_date','<=', $toDate)->sum('amount');
    }

    public function getMaxDepositHistoryDate($depositId){
        $maxDate = $this->model->where('deposit_id', $depositId)->where('action_type', 'credit')->max('action_date');
        if($maxDate)
            return $maxDate;
        else
            return null;
    }
    
    public function getDepositReceivedAmountByDate($companyId, $memberId, $date){
        $amount = DB::table('deposit_history')
                    ->join('customer_deposits', 'deposit_history.deposit_id', '=', 'customer_deposits.id')
                    ->where('customer_deposits.company_id', $companyId)
                    ->where('deposit_history.receiver_member_id', $memberId)
                    ->whereDate('deposit_history.action_date', $date)
                    ->where('deposit_history.action_type', 'credit')
                    ->sum('deposit_history.amount');
        return $amount;
    }
    // You can add any specific methods related to User here
}
