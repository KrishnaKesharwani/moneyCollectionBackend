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
    
    public function getDepositReceivedAmountByDate($companyId, $memberId=null, $date=null,$status=null,$type='credit'){
        $amount = DB::table('deposit_history')
                    ->join('customer_deposits', 'deposit_history.deposit_id', '=', 'customer_deposits.id')
                    ->where('customer_deposits.company_id', $companyId);
        if($status!=null){
            $amount = $amount->where('customer_deposits.status', $status);
        }
        if($memberId!=null){
            $amount = $amount->where('deposit_history.receiver_member_id', $memberId);
        }
        if($date!=null){
            $amount = $amount->whereDate('deposit_history.action_date', $date);
        }
        $amount = $amount->where('deposit_history.action_type', $type)
                    ->sum('deposit_history.amount');
        return $amount;
    }


    public function getDepositReceivedAmountByDatewise($companyId, $memberId=null, $fromDate, $toDate,$status=null){
        $amount = DB::table('deposit_history')
                    ->join('customer_deposits', 'deposit_history.deposit_id', '=', 'customer_deposits.id')
                    ->where('customer_deposits.company_id', $companyId);
        if($status!=null){
            $amount = $amount->where('customer_deposits.status', $status);
        }
        if($memberId!=null){
            $amount = $amount->where('deposit_history.receiver_member_id', $memberId);
        }
        $amount = $amount
                    ->whereDate('deposit_history.action_date','>=', $fromDate)
                    ->whereDate('deposit_history.action_date','<=', $toDate)
                    ->where('deposit_history.action_type', 'credit')
                    ->sum('deposit_history.amount');
        return $amount;
    }

    public function getdepositAmountByCustomerId($customerId,$type){
        $amount = DB::table('deposit_history')
                ->where('action_type',$type)
                ->whereIn('deposit_id', function ($query) use ($customerId) {
                    $query->select('id')
                          ->from('customer_deposits')
                          ->where('status', 'active')
                          ->where('customer_id', $customerId);
                })                
                ->sum('amount');
        return $amount;
    }

    public function getTodayCollection($memberId){
        $data = DB::table('deposit_history')
                ->select('deposit_history.*', 'customers.name as customer_name', 'customers.mobile as mobile','customers.id as customer_id')
                ->join('customer_deposits', 'deposit_history.deposit_id', '=', 'customer_deposits.id')
                ->join('customers', 'customer_deposits.customer_id', '=', 'customers.id')
                ->where('deposit_history.receiver_member_id', $memberId)
                ->whereDate('action_date', carbon::today())
                ->get();
        return $data;
    }


    // You can add any specific methods related to User here
}
